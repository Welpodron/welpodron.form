(() => {
  if (!window.welpodron) {
    window.welpodron = {};
  }

  if (window.welpodron.agreement) {
    return;
  }

  class Agreement {
    constructor({ element }) {
      this.element = element;

      this.successContainer = document.createElement("div");
      this.element.parentNode.append(this.successContainer);

      this.element.removeEventListener("change", this.#handleChange);
      this.element.addEventListener("change", this.#handleChange);
    }

    #handleChange = async (event) => {
      if (!this.element.checked) {
        this.loading = false;
        return;
      }

      this.element.checked = false;

      const { agreementId, agreementToken, agreementParams } =
        this.element.dataset;

      const data = new FormData();

      data.append("id", agreementId);
      data.append("sessid", agreementToken);
      data.append("params", agreementParams);

      if (this.loading) {
        return;
      }

      this.loading = true;

      const _loaderSvgInline =
        '<svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 38 38" stroke="#fff"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".5" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg>';

      const _loader = document.createElement("dialog");
      _loader.innerHTML = _loaderSvgInline;
      _loader.oncancel = (event) => event.preventDefault();
      _loader.inert = true;
      _loader.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,.8);
        min-height: 100%;
        min-width: 100%;
        z-index: 999999;
        display: grid;
        place-items: center;
        place-content: center;
      `;
      document.body.append(_loader);
      _loader.showModal();

      const response = await fetch(
        "/bitrix/services/main/ajax.php?c=welpodron:feedback.agreement.request&mode=class&action=get",
        {
          method: "POST",
          body: data,
        }
      );

      if (!response.ok) {
        console.error(response);
        this.loading = false;
        _loader.remove();
        return;
      }

      const result = await response.json();

      if (result.status === "error") {
        console.error(result);
        this.loading = false;
        _loader.remove();
        return;
      }

      _loader.remove();

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
      }

      this.loading = false;
    };

    #isStringHTML = (string) => {
      const doc = new DOMParser().parseFromString(string, "text/html");

      return [...doc.body.childNodes].some((node) => node.nodeType === 1);
    };

    #renderString = ({ string, container, config = {} }) => {
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
  }

  window.welpodron.agreement = Agreement;

  document.addEventListener(
    "DOMContentLoaded",
    () => {
      document.querySelectorAll("[data-agreement-id]").forEach((element) => {
        new Agreement({
          element,
        });
      });
    },
    {
      once: true,
    }
  );
})();
