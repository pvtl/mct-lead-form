
let state = {
  stage: 1,
  submitting: false,
  leadId: null,
  buttons: {},
  errorMessage: null,
  errors: null,
};

const getState = () => state;

const setState = (newState, callback = null) => {
  state = { ...state, ...newState };

  if (typeof callback === 'function') {
    callback();
  }
};

export { getState, setState };
