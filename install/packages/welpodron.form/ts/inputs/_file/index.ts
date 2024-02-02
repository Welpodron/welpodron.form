const PARENT_MODULE_BASE = 'form';
const MODULE_BASE = 'input-file';

const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;
const ATTRIBUTE_BASE_MAX_AMOUNT = `${ATTRIBUTE_BASE}-max-amount`;
const ATTRIBUTE_BASE_MAX_SIZE = `${ATTRIBUTE_BASE}-max-size`;
const ATTRIBUTE_BASE_MAX_SIZE_TOTAL = `${ATTRIBUTE_BASE}-max-size-total`;

const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;

const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;
const ATTRIBUTE_CONTROL_ACTIVE = `${ATTRIBUTE_CONTROL}-active`;

const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;
const ATTRIBUTE_ACTION_FORCE = `${ATTRIBUTE_ACTION}-force`;

const ATTRIBUTE_DROPZONE = `${ATTRIBUTE_BASE}-dropzone`;
const ATTRIBUTE_DROPZONE_ACTIVE = `${ATTRIBUTE_DROPZONE}-active`;

type DropzonePropsType = {
  element: HTMLElement;
  input: Input;
};

class Dropzone {
  element: HTMLElement;

  input: Input;

  constructor({ element, input }: DropzonePropsType) {
    this.element = element;
    this.input = input;

    [
      'drag',
      'dragstart',
      'dragend',
      'dragover',
      'dragenter',
      'dragleave',
      'drop',
    ].forEach((eventName) => {
      this.element.addEventListener(eventName, (event) => {
        event.preventDefault();
        event.stopPropagation();
      });
    });

    this.element.addEventListener('drop', this._handleElementDrop.bind(this));
  }

  protected _handleElementDrop = (event: DragEvent) => {
    if (event.dataTransfer && event.dataTransfer.files) {
      this.input.change({ args: event.dataTransfer.files });
    }
  };
}

type InputConfigType = {
  maxAmount?: number;

  maxSize?: number;
  maxSizeTotal?: number;

  dropzoneElement?: HTMLElement;
};

type InputPropsType = {
  element: HTMLElement;

  config?: InputConfigType;
};

class Input {
  maxSize = 0;
  maxSizeTotal = 0;

  maxAmount = 0;

  element: HTMLElement;
  input: HTMLInputElement;

  dropzone?: Dropzone;

  constructor({ element, config = {} }: InputPropsType) {
    this.element = element;

    this.input = document.querySelector(
      `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
        `${ATTRIBUTE_BASE_ID}`
      )}"][${ATTRIBUTE_INPUT}]`
    ) as HTMLInputElement;

    const maxSize = config.maxSize
      ? Number(config.maxSize)
      : Number(this.element.getAttribute(ATTRIBUTE_BASE_MAX_SIZE));

    // So maxSize will come in mb form so 1024 * 1024 = 1048576
    this.maxSize = Number.isNaN(maxSize) ? 0 : maxSize * 1048576;

    const maxSizeTotal = config.maxSizeTotal
      ? Number(config.maxSizeTotal)
      : Number(this.element.getAttribute(ATTRIBUTE_BASE_MAX_SIZE_TOTAL));

    // So maxSizeTotal will come in mb form so 1024 * 1024 = 1048576
    this.maxSizeTotal = Number.isNaN(maxSizeTotal) ? 0 : maxSizeTotal * 1048576;

    const maxAmount = config.maxAmount
      ? Number(config.maxAmount)
      : Number(this.element.getAttribute(ATTRIBUTE_BASE_MAX_AMOUNT));

    this.maxAmount = Number.isNaN(maxAmount) ? 0 : maxAmount;

    const dropzoneElement: HTMLElement | null = config.dropzoneElement
      ? config.dropzoneElement
      : document.querySelector(
          `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
            `${ATTRIBUTE_BASE_ID}`
          )}"][${ATTRIBUTE_DROPZONE}]`
        );

    if (dropzoneElement) {
      this.dropzone = new Dropzone({ element: dropzoneElement, input: this });
    }

    this.input.addEventListener('input', this._handleInputInput.bind(this));
  }

  change = ({ args, event }: { args?: FileList | null; event?: Event }) => {
    debugger;

    if (typeof window.DataTransfer === 'function') {
      if (args) {
        let i = 0;

        const transfer = new window.DataTransfer();

        const checks = this.input.accept
          ? this.input.accept.split(',').map((v) => new RegExp(v.trim()))
          : [];

        let currentTotalSize = 0;

        for (const file of args) {
          if (this.maxSize > 0 && file.size > this.maxSize) {
            continue;
          }

          currentTotalSize += file.size;

          if (this.maxSizeTotal > 0 && currentTotalSize > this.maxSizeTotal) {
            break;
          }

          if (checks.length && !checks.some((check) => check.test(file.type))) {
            continue;
          }

          i++;

          if (this.maxAmount > 0 && i > this.maxAmount) {
            break;
          }

          transfer.items.add(file);
        }

        this.input.files = transfer.files;
      }
    }

    const _event = new Event('input', {
      bubbles: true,
      cancelable: true,
    });

    this.input.dispatchEvent(_event);
  };

  protected _handleInputInput = (event: Event) => {
    if (!event.isTrusted) {
      return;
    }

    this.change({ args: this.input.files });
  };
}
