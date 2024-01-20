const PARENT_MODULE_BASE = 'form';
const MODULE_BASE = 'input-number';

const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;
const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;
const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;
const ATTRIBUTE_CONTROL_ACTIVE = `${ATTRIBUTE_CONTROL}-active`;
const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;
const ATTRIBUTE_ACTION_FORCE = `${ATTRIBUTE_ACTION}-force`;

type InputConfigType = {};

type InputPropsType = {
  element: HTMLElement;
  config?: InputConfigType;
};

class Input {
  supportedActions = ['change', 'set'];

  element: HTMLElement;

  constructor({ element, config = {} }: InputPropsType) {
    this.element = element;

    document.removeEventListener('click', this.handleDocumentClick);
    document.removeEventListener('input', this.handleDocumentInput);

    document.addEventListener('click', this.handleDocumentClick);
    document.addEventListener('input', this.handleDocumentInput);
  }

  handleDocumentInput = (event: Event) => {
    //TODO: Ну тут нужна одна шина событий, а не такое вот вот :c
    if (!document.contains(this.element)) {
      document.removeEventListener('click', this.handleDocumentClick);
      document.removeEventListener('input', this.handleDocumentInput);
      return;
    }

    let { target } = event;

    if (!target) {
      return;
    }

    target = (target as Element).closest(
      `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"][${ATTRIBUTE_INPUT}]`
    );

    if (!target || !(target instanceof HTMLInputElement)) {
      return;
    }

    if (!event.isTrusted) {
      return;
    }

    event.preventDefault();

    let inputValue = parseInt(target.value);

    if (isNaN(inputValue)) {
      target.value = '0';
    }

    inputValue = parseInt(target.value);

    if (target.min) {
      if (inputValue < parseInt(target.min)) {
        target.value = target.min;
      }
    }

    inputValue = parseInt(target.value);

    if (target.max) {
      if (inputValue > parseInt(target.max)) {
        target.value = target.max;
      }
    }

    inputValue = parseInt(target.value);

    if (isNaN(inputValue)) {
      target.value = '';
    }

    const _event = new Event('input', { bubbles: true, cancelable: true });
    target.dispatchEvent(_event);
  };

  handleDocumentClick = (event: MouseEvent) => {
    if (!document.contains(this.element)) {
      document.removeEventListener('click', this.handleDocumentClick);
      document.removeEventListener('input', this.handleDocumentInput);
      return;
    }

    let { target } = event;

    if (!target) {
      return;
    }

    target = (target as Element).closest(
      `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"][${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
    );

    if (!target) {
      return;
    }

    const action = (target as Element).getAttribute(
      ATTRIBUTE_ACTION
    ) as keyof this;

    const actionArgs = (target as Element).getAttribute(ATTRIBUTE_ACTION_ARGS);

    const actionFlush = (target as Element).getAttribute(
      ATTRIBUTE_ACTION_FLUSH
    );

    if (!actionFlush) {
      event.preventDefault();
    }

    if (!action || !this.supportedActions.includes(action as string)) {
      return;
    }

    const actionFunc = this[action] as any;

    if (actionFunc instanceof Function) {
      return actionFunc({
        args: actionArgs,
        event,
      });
    }
  };

  change = ({ args, event }: { args?: unknown; event?: Event }) => {
    if (!args) {
      return;
    }

    const inputs = this.element.querySelectorAll(
      `[${ATTRIBUTE_INPUT}][${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"]`
    ) as NodeListOf<HTMLInputElement>;

    inputs.forEach((input) => {
      const beforeInputValue = input.value;
      let inputValue = parseInt(input.value);
      const changeValue = parseInt(args as string);

      input.value = `${inputValue + changeValue}`;

      inputValue = parseInt(input.value);

      if (input.min) {
        if (inputValue + changeValue < parseInt(input.min)) {
          input.value = input.min;
        }
      }

      inputValue = parseInt(input.value);

      if (input.max) {
        if (inputValue + changeValue > parseInt(input.max)) {
          input.value = input.max;
        }
      }

      inputValue = parseInt(input.value);

      if (isNaN(inputValue)) {
        input.value = '';
      }

      if (beforeInputValue !== input.value) {
        const _event = new Event('input', {
          bubbles: true,
          cancelable: true,
        });
        input.dispatchEvent(_event);
      }
    });
  };

  set = ({ args, event }: { args?: unknown; event?: Event }) => {
    if (!args) {
      return;
    }

    let isForced = false;

    if (event?.target) {
      const target = (event?.target as Element).closest(
        `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
          `${ATTRIBUTE_BASE_ID}`
        )}"][${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
      );

      if (target && target.hasAttribute(ATTRIBUTE_ACTION_FORCE)) {
        isForced = true;
      }
    }

    const inputs = this.element.querySelectorAll(
      `[${ATTRIBUTE_INPUT}][${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"]`
    ) as NodeListOf<HTMLInputElement>;

    inputs.forEach((input) => {
      const beforeInputValue = input.value;
      let inputValue = parseInt(input.value);
      const changeValue = args as string;

      input.value = changeValue;

      if (!isForced) {
        inputValue = parseInt(input.value);

        if (isNaN(inputValue)) {
          input.value = '0';
        }

        inputValue = parseInt(input.value);

        if (input.min) {
          if (inputValue < parseInt(input.min)) {
            input.value = input.min;
          }
        }

        inputValue = parseInt(input.value);

        if (input.max) {
          if (inputValue > parseInt(input.max)) {
            input.value = input.max;
          }
        }

        inputValue = parseInt(input.value);

        if (isNaN(inputValue)) {
          input.value = '';
        }
      }

      if (beforeInputValue !== input.value) {
        const _event = new Event('input', {
          bubbles: true,
          cancelable: true,
        });
        input.dispatchEvent(_event);
      }
    });
  };
}

export {
  Input as inputNumber,
  InputConfigType as InputNumberConfigType,
  InputPropsType as InputNumberPropsType,
};
