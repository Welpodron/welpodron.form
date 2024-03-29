import { templater, utils } from 'welpodron.core';

const MODULE_BASE = 'form';

const EVENT_SUBMIT_BEFORE = `welpodron.${MODULE_BASE}:submit:before`;
const EVENT_SUBMIT_AFTER = `welpodron.${MODULE_BASE}:submit:after`;

const GENERAL_ERROR_CODE = 'FORM_GENERAL_ERROR';
const FIELD_VALIDATION_ERROR_CODE = 'FIELD_VALIDATION_ERROR';

type _BitrixResponse = {
  data: any;
  status: 'success' | 'error';
  errors: {
    code: string;
    message: string;
    customData: string;
  }[];
};

type FormConfigType = {};

type FormPropsType = {
  element: HTMLFormElement;
  config?: FormConfigType;
};

class Form {
  element: HTMLFormElement;

  action: string = '';

  isDisabled: boolean = false;

  responseContainer: HTMLElement;

  captchaLoaded:
    | (Promise<any> & {
        resolve: () => void;
      })
    | null = null;
  captchaKey: string | null = null;

  constructor({ element, config = {} }: FormPropsType) {
    this.element = element;

    this.element.addEventListener('input', this.handleFormInput);

    this.element.addEventListener('submit', this.handleFormSubmit);

    this.responseContainer = document.createElement('div');
    this.element.prepend(this.responseContainer);

    this.captchaKey = this.element.getAttribute('data-captcha');
    this.action = this.element.getAttribute('action') || '';

    // v4
    this.disable();

    if (this.captchaKey) {
      this.captchaLoaded = utils.deferred();

      if (!(window as any).grecaptcha) {
        const loadCaptcha = () => {
          if (document.querySelector(`script[src*="recaptcha"]`)) {
            if (this.element.checkValidity()) {
              this.enable();
            }
            this.captchaLoaded?.resolve();
            return;
          }
          const script = document.createElement('script');
          script.src = `https://www.google.com/recaptcha/api.js?render=${this.captchaKey}`;
          document.body.appendChild(script);
          script.onload = () => {
            (window as any).grecaptcha.ready(() => {
              if (this.element.checkValidity()) {
                this.enable();
              }
              this.captchaLoaded?.resolve();
            });
          };
        };

        window.addEventListener('scroll', loadCaptcha, {
          once: true,
          passive: true,
        });
        window.addEventListener('touchstart', loadCaptcha, {
          once: true,
        });
        document.addEventListener('mouseenter', loadCaptcha, {
          once: true,
        });
        document.addEventListener('click', loadCaptcha, {
          once: true,
        });
      } else {
        (window as any).grecaptcha.ready(() => {
          if (this.element.checkValidity()) {
            this.enable();
          }
          this.captchaLoaded?.resolve();
        });
      }
    } else {
      if (this.element.checkValidity()) {
        this.enable();
      }
    }
  }

  handleFormSubmit = async (event: Event) => {
    event.preventDefault();

    if (!this.action.trim().length) {
      return;
    }

    if (this.isDisabled) {
      return;
    }

    this.disable();

    const data = new FormData(this.element);

    if (this.captchaKey) {
      const token = await (window as any).grecaptcha.execute(this.captchaKey, {
        action: 'submit',
      });
      data.set('g-recaptcha-response', token);
    }

    //! composite and deep cache fix
    if ((window as any).BX && (window as any).BX.bitrix_sessid) {
      data.set('sessid', (window as any).BX.bitrix_sessid());
    }

    let dispatchedEvent = new CustomEvent(EVENT_SUBMIT_BEFORE, {
      bubbles: true,
      cancelable: true,
      detail: {
        data,
        form: this.element,
      },
    });

    if (!this.element.dispatchEvent(dispatchedEvent)) {
      dispatchedEvent = new CustomEvent(EVENT_SUBMIT_AFTER, {
        bubbles: true,
        cancelable: false,
      });

      this.element.dispatchEvent(dispatchedEvent);

      if (this.element.checkValidity()) {
        this.enable();
      } else {
        this.disable();
      }

      return;
    }

    try {
      const response = await fetch(this.action, {
        method: 'POST',
        body: data,
      });

      if (!response.ok) {
        throw new Error(response.statusText);
      }

      if (response.redirected) {
        window.location.href = response.url;
        return;
      }

      const result: _BitrixResponse = await response.json();

      if (result.status === 'error') {
        const error = result.errors[0];

        if (error.code === FIELD_VALIDATION_ERROR_CODE) {
          const field = this.element.elements[error.customData as any] as
            | HTMLInputElement
            | HTMLTextAreaElement
            | HTMLSelectElement;

          if (field) {
            field.setCustomValidity(error.message);
            field.reportValidity();
            field.addEventListener(
              'input',
              () => {
                field.setCustomValidity('');
                field.reportValidity();
                field.checkValidity();
              },
              {
                once: true,
              }
            );
          }
        }

        if (error.code === GENERAL_ERROR_CODE) {
          templater.renderHTML({
            string: error.message,
            container: this.responseContainer,
            config: {
              replace: true,
            },
          });
        }

        throw new Error(error.message);
      }

      if (result.status === 'success') {
        templater.renderHTML({
          string: result.data,
          container: this.responseContainer,
          config: {
            replace: true,
          },
        });

        this.element.reset();
      }
    } catch (error) {
      console.error(error);
    } finally {
      dispatchedEvent = new CustomEvent(EVENT_SUBMIT_AFTER, {
        bubbles: true,
        cancelable: false,
      });

      this.element.dispatchEvent(dispatchedEvent);

      if (this.element.checkValidity()) {
        this.enable();
      } else {
        this.disable();
      }
    }
  };

  // v4
  handleFormInput = (event: Event) => {
    if (this.element.checkValidity()) {
      return this.enable();
    }

    this.disable();
  };

  // v4
  disable = () => {
    this.isDisabled = true;

    [...this.element.elements]
      .filter((element) => {
        return (
          (element instanceof HTMLButtonElement ||
            element instanceof HTMLInputElement) &&
          element.type === 'submit'
        );
      })
      .forEach((element) => {
        element.setAttribute('disabled', '');
      });
  };

  // v4
  enable = () => {
    this.isDisabled = false;

    [...this.element.elements]
      .filter((element) => {
        return (
          (element instanceof HTMLButtonElement ||
            element instanceof HTMLInputElement) &&
          element.type === 'submit'
        );
      })
      .forEach((element) => {
        element.removeAttribute('disabled');
      });
  };
}

export { Form as form, FormPropsType, FormConfigType };
