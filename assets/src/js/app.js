const containers = document.querySelectorAll('[data-mct-lead-form]');

containers.forEach((container) => {
  const host = container.dataset.mctLeadForm;
  const redirect = container.dataset.redirect;

  let state = {
    stage: 1,
    submitting: false,
    leadId: null,
    data: {},
    buttons: {},
    errorMessage: null,
    errors: null,
  };

  const setState = (newState) => state = { ...state, ...newState };

  const render = () => {
    const { stage, errorMessage, buttons, submitting, errors } = state;

    const form = container.querySelector(`[data-mct-stage="${stage}"]`);

    container.querySelectorAll(`[data-mct-stage]`).forEach((form) => form.style.display = 'none');

    form.style.display = 'block';

    const errorMessageContainer = container.querySelector(`[data-mct-message="error"]`);

    if (errorMessage) {
      errorMessageContainer.style.display = 'block';
      errorMessageContainer.innerHTML = errorMessage;
    } else {
      errorMessageContainer.style.display = 'hide';
      errorMessageContainer.innerHTML = '';
    }

    const button = form.querySelector('button[type="submit"]');

    let { [stage]: stageButton = null } = buttons;

    if (!stageButton) {
      stageButton = button.innerHTML.trim();

      setState({ buttons: { [stage]: stageButton } });
    }

    if (submitting) {
      button.innerHTML = 'Submitting';
    } else {
      button.innerHTML = stageButton;
    }

    if (errors !== null) {
      Object.keys(errors).forEach((error) => {
        const input = form.querySelector(`[name="${error}"]`);
        const group = input.parentElement;
        const feedback = group.querySelector('.invalid-feedback');

        group.classList.add('is-invalid');

        feedback.innerHTML = errors[error];
        feedback.style.display = 'block';
      });
    } else {
      form.querySelectorAll('.is-invalid').forEach((group) => group.classList.remove('is-invalid'));
    }
  };

  const prevButton = container.querySelector('[data-prev]');

  if (prevButton) {
    prevButton.addEventListener('click', (e) => {
      e.preventDefault();

      const { stage } = state;

      if (stage > 1) {
        setState({ stage: stage - 1 });
      }

      render();
    });
  }

  document.addEventListener('submit', async (e) => {
    const form = e.target;

    if (!form.matches('[data-mct-stage]')) {
      return;
    }

    e.preventDefault();

    const { stage, submitting, leadId } = state;

    if (submitting) {
      return;
    }

    setState({ submitting: true, errorMessage: null, errors: null });

    const data = new FormData(form);

    let dataSerialised = {};

    for (let [key, value] of data) {
      dataSerialised = { ...dataSerialised, [key]: value };
    }

    const url = `${host}/${form.dataset.endpoint.replace('{id}', leadId)}`;
    const body = JSON.stringify(dataSerialised);
    const method = form.dataset.method;
    const headers = new Headers({
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-WP-Nonce': form.dataset.nonce,
    });

    render();

    try {
      const response = await fetch(url, { method, headers, body });
      const json = await response.json();

      const { code = null, errors = null } = json;

      setState({ errors });

      if (code !== null) {
        throw new Error();
      }

      if (errors === null) {
        const { data: { id = null } } = json;

        if (stage === 1) {
          const updateEndpoint = container.querySelector('[data-mct-stage="2"]').dataset.endpoint;

          form.dataset.endpoint = updateEndpoint;
        }

        if (stage === 2) {
          window.location = redirect;

          return;
        }

        setState({ submitting: false });

        render();

        setState({ leadId: id, stage: 2 });
      }
    } catch (err) {
      console.log(err);

      setState({ errorMessage: 'An error occurred' });
    }

    setState({ submitting: false });

    render();
  });

  render();
});
