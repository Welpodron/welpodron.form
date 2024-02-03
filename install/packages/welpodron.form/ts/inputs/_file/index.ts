const PARENT_MODULE_BASE = 'form';
const MODULE_BASE = 'input-file';

const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;
const ATTRIBUTE_BASE_MAX_AMOUNT = `${ATTRIBUTE_BASE}-max-amount`;
const ATTRIBUTE_BASE_MAX_SIZE = `${ATTRIBUTE_BASE}-max-size`;
const ATTRIBUTE_BASE_MAX_SIZE_TOTAL = `${ATTRIBUTE_BASE}-max-size-total`;

const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;

const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;

const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;

const ATTRIBUTE_DROPZONE = `${ATTRIBUTE_BASE}-dropzone`;
const ATTRIBUTE_DROPZONE_ACTIVE = `${ATTRIBUTE_DROPZONE}-active`;

const _ATTRIBUTE_FETCHER = `${ATTRIBUTE_BASE}-fetcher`;

//! WARNING WILL BE MOVED TO CORE
// Original: https://stackoverflow.com/questions/6122571/simple-non-secure-hash-function-for-javascript
// thanks to Barak and Vitim.us
const _hash = (str: string) => {
  let hash = 0;
  for (let i = 0, len = str.length; i < len; i++) {
    let chr = str.charCodeAt(i);
    hash = (hash << 5) - hash + chr;
    hash |= 0; // Convert to 32bit integer
  }
  return hash;
};

type _FetcherPropsType<BaseElementType extends HTMLElement> = {
  element: BaseElementType;

  input: Input;
};

class _Fetcher<BaseElementType extends HTMLElement = HTMLElement> {
  element: BaseElementType;

  input: Input;

  controller: AbortController;

  constructor({ element, input }: _FetcherPropsType<BaseElementType>) {
    this.element = element;

    this.input = input;

    this.controller = new window.AbortController();

    this.element.addEventListener('click', this._handleElementClick.bind(this));
  }

  protected _handleElementClick = async () => {
    if (typeof window.DataTransfer === 'function') {
      const url = prompt('Введите URL', '')?.trim() || '';

      if (!url.length) {
        return;
      }

      const transfer = new window.DataTransfer();

      try {
        this.controller = new window.AbortController();

        const response = await fetch(url, {
          signal: this.controller.signal,
        });

        if (!response.ok) {
          throw new Error(`${response.status}: ${response.statusText}`);
        }

        const result = await response.blob();

        transfer.items.add(
          new File(
            [result],
            `${Math.floor(
              Math.random() * Math.floor(Math.random() * Date.now())
            )}`,
            {
              lastModified: new Date().getTime(),
              type: result.type,
            }
          )
        );

        this.input.change({ args: transfer.files });
      } catch (error) {
        if (!this.controller.signal.aborted) {
          console.error(error);
        }
      }
    }
  };
}

type DropzonePropsType<BaseElementType extends HTMLElement> = {
  element: BaseElementType;

  input: Input;
};

class Dropzone<BaseElementType extends HTMLElement = HTMLElement> {
  element: BaseElementType;

  input: Input;

  constructor({ element, input }: DropzonePropsType<BaseElementType>) {
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
      if (this.input.fetcher) {
        this.input.fetcher.controller.abort();
      }

      this.input.change({ args: event.dataTransfer.files });
    }
  };
}

type InputConfigType = {
  maxAmount?: number;

  maxSize?: number;
  maxSizeTotal?: number;

  dropzoneElement?: HTMLElement;

  fetcherElement?: HTMLElement;
};

type InputPropsType<BaseElementType extends HTMLElement> = {
  element: BaseElementType;

  config?: InputConfigType;
};

class Input<BaseElementType extends HTMLElement = HTMLElement> {
  static readonly SUPPORTED_ACTIONS = ['change', 'remove'];

  maxSize = 0;
  maxSizeTotal = 0;

  maxAmount = 0;

  element: BaseElementType;
  input: HTMLInputElement;

  dropzone?: Dropzone;

  fetcher?: _Fetcher;

  constructor({ element, config = {} }: InputPropsType<BaseElementType>) {
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

    const dropzoneElement: HTMLElement | null =
      config.dropzoneElement ||
      document.querySelector(
        `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
          `${ATTRIBUTE_BASE_ID}`
        )}"][${ATTRIBUTE_DROPZONE}]`
      );

    if (dropzoneElement) {
      this.dropzone = new Dropzone({ element: dropzoneElement, input: this });
    }

    const fetcherElement: HTMLElement | null =
      config.fetcherElement ||
      document.querySelector(
        `[${ATTRIBUTE_BASE_ID}="${this.element.getAttribute(
          `${ATTRIBUTE_BASE_ID}`
        )}"][${_ATTRIBUTE_FETCHER}]`
      );

    if (fetcherElement) {
      this.fetcher = new _Fetcher({ element: fetcherElement, input: this });
    }

    this.input.addEventListener('input', this._handleInputInput.bind(this));

    document.addEventListener('click', this._handleDocumentClick.bind(this));
  }

  change = ({ args, event }: { args?: FileList | null; event?: Event }) => {
    if (this.fetcher) {
      this.fetcher.controller.abort();
    }

    const beforeValue = this.input.files
      ? [...this.input.files]
          .map((file) =>
            _hash(file.lastModified + file.name + file.size + file.type)
          )
          .sort()
      : [];

    if (typeof window.DataTransfer === 'function') {
      if (args instanceof FileList) {
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

    const afterValue = this.input.files
      ? [...this.input.files]
          .map((file) =>
            _hash(file.lastModified + file.name + file.size + file.type)
          )
          .sort()
      : [];

    if (
      beforeValue.length !== afterValue.length ||
      beforeValue.some((hash, index) => hash !== afterValue[index])
    ) {
      const _event = new Event('input', {
        bubbles: true,
        cancelable: true,
      });

      this.input.dispatchEvent(_event);
    }
  };

  remove = ({ args, event }: { args?: string | number; event?: Event }) => {
    if (this.fetcher) {
      this.fetcher.controller.abort();
    }

    if (!this.input.files?.length) {
      return;
    }

    const beforeValue = this.input.files
      ? [...this.input.files]
          .map((file) =>
            _hash(file.lastModified + file.name + file.size + file.type)
          )
          .sort()
      : [];

    if (typeof window.DataTransfer === 'function') {
      const transfer = new window.DataTransfer();

      for (let i = 0; i < this.input.files.length; i++) {
        if (i == args) {
          continue;
        }

        transfer.items.add(this.input.files[i]);
      }

      this.input.files = transfer.files;
    }

    const afterValue = this.input.files
      ? [...this.input.files]
          .map((file) =>
            _hash(file.lastModified + file.name + file.size + file.type)
          )
          .sort()
      : [];

    if (
      beforeValue.length !== afterValue.length ||
      beforeValue.some((hash, index) => hash !== afterValue[index])
    ) {
      const _event = new Event('input', {
        bubbles: true,
        cancelable: true,
      });

      this.input.dispatchEvent(_event);
    }
  };

  protected _handleInputInput = (event: Event) => {
    if (!event.isTrusted) {
      return;
    }

    this.change({ args: this.input.files });
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
}

export { Input as inputFile };
