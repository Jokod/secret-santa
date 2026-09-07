import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        text: String,
    };

    static targets = ['icon'];

    async copy() {
        const text = this.textValue;
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            this.flashSuccess();
        } catch {
            this.fallbackCopy(text);
            this.flashSuccess();
        }
    }

    fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

    flashSuccess() {
        if (!this.hasIconTarget) {
            return;
        }

        const icon = this.iconTarget;
        const previous = icon.className;
        icon.className = 'fa-solid fa-check';
        this.element.setAttribute('title', 'Copié !');

        window.clearTimeout(this._resetTimer);
        this._resetTimer = window.setTimeout(() => {
            icon.className = previous;
            this.element.setAttribute('title', 'Copier le lien');
        }, 1600);
    }
}
