import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['button', 'panel'];

    connect() {
        this.onKeydown = this.onKeydown.bind(this);
        this.onPointerDown = this.onPointerDown.bind(this);
        this.onNavigate = this.close.bind(this);
        this.close();
        this.element.addEventListener('click', this.onLinkClick);
        document.addEventListener('turbo:before-cache', this.onNavigate);
    }

    disconnect() {
        this.element.removeEventListener('click', this.onLinkClick);
        document.removeEventListener('turbo:before-cache', this.onNavigate);
        document.removeEventListener('keydown', this.onKeydown);
        document.removeEventListener('pointerdown', this.onPointerDown);
        this.close();
    }

    toggle(event) {
        event?.preventDefault();
        if (this.element.classList.contains('is-nav-open')) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.element.classList.add('is-nav-open');
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }
        document.addEventListener('keydown', this.onKeydown);
        document.addEventListener('pointerdown', this.onPointerDown);
    }

    close() {
        this.element.classList.remove('is-nav-open');
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        }
        document.removeEventListener('keydown', this.onKeydown);
        document.removeEventListener('pointerdown', this.onPointerDown);
    }

    onLinkClick = (event) => {
        const link = event.target.closest('a');
        if (link && this.panelTarget?.contains(link)) {
            this.close();
        }
    };

    onKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
            this.buttonTarget?.focus();
        }
    }

    onPointerDown(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }
}
