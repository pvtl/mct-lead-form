import { getState, setState } from './state';

// Get the active stage form.
const getCurrentForm = (container) => {
  const { stage } = getState();

  return container.querySelector(`[data-mct-stage="${stage}"]`);
};

// Show/hide the active/inactive stages.
const toggleForms = (container) => {
  const { stage } = getState();

  container.querySelectorAll(`[data-mct-stage]:not([data-mct-stage="${stage}"])`)
    .forEach((form) => form.style.display = 'none');

  container.querySelector(`[data-mct-stage="${stage}"]`).style.display = 'block';
};

// Render the submit button text based on the submitting state.
const renderButton = (container) => {
  const { stage, submitting, buttons } = getState();

  const form = getCurrentForm(container);
  const button = form.querySelector('button[type="submit"]');

  let { [stage]: buttonText = null } = buttons;

  if (!buttonText) {
    buttonText = button.innerHTML.trim();

    setState({ buttons: { [stage]: buttonText } });
  }

  button.innerHTML = submitting ? 'Submitting' : buttonText;
};

// Show any form validation errors.
const showErrors = (container) => {
  const { errorMessage, errors } = getState();

  const form = getCurrentForm(container);
  const alert = container.querySelector(`[data-mct-message="error"]`);

  alert.style.display = errorMessage ? 'block' : 'none';
  alert.innerHTML = errorMessage;

  if (errors === null) {
    form.querySelectorAll('.is-invalid')
      .forEach((formGroup) => formGroup.classList.remove('is-invalid'));

    return;
  }

  Object.keys(errors).forEach((error) => {
    const input = form.querySelector(`[name="${error}"]`);

    if (!input) {
      console.warn('input not found', error);

      return;
    }

    const formGroup = input.parentElement;
    const feedback = formGroup.querySelector('.invalid-feedback');

    formGroup.classList.add('is-invalid');

    feedback.innerHTML = errors[error];
  });
};

export default (container) => {
  toggleForms(container);
  renderButton(container);
  showErrors(container);
};
