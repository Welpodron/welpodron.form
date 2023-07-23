(() => {
  type FormConfigType = {};

  type FormPropsType = {
    element: HTMLFormElement;
    config?: FormConfigType;
  };

  type _BitrixResponce = {
    data: any;
    status: "success" | "error";
    errors: {
      code: string;
      message: string;
      customData: string;
    }[];
  };

  //! TODO: v5 Добавить события

  class Form {
    element: HTMLFormElement;

    action: string = "";
    disabled: boolean = false;

    errorContainer: HTMLElement;
    successContainer: HTMLElement;

    captchaLoaded:
      | (Promise<any> & {
          resolve: () => void;
        })
      | null = null;
    captchaKey: string | null = null;

    constructor({ element, config = {} }: FormPropsType) {
      this.element = element;

      this.element.removeEventListener("input", this.#handleInput);
      this.element.addEventListener("input", this.#handleInput);

      this.element.removeEventListener("submit", this.#handleSubmit);
      this.element.addEventListener("submit", this.#handleSubmit);

      this.errorContainer = document.createElement("div");
      this.element.prepend(this.errorContainer);
      this.successContainer = document.createElement("div");
      this.element.prepend(this.successContainer);

      this.captchaKey = this.element.getAttribute("data-captcha");
      this.action = this.element.getAttribute("action") || "";

      if (this.captchaKey) {
        this.#disable();

        this.captchaLoaded = this.#getDefferedPromise<void>();

        if (!(window as any).grecaptcha) {
          const loadCaptcha = () => {
            if (document.querySelector(`script[src*="recaptcha"]`)) {
              if (this.element.checkValidity()) {
                this.#enable();
              }
              this.captchaLoaded?.resolve();
              return;
            }
            const script = document.createElement("script");
            script.src = `https://www.google.com/recaptcha/api.js?render=${this.captchaKey}`;
            document.body.appendChild(script);
            script.onload = () => {
              ((window as any).grecaptcha as any).ready(() => {
                if (this.element.checkValidity()) {
                  this.#enable();
                }
                this.captchaLoaded?.resolve();
              });
            };
          };

          window.addEventListener("scroll", loadCaptcha, {
            once: true,
            passive: true,
          });
          window.addEventListener("touchstart", loadCaptcha, {
            once: true,
          });
          document.addEventListener("mouseenter", loadCaptcha, {
            once: true,
          });
          document.addEventListener("click", loadCaptcha, {
            once: true,
          });
        } else {
          ((window as any).grecaptcha as any).ready(() => {
            if (this.element.checkValidity()) {
              this.#enable();
            }
            this.captchaLoaded?.resolve();
          });
        }
      }

      // v4
      this.#disable();

      if (this.element.checkValidity()) {
        this.#enable();
      }
    }

    #getDefferedPromise = <T = unknown>(): Promise<T> & {
      resolve: (value: T | PromiseLike<T>) => void;
    } => {
      let resolver, promise;
      promise = new Promise<T>((resolve, reject) => {
        resolver = resolve;
      });
      (
        promise as Promise<T> & { resolve: (value: T | PromiseLike<T>) => void }
      ).resolve = resolver as unknown as (value: T | PromiseLike<T>) => void;
      return promise as Promise<T> & {
        resolve: (value: T | PromiseLike<T>) => void;
      };
    };

    #isStringHTML = (string: string) => {
      const doc = new DOMParser().parseFromString(string, "text/html");
      return [...doc.body.childNodes].some((node) => node.nodeType === 1);
    };

    #renderString = ({
      string,
      container,
      config,
    }: {
      string: string;
      container: HTMLElement;
      config: {
        replace?: boolean;
      };
    }) => {
      const replace = config.replace;
      const templateElement = document.createElement("template");
      templateElement.innerHTML = string;
      const fragment = templateElement.content;
      fragment.querySelectorAll("script").forEach((scriptTag) => {
        const scriptParentNode = scriptTag.parentNode;
        scriptParentNode?.removeChild(scriptTag);
        const script = document.createElement("script");
        script.text = scriptTag.text;
        scriptParentNode?.append(script);
      });
      if (replace) {
        return container.replaceChildren(fragment);
      }

      return container.appendChild(fragment);
    };

    #handleSubmit = async (evt: Event) => {
      evt.preventDefault();

      if (!this.action.trim().length) return;

      if (this.disabled) return;

      this.#disable();

      const data = new FormData(this.element);

      if (this.captchaKey) {
        const token = await ((window as any).grecaptcha as any).execute(
          this.captchaKey,
          { action: "submit" }
        );
        data.set("g-recaptcha-response", token);
      }

      const loaderSvgInline =
        '<svg style="margin:auto;" xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 38 38" stroke="currentColor"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".5" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg>';

      const submitBtn = [...this.element.elements].find((el) => {
        return (
          (el instanceof HTMLButtonElement || el instanceof HTMLInputElement) &&
          el.type === "submit"
        );
      });

      let submitBtnContentBefore = "";

      if (submitBtn) {
        submitBtnContentBefore = submitBtn.innerHTML;
        submitBtn.innerHTML = loaderSvgInline;
      }

      try {
        const response = await fetch(this.action, {
          method: "POST",
          body: data,
        });

        if (!response.ok) {
          this.#enable();
          console.error(response);
          return;
        }

        const result: _BitrixResponce = await response.json();

        if (result.status === "error") {
          const error = result.errors[0];

          if (error.code === "FIELD_VALIDATION_ERROR") {
            const field = this.element.elements[error.customData as any] as
              | HTMLInputElement
              | HTMLTextAreaElement
              | HTMLSelectElement;

            if (field) {
              field.setCustomValidity(error.message);
              field.reportValidity();
              field.addEventListener(
                "input",
                () => {
                  field.setCustomValidity("");
                  field.reportValidity();
                  field.checkValidity();
                },
                {
                  once: true,
                }
              );
            }
          }

          if (error.code === "FORM_GENERAL_ERROR") {
            if (this.#isStringHTML(error.message)) {
              this.#renderString({
                string: error.message,
                container: this.errorContainer,
                config: {
                  replace: true,
                },
              });
            }
          }

          this.#enable();
          console.error(error);
          return;
        }

        if (result.status === "success") {
          if (this.#isStringHTML(result.data)) {
            this.#renderString({
              string: result.data,
              container: this.successContainer,
              config: {
                replace: true,
              },
            });
          }

          this.element.reset();
          if (this.element.checkValidity()) {
            this.#enable();
          } else {
            this.#disable();
          }
        }
      } catch (error) {
        console.error(error);
      } finally {
        if (submitBtn) {
          submitBtn.innerHTML = submitBtnContentBefore;
        }

        if (this.element.checkValidity()) {
          this.#enable();
        } else {
          this.#disable();
        }
      }
    };

    // v4
    #handleInput = (evt: Event) => {
      if (this.element.checkValidity()) {
        return this.#enable();
      }

      this.#disable();
    };

    // v4
    #disable = () => {
      this.disabled = true;

      [...this.element.elements]
        .filter((el) => {
          return (
            (el instanceof HTMLButtonElement ||
              el instanceof HTMLInputElement) &&
            el.type === "submit"
          );
        })
        .forEach((el) => {
          el.setAttribute("disabled", "disabled");
        });
    };

    // v4
    #enable = () => {
      this.disabled = false;

      [...this.element.elements]
        .filter((el) => {
          return (
            (el instanceof HTMLButtonElement ||
              el instanceof HTMLInputElement) &&
            el.type === "submit"
          );
        })
        .forEach((el) => {
          el.removeAttribute("disabled");
        });
    };
  }

  if ((window as any).welpodron == null) {
    (window as any).welpodron = {};
  }

  (window as any).welpodron.form = Form;

  //! v4 ВНИМАНИЕ УБРАНО АВТОПОДКЛЮЧЕНИЕ ТЕПЕРЬ НУЖНО ПОДКЛЮЧАТЬ ВРУЧНУЮ

  // document.addEventListener(
  //   "DOMContentLoaded",
  //   () => {
  //     document.querySelectorAll("form[data-form]").forEach((element) => {
  //       new Form({ element: element as HTMLFormElement });
  //     });
  //   },
  //   {
  //     once: true,
  //   }
  // );
})();
