import { getState, setState } from './components/state';
import render from './components/render';
import submit from './components/submit';

// Handle changing to previous stage.
const handlePrevButton = (container) => {
  const prevButton = container.querySelector('[data-prev]');

  if (!prevButton) {
    return;
  }

  prevButton.addEventListener('click', (e) => {
    e.preventDefault();

    const { stage } = getState();

    if (stage > 1) {
      setState({ stage: stage - 1 });
    }

    render(container);
  });
};

document.querySelectorAll('[data-mct-lead-form]')
  .forEach((container) => {
    handlePrevButton(container);

    container.addEventListener('submit', (e) => {
      const form = e.target;

      if (!form.matches('[data-mct-stage]')) {
        return;
      }

      e.preventDefault();

      submit(container, form);
    });

    render(container);
  });
