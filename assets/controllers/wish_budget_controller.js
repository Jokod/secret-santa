import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        budgetMax: Number,
    };

    static targets = ['price', 'icon'];

    connect() {
        this.sync();
    }

    sync() {
        const over = this.isOverBudget();
        if (this.hasIconTarget) {
            this.iconTarget.hidden = !over;
        }
    }

    confirmIfOverBudget(event) {
        if (!this.isOverBudget()) {
            return;
        }

        const budget = this.formatAmount(this.budgetMaxValue);
        const ok = window.confirm(
            `Ce souhait dépasse le budget de ${budget} €. Continuer quand même ?`
        );
        if (!ok) {
            event.preventDefault();
        }
    }

    isOverBudget() {
        if (!this.hasPriceTarget) {
            return false;
        }

        const raw = this.priceTarget.value.trim().replace(',', '.');
        if (raw === '') {
            return false;
        }

        const price = Number.parseFloat(raw);
        if (Number.isNaN(price)) {
            return false;
        }

        return price > this.budgetMaxValue;
    }

    formatAmount(amount) {
        const fixed = Number(amount).toFixed(2);
        return fixed.replace(/\.?0+$/, '') || '0';
    }
}
