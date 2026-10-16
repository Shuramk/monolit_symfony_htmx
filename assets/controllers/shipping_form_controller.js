import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['quantity', 'totalWeight', 'pickup', 'delivery', 'storage'];

    connect() {
        this.togglePickup();
        this.recalculate();
    }

    togglePickup() {
        this.deliveryTarget.hidden = this.pickupTarget.checked;
        this.deliveryTarget.disabled = this.pickupTarget.checked;
    }

    recalculate() {
        let total = 0;
        for (const input of this.quantityTargets) {
            let quantity = Number(input.value.replace(',', '.'));
            const max = Number(input.max);
            if (!Number.isFinite(quantity) || quantity < 0) quantity = 0;
            if (Number.isFinite(max) && quantity > max) {
                quantity = max;
                input.value = String(max);
            }
            const weight = quantity * Number(input.dataset.weight || '0') / 1000;
            total += weight;
            input.closest('tr').querySelector('[data-row-weight]').textContent = weight.toFixed(3) + 'т';
            const free = Number(this.storageTarget.value === '1' ? input.dataset.freeOffice : input.dataset.freeWorkshop);
            input.classList.toggle('shipping-shortage', quantity > 0 && quantity > free);
            input.classList.toggle('shipping-available', quantity > 0 && quantity <= free);
        }
        this.totalWeightTarget.textContent = total.toFixed(3) + 'т';
    }
}
