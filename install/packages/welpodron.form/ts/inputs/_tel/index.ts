//!Original: https://github.com/nosir/cleave.js Max Huang https://github.com/nosir/
const DELIMITERS = [' (', ') ', '-', '-'];
const BLOCKS = [1, 3, 3, 2, 2];

const isAndroid = navigator && /android/i.test(navigator.userAgent);

const stripDelimiters = (value: string) => {
  DELIMITERS.forEach((delimiter) => {
    value = value.replaceAll(delimiter, '');
  });

  return value;
};

const getPostDelimiter = (value: string) => {
  let matchedDelimiter = '';

  DELIMITERS.forEach((current) => {
    if (value.slice(-current.length) === current) {
      matchedDelimiter = current;
    }
  });

  return matchedDelimiter;
};

const getPositionOffset = (
  prevPos: number,
  oldValue: string,
  newValue: string
) => {
  let oldRawValue: string, newRawValue: string, lengthOffset: number;

  oldRawValue = stripDelimiters(oldValue.slice(0, prevPos));
  newRawValue = stripDelimiters(newValue.slice(0, prevPos));
  lengthOffset = oldRawValue.length - newRawValue.length;

  return lengthOffset !== 0 ? lengthOffset / Math.abs(lengthOffset) : 0;
};

const getFormattedValue = (value: string) => {
  let result = '';

  BLOCKS.forEach((length, index) => {
    if (value.length > 0) {
      let substring = value.slice(0, length),
        rest = value.slice(length);

      result += substring;

      if (substring.length === length && index < BLOCKS.length - 1) {
        result += DELIMITERS[index];
      }

      value = rest;
    }
  });

  return result;
};

const getNextCursorPosition = (
  prevPos: number,
  oldValue: string,
  newValue: string
) => {
  if (oldValue.length === prevPos) {
    return newValue.length;
  }

  return prevPos + getPositionOffset(prevPos, oldValue, newValue);
};

const setSelection = (element: HTMLInputElement, position: number) => {
  if (element !== document.activeElement) {
    return;
  }

  if (element && element.value.length <= position) {
    return;
  }

  element.setSelectionRange(position, position);
};

const PARENT_MODULE_BASE = 'form';
const MODULE_BASE = 'input-tel';

const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;
const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;

type InputConfigType = {};

type InputPropsType = {
  element: HTMLElement;
  config?: InputConfigType;
};

class Input {
  supportedActions = ['set'];

  element: HTMLElement;

  result = '';
  lastInputValue = '';
  isBackward = false;
  postDelimiterBackspace = '';

  constructor({ element, config = {} }: InputPropsType) {
    this.element = element;

    document.addEventListener('focus', this.handleDocumentFocus);
    document.addEventListener('keydown', this.handleDocumentKeyDown);
    document.addEventListener('input', this.handleDocumentInput);

    const input = this.element.querySelector(
      `[${ATTRIBUTE_INPUT}][${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"]`
    ) as HTMLInputElement;

    if (input.value) {
      this.set({ args: input.value });
    }
  }

  handleDocumentFocus = () => {
    const input = this.element.querySelector(
      `[${ATTRIBUTE_INPUT}][${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"]`
    ) as HTMLInputElement;

    if (
      !input ||
      !(input instanceof HTMLInputElement) ||
      document.activeElement !== input
    ) {
      return;
    }

    this.lastInputValue = input.value;
  };

  handleDocumentKeyDown = (event: KeyboardEvent) => {
    const input = this.element.querySelector(
      `[${ATTRIBUTE_INPUT}][${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"]`
    ) as HTMLInputElement;

    if (
      !input ||
      !(input instanceof HTMLInputElement) ||
      document.activeElement !== input
    ) {
      return;
    }

    this.lastInputValue = input.value;
    this.isBackward = event.key === 'Backspace';
  };

  handleDocumentInput = (event: Event) => {
    let { target } = event;

    if (!target) {
      return;
    }

    if (!event.isTrusted) {
      return;
    }

    target = (target as Element).closest(
      `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"][${ATTRIBUTE_INPUT}]`
    );

    if (
      !target ||
      !(target instanceof HTMLInputElement) ||
      document.activeElement !== target
    ) {
      return;
    }

    event.preventDefault();

    const postDelimiter = getPostDelimiter(this.lastInputValue);

    if (this.isBackward && postDelimiter) {
      this.postDelimiterBackspace = postDelimiter;
    } else {
      this.postDelimiterBackspace = '';
    }

    this.set({ args: target.value });
  };

  set = ({ args, event }: { args?: unknown; event?: Event }) => {
    if (args == null) {
      return;
    }

    const input = this.element.querySelector(
      `[${ATTRIBUTE_INPUT}][${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"]`
    ) as HTMLInputElement;

    const beforeInputValue = input.value;
    let changeValue = String(args);

    const postDelimiterAfter = getPostDelimiter(changeValue);

    if (this.postDelimiterBackspace && !postDelimiterAfter) {
      changeValue = changeValue.slice(
        0,
        changeValue.length - this.postDelimiterBackspace.length
      );
    }

    // strip existing delimiters from value
    changeValue = stripDelimiters(changeValue);

    // strip non-numeric characters
    changeValue = changeValue.replace(/[^\d]/g, '');

    // strip over length characters
    changeValue = changeValue.slice(0, 11);

    // apply blocks
    this.result = getFormattedValue(changeValue);

    let endPos = input.selectionEnd as number;

    endPos = getNextCursorPosition(endPos, input.value, this.result);

    if (isAndroid) {
      return setTimeout(() => {
        input.value = this.result;
        setSelection(input, endPos);
      }, 1);
    }

    input.value = this.result;
    setSelection(input, endPos);

    if (beforeInputValue !== input.value) {
      const _event = new Event('input', {
        bubbles: true,
        cancelable: true,
      });
      input.dispatchEvent(_event);
    }
  };
}

export {
  Input as inputTel,
  InputConfigType as InputTelConfigType,
  InputPropsType as InputTelPropsType,
};
