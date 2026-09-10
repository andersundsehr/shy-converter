const HTML_SOFT_HYPHEN = "&shy;";
const MALFORMED_HTML_SOFT_HYPHEN = "&shy";
const UTF8_SOFT_HYPHEN = "\u00ad";
const INITIALIZED_ATTRIBUTE = "visibleShyInitialized";
const synchronizersByForm = new WeakMap();

const replaceAll = (value, search, replacement) => value.split(search).join(replacement);

export const makeVisible = (value) => replaceAll(value, UTF8_SOFT_HYPHEN, HTML_SOFT_HYPHEN);

export const convertToUtf8 = (value) => replaceAll(
  replaceAll(value, HTML_SOFT_HYPHEN, UTF8_SOFT_HYPHEN),
  MALFORMED_HTML_SOFT_HYPHEN,
  UTF8_SOFT_HYPHEN,
);

const registerSubmitSynchronizer = (form, synchronizer) => {
  let synchronizers = synchronizersByForm.get(form);
  if (synchronizers === undefined) {
    synchronizers = new Set();
    synchronizersByForm.set(form, synchronizers);
    form.addEventListener("submit", () => {
      synchronizers.forEach((registeredSynchronizer) => registeredSynchronizer(false));
    }, { capture: true });
  }
  synchronizers.add(synchronizer);
};

export const initialize = (fieldName) => {
  const visibleField = Array.from(document.querySelectorAll("[data-formengine-input-name]"))
    .find((field) => field.dataset.formengineInputName === fieldName);
  if (!(visibleField instanceof HTMLInputElement) || visibleField.dataset[INITIALIZED_ATTRIBUTE] === "true") {
    return;
  }

  const form = visibleField.form;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const hiddenField = Array.from(form.elements)
    .find((field) => field instanceof HTMLInputElement && field.type === "hidden" && field.name === fieldName);
  if (!(hiddenField instanceof HTMLInputElement)) {
    return;
  }

  // Defensive fallback: PHP removes this before the FormEngine is initialized.
  // Removing it here as well prevents TYPO3 from installing its own conflicting
  // visible-to-hidden synchronization if the markup changes in a future version.
  visibleField.removeAttribute("data-formengine-input-params");
  visibleField.value = makeVisible(hiddenField.value);

  let dispatchingHiddenChange = false;
  const synchronize = (dispatchChange) => {
    if (!visibleField.isConnected || !hiddenField.isConnected || visibleField.disabled) {
      return;
    }

    const convertedValue = convertToUtf8(visibleField.value);
    if (hiddenField.value === convertedValue) {
      return;
    }

    if (hiddenField.disabled && "enableOnModification" in hiddenField.dataset) {
      hiddenField.disabled = false;
    }
    hiddenField.value = convertedValue;

    if (dispatchChange) {
      dispatchingHiddenChange = true;
      hiddenField.dispatchEvent(new Event("change", { bubbles: true }));
      dispatchingHiddenChange = false;
    }
  };

  visibleField.addEventListener("input", () => synchronize(false));
  visibleField.addEventListener("change", () => synchronize(true));
  hiddenField.addEventListener("change", () => {
    if (!dispatchingHiddenChange) {
      visibleField.value = makeVisible(hiddenField.value);
    }
  });
  registerSubmitSynchronizer(form, synchronize);

  visibleField.dataset[INITIALIZED_ATTRIBUTE] = "true";
};
