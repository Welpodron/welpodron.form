const PARENT_MODULE_BASE = 'form';
const MODULE_BASE = 'input-number';

const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;

const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;

const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;

const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;

type InputConfigType = {
  maxValue?: number;
  minValue?: number;
};

type InputPropsType<BaseElementType extends HTMLElement> = {
  element: BaseElementType;

  config?: InputConfigType;
};

class Input<BaseElementType extends HTMLElement = HTMLElement> {
  maxValue = NaN;
  minValue = NaN;

  static readonly SUPPORTED_ACTIONS = ['change', 'set'];

  element: BaseElementType;

  input: HTMLInputElement;

  constructor({ element, config = {} }: InputPropsType<BaseElementType>) {
    this.element = element;

    this.input = document.querySelector(
      `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"][${ATTRIBUTE_INPUT}]`
    ) as HTMLInputElement;

    this.maxValue =
      config.maxValue != null
        ? Number(config.maxValue)
        : this.input.hasAttribute('max')
        ? Number(this.input.max)
        : NaN;

    this.minValue =
      config.minValue != null
        ? Number(config.minValue)
        : this.input.hasAttribute('min')
        ? Number(this.input.min)
        : NaN;

    document.addEventListener('click', this._handleDocumentClick.bind(this));

    this.input.addEventListener('input', this._handleInputInput.bind(this));
  }

  protected _handleInputInput = (event: Event) => {
    if (!event.isTrusted) {
      return;
    }

    this.change({ args: this.input.value });
  };

  protected _handleDocumentClick = (event: MouseEvent) => {
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

    if (!action || !Input.SUPPORTED_ACTIONS.includes(action as string)) {
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

  set = ({ args, event }: { args?: string | number; event?: Event }) => {
    const beforeValue = this.input.value;

    this.input.value = args + '';

    if (beforeValue !== this.input.value) {
      const _event = new Event('input', {
        bubbles: true,
        cancelable: true,
      });

      this.input.dispatchEvent(_event);
    }
  };

  change = ({ args, event }: { args?: string | number; event?: Event }) => {
    debugger;
    const beforeValue = this.input.value;

    if (typeof args === 'string' && args.trim() === '') {
      this.input.value = '';
    } else {
      let changedValue: string | number = Number(args);

      if (!Number.isNaN(this.minValue)) {
        if (changedValue < this.minValue) {
          changedValue = this.minValue;
        }
      }

      if (!Number.isNaN(this.maxValue)) {
        if (changedValue > this.maxValue) {
          changedValue = this.maxValue;
        }
      }

      if (Number.isNaN(changedValue) || !Number.isFinite(changedValue)) {
        changedValue = '';
      }

      this.input.value = changedValue + '';
    }

    if (beforeValue !== this.input.value) {
      const _event = new Event('input', {
        bubbles: true,
        cancelable: true,
      });

      this.input.dispatchEvent(_event);
    }
  };
}

export {
  Input as inputNumber,
  InputConfigType as InputNumberConfigType,
  InputPropsType as InputNumberPropsType,
};
