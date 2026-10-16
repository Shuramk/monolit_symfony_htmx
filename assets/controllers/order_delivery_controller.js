import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['pickup', 'delivery', 'price', 'trips', 'cost', 'total', 'withMarkup'];
    static values = { markup: Number };

    connect() {
        this.toggle();
    }

    toggle() {
        this.deliveryTarget.hidden = this.pickupTarget.checked;
        this.deliveryTarget.disabled = this.pickupTarget.checked;
        this.recalculate();
    }

    recalculate() {
        const price = Math.round(Number(this.priceTarget.value || '0') * 100);
        const trips = Number(this.tripsTarget.value || '0');
        const cents = !this.pickupTarget.checked && Number.isFinite(price) && Number.isInteger(trips) && price >= 0 && trips >= 0
            ? price * trips : 0;
        const total = (cents / 100).toFixed(2);
        this.costTarget.value = total;
        this.totalTarget.textContent = total;
        if (this.hasWithMarkupTarget) this.withMarkupTarget.textContent = (Math.round(cents * (100 + this.markupValue) / 100) / 100).toFixed(2);
    }
}
