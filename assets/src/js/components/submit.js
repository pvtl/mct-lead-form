import { getState, setState } from './state';
import render from './render';

// Serialize form data to JSON.
const serializeJson = (form) => {
  const formData = new FormData(form);
  let data = {};

  for (let [key, value] of formData) {
    data = { ...data, [key]: value };
  }

  return JSON.stringify(data);
};

// Send the API request.
const sendRequest = async (container, form) => {
  const { leadId, stage } = getState();

  const host = container.dataset.mctLeadForm;

  const url = `${host}/${form.dataset.endpoint.replace('{id}', leadId)}`;
  const body = serializeJson(form);
  const method = form.dataset.method;
  const headers = new Headers({
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-WP-Nonce': form.dataset.nonce,
  });

  try {
    const response = await fetch(url, { method, headers, body });
    const json = await response.json();

    const {
      message = null,
      code = null,
      errors = null,
      data: { id = null } = {},
    } = json;

    setState({ errors });

    if ((message !== null && message === 'Unauthenticated.') || code !== null) {
      throw new Error();
    }

    if (errors !== null) {
      return;
    }

    window.dataLayer = window.dataLayer || [];

    if (stage === 1) {
      const email = form.querySelector('[name="email"]').value;

      setState({ email });

      dataLayer.push({ email });
      dataLayer.push({ 'event': 'step1success' });

      form.dataset.endpoint = container.querySelector('[data-mct-stage="2"]').dataset.endpoint;
    }

    if (stage === 2) {
      dataLayer.push({ 'event': 'step2success' });

      const { email } = getState();

      try {
        window.localStorage.setItem('mct-lead-form-email', email);
      } catch (e) {
        console.warn(e);
      }

      window.location = container.dataset.redirect;

      return;
    }

    setState({ submitting: false }, () => render(container));
    setState({ leadId: id, stage: 2 });
  } catch (err) {
    console.log(err);

    setState({ errorMessage: 'Uh oh! An error occurred, please try again later.' });
  }
};

export default async (container, form) => {
  const { submitting } = getState();

  if (submitting) {
    return;
  }

  setState({ submitting: true, errorMessage: null, errors: null }, () => render(container));

  await sendRequest(container, form);

  setState({ submitting: false }, () => render(container));
};
