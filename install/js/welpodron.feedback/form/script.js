"use strict";
((window) => {
    if (window.welpodron &&
        window.welpodron.utils &&
        window.welpodron.templater) {
        if (window.welpodron.feedbackForm) {
            return;
        }
        const MODULE_BASE = "feedback";
        const EVENT_SUBMIT_BEFORE = `welpodron.${MODULE_BASE}:submit:before`;
        const EVENT_SUBMIT_AFTER = `welpodron.${MODULE_BASE}:submit:after`;
        const GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
        const FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
        class FeedbackForm {
            element;
            action = "";
            isDisabled = false;
            responseContainer;
            captchaLoaded = null;
            captchaKey = null;
            constructor({ element, config = {} }) {
                this.element = element;
                this.element.removeEventListener("input", this.handleFormInput);
                this.element.addEventListener("input", this.handleFormInput);
                this.element.removeEventListener("submit", this.handleFormSubmit);
                this.element.addEventListener("submit", this.handleFormSubmit);
                this.responseContainer = document.createElement("div");
                this.element.prepend(this.responseContainer);
                this.captchaKey = this.element.getAttribute("data-captcha");
                this.action = this.element.getAttribute("action") || "";
                // v4
                this.disable();
                if (this.captchaKey) {
                    this.captchaLoaded = window.welpodron.utils.deferred();
                    if (!window.grecaptcha) {
                        const loadCaptcha = () => {
                            if (document.querySelector(`script[src*="recaptcha"]`)) {
                                if (this.element.checkValidity()) {
                                    this.enable();
                                }
                                this.captchaLoaded?.resolve();
                                return;
                            }
                            const script = document.createElement("script");
                            script.src = `https://www.google.com/recaptcha/api.js?render=${this.captchaKey}`;
                            document.body.appendChild(script);
                            script.onload = () => {
                                window.grecaptcha.ready(() => {
                                    if (this.element.checkValidity()) {
                                        this.enable();
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
                    }
                    else {
                        window.grecaptcha.ready(() => {
                            if (this.element.checkValidity()) {
                                this.enable();
                            }
                            this.captchaLoaded?.resolve();
                        });
                    }
                }
                else {
                    if (this.element.checkValidity()) {
                        this.enable();
                    }
                }
            }
            handleFormSubmit = async (event) => {
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
                    const token = await window.grecaptcha.execute(this.captchaKey, {
                        action: "submit",
                    });
                    data.set("g-recaptcha-response", token);
                }
                //! composite and deep cache fix
                if (window.BX && window.BX.bitrix_sessid) {
                    data.set("sessid", window.BX.bitrix_sessid());
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
                    }
                    else {
                        this.disable();
                    }
                    return;
                }
                try {
                    const response = await fetch(this.action, {
                        method: "POST",
                        body: data,
                    });
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    const result = await response.json();
                    if (result.status === "error") {
                        const error = result.errors[0];
                        if (error.code === FIELD_VALIDATION_ERROR_CODE) {
                            const field = this.element.elements[error.customData];
                            if (field) {
                                field.setCustomValidity(error.message);
                                field.reportValidity();
                                field.addEventListener("input", () => {
                                    field.setCustomValidity("");
                                    field.reportValidity();
                                    field.checkValidity();
                                }, {
                                    once: true,
                                });
                            }
                        }
                        if (error.code === GENERAL_ERROR_CODE) {
                            window.welpodron.templater.renderHTML({
                                string: error.message,
                                container: this.responseContainer,
                                config: {
                                    replace: true,
                                },
                            });
                        }
                        throw new Error(error.message);
                    }
                    if (result.status === "success") {
                        window.welpodron.templater.renderHTML({
                            string: result.data,
                            container: this.responseContainer,
                            config: {
                                replace: true,
                            },
                        });
                        this.element.reset();
                    }
                }
                catch (error) {
                    console.error(error);
                }
                finally {
                    dispatchedEvent = new CustomEvent(EVENT_SUBMIT_AFTER, {
                        bubbles: true,
                        cancelable: false,
                    });
                    this.element.dispatchEvent(dispatchedEvent);
                    if (this.element.checkValidity()) {
                        this.enable();
                    }
                    else {
                        this.disable();
                    }
                }
            };
            // v4
            handleFormInput = (event) => {
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
                    return ((element instanceof HTMLButtonElement ||
                        element instanceof HTMLInputElement) &&
                        element.type === "submit");
                })
                    .forEach((element) => {
                    element.setAttribute("disabled", "");
                });
            };
            // v4
            enable = () => {
                this.isDisabled = false;
                [...this.element.elements]
                    .filter((element) => {
                    return ((element instanceof HTMLButtonElement ||
                        element instanceof HTMLInputElement) &&
                        element.type === "submit");
                })
                    .forEach((element) => {
                    element.removeAttribute("disabled");
                });
            };
        }
        window.welpodron.feedbackForm = FeedbackForm;
    }
})(window);
//# sourceMappingURL=script.js.map