const HTML_SOFT_HYPHEN = "&shy;";
const MALFORMED_HTML_SOFT_HYPHEN = "&shy";
const UTF8_SOFT_HYPHEN = "\u00ad";
const HTML_NON_BREAKING_SPACE = "&nbsp;";
const UTF8_NON_BREAKING_SPACE = "\u00a0";
const INITIALIZED_ATTRIBUTE = "visibleShyInitialized";
const synchronizersByForm = new WeakMap();

const replaceAll = (value, search, replacement) => value.split(search).join(replacement);

export const makeVisible = (value) => value
  .replace(/&(?=#\d+;|#x[0-9a-fA-F]+;|[a-zA-Z][a-zA-Z0-9]+;)/g, "&amp;")
  .replace(/\u00ad/g, HTML_SOFT_HYPHEN)
  .replace(/\u00a0/g, HTML_NON_BREAKING_SPACE);

export const convertToUtf8 = (value) => replaceAll(
  value
    .replace(/&shy;/gi, UTF8_SOFT_HYPHEN)
    .replace(/&nbsp;/gi, UTF8_NON_BREAKING_SPACE),
  MALFORMED_HTML_SOFT_HYPHEN,
  UTF8_SOFT_HYPHEN,
).replace(/&amp;/gi, "&");

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
