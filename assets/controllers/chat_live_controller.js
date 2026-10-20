import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['thread', 'form', 'submit'];

    static values = {
        subscribeUrl: String,
        mineType: String,
        mineLabel: String,
        theirsLabel: String,
        markReadUrlTemplate: String,
        markReadCsrf: String,
    };

    connect() {
        this.seenIds = new Set(
            [...this.threadTarget.querySelectorAll('[data-message-id]')].map((el) => el.dataset.messageId),
        );
        this.eventSource = null;
        this.sending = false;
        this.connectHub();
        document.addEventListener('visibilitychange', this.onVisibility);
    }

    disconnect() {
        this.closeHub();
        document.removeEventListener('visibilitychange', this.onVisibility);
    }

    onVisibility = () => {
        if (document.hidden) {
            this.closeHub();
            return;
        }
        this.connectHub();
    };

    connectHub() {
        if (this.eventSource || document.hidden || !this.subscribeUrlValue) {
            return;
        }

        this.eventSource = new EventSource(this.subscribeUrlValue, { withCredentials: true });
        this.eventSource.onmessage = (event) => this.onMessage(event);
        this.eventSource.onerror = () => {
            if (document.hidden) {
                this.closeHub();
            }
        };
    }

    closeHub() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    async send(event) {
        event.preventDefault();
        if (this.sending || !this.hasFormTarget) {
            return;
        }

        const form = this.formTarget;
        const bodyField = form.querySelector('[name$="[body]"]');
        if (bodyField && !String(bodyField.value || '').trim()) {
            form.reportValidity?.();
            return;
        }

        this.sending = true;
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (data?.message) {
                this.onMessage({ data: JSON.stringify(data.message) });
            }
            form.reset();
            bodyField?.focus();
        } catch {
            // Keep the typed message; user can retry.
        } finally {
            this.sending = false;
            if (this.hasSubmitTarget) {
                this.submitTarget.disabled = false;
            }
        }
    }

    onMessage(event) {
        let payload;
        try {
            payload = JSON.parse(event.data);
        } catch {
            return;
        }

        if (!payload || typeof payload.id === 'undefined' || typeof payload.body !== 'string') {
            return;
        }

        const id = String(payload.id);
        if (this.seenIds.has(id)) {
            return;
        }
        this.seenIds.add(id);

        const mine = payload.type === this.mineTypeValue;
        this.appendBubble(payload, mine);
        this.scrollToBottom();

        if (!mine) {
            this.markRead(id);
        }
    }

    markRead(messageId) {
        if (!this.markReadUrlTemplateValue || !this.markReadCsrfValue) {
            return;
        }

        const url = this.markReadUrlTemplateValue.replace('/0/read', `/${messageId}/read`);
        fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.markReadCsrfValue,
            },
            credentials: 'same-origin',
        }).catch(() => {
            // Email delay is a safety net if mark-read fails.
        });
    }

    appendBubble(payload, mine) {
        const empty = this.threadTarget.querySelector('.empty-state');
        if (empty) {
            empty.remove();
        }

        const article = document.createElement('article');
        article.className = `chat-bubble ${mine ? 'chat-bubble-mine' : 'chat-bubble-theirs'}`;
        article.dataset.messageId = String(payload.id);

        const meta = document.createElement('div');
        meta.className = 'chat-bubble-meta';
        meta.textContent = `${mine ? this.mineLabelValue : this.theirsLabelValue} · ${this.formatDate(payload.createdAt)}`;

        const body = document.createElement('div');
        body.className = 'chat-bubble-body';
        body.append(...this.bodyNodes(payload.body));

        article.append(meta, body);
        this.threadTarget.append(article);
    }

    bodyNodes(text) {
        const parts = String(text).split('\n');
        const nodes = [];
        parts.forEach((part, index) => {
            nodes.push(document.createTextNode(part));
            if (index < parts.length - 1) {
                nodes.push(document.createElement('br'));
            }
        });
        return nodes;
    }

    formatDate(iso) {
        if (!iso) {
            return '';
        }
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        const pad = (n) => String(n).padStart(2, '0');
        return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    scrollToBottom() {
        requestAnimationFrame(() => {
            const top = Math.max(
                document.body.scrollHeight,
                document.documentElement.scrollHeight,
            );
            window.scrollTo({ top, left: 0, behavior: 'smooth' });
        });
    }
}
