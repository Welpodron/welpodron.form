"use strict";
((window) => {
    //!Original: https://github.com/nosir/cleave.js Max Huang https://github.com/nosir/
    if (!window.welpodron) {
        window.welpodron = {};
    }
    if (!window.welpodron.form) {
        window.welpodron.form = {};
    }
    if (window.welpodron.form["input-tel"]) {
        return;
    }
    const DELIMITERS = [" (", ") ", "-", "-"];
    const BLOCKS = [1, 3, 3, 2, 2];
    const isAndroid = navigator && /android/i.test(navigator.userAgent);
    const stripDelimiters = (value) => {
        DELIMITERS.forEach((delimiter) => {
            value = value.replaceAll(delimiter, "");
        });
        return value;
    };
    const getPostDelimiter = (value) => {
        let matchedDelimiter = "";
        DELIMITERS.forEach((current) => {
            if (value.slice(-current.length) === current) {
                matchedDelimiter = current;
            }
        });
        return matchedDelimiter;
    };
    const getPositionOffset = (prevPos, oldValue, newValue) => {
        let oldRawValue, newRawValue, lengthOffset;
        oldRawValue = stripDelimiters(oldValue.slice(0, prevPos));
        newRawValue = stripDelimiters(newValue.slice(0, prevPos));
        lengthOffset = oldRawValue.length - newRawValue.length;
        return lengthOffset !== 0 ? lengthOffset / Math.abs(lengthOffset) : 0;
    };
    const getFormattedValue = (value) => {
        let result = "";
        BLOCKS.forEach((length, index) => {
            if (value.length > 0) {
                let substring = value.slice(0, length), rest = value.slice(length);
                result += substring;
                if (substring.length === length && index < BLOCKS.length - 1) {
                    result += DELIMITERS[index];
                }
                value = rest;
            }
        });
        return result;
    };
    const getNextCursorPosition = (prevPos, oldValue, newValue) => {
        if (oldValue.length === prevPos) {
            return newValue.length;
        }
        return prevPos + getPositionOffset(prevPos, oldValue, newValue);
    };
    const setSelection = (element, position) => {
        if (element !== document.activeElement) {
            return;
        }
        if (element && element.value.length <= position) {
            return;
        }
        element.setSelectionRange(position, position);
    };
    class Input {
        element;
        result = "";
        lastInputValue = "";
        isBackward = false;
        postDelimiterBackspace = "";
        constructor({ element, config = {} }) {
            this.element = element;
            this.element.addEventListener("focus", this.handleFocus);
            this.element.addEventListener("keydown", this.handleKeyDown);
            this.element.addEventListener("input", this.handleInput);
            if (element.value) {
                this.changeValue(element.value);
            }
        }
        handleKeyDown = (event) => {
            this.lastInputValue = this.element.value;
            this.isBackward = event.key === "Backspace";
        };
        handleFocus = () => {
            this.lastInputValue = this.element.value;
        };
        handleInput = () => {
            const postDelimiter = getPostDelimiter(this.lastInputValue);
            if (this.isBackward && postDelimiter) {
                this.postDelimiterBackspace = postDelimiter;
            }
            else {
                this.postDelimiterBackspace = "";
            }
            this.changeValue(this.element.value);
        };
        changeValue = (value) => {
            const postDelimiterAfter = getPostDelimiter(value);
            if (this.postDelimiterBackspace && !postDelimiterAfter) {
                value = value.slice(0, value.length - this.postDelimiterBackspace.length);
            }
            // strip existing delimiters from value
            value = stripDelimiters(value);
            // strip non-numeric characters
            value = value.replace(/[^\d]/g, "");
            // strip over length characters
            value = value.slice(0, 11);
            // apply blocks
            this.result = getFormattedValue(value);
            let endPos = this.element.selectionEnd;
            endPos = getNextCursorPosition(endPos, this.element.value, this.result);
            if (isAndroid) {
                return window.setTimeout(() => {
                    this.element.value = this.result;
                    setSelection(this.element, endPos);
                }, 1);
            }
            this.element.value = this.result;
            setSelection(this.element, endPos);
        };
    }
    window.welpodron.form["input-tel"] = Input;
})(window);
//# sourceMappingURL=script.js.map