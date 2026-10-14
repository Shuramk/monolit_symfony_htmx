import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['customerSelect', 'customerName', 'customerPhone', 'stackerSelect', 'stackerName', 'stackerPhone', 'contactDate', 'contactTime', 'contactAt'];

    connect() {
        if (this.hasCustomerSelectTarget) this.customer();
        if (this.hasStackerSelectTarget) this.stacker();
        if (this.hasContactAtTarget) this.syncContactAt();
    }

    customer() {
        const option = this.customerSelectTarget.selectedOptions[0];
        this.customerNameTarget.textContent = option?.dataset.name ?? '';
        this.customerPhoneTarget.textContent = option?.dataset.phone ?? '';
        this.customerSelectTarget.closest('details').open = false;
    }

    stacker() {
        const option = this.stackerSelectTarget.selectedOptions[0];
        this.stackerNameTarget.textContent = option?.dataset.name ?? '';
        this.stackerPhoneTarget.textContent = option?.dataset.phone ?? '';
        this.stackerSelectTarget.closest('details').open = false;
    }

    syncContactAt() {
        const date = this.contactDateTarget.value;
        const time = this.contactTimeTarget.value;
        this.contactAtTarget.value = date && time ? `${date}T${time}` : '';
    }

    reveal(event) {
        let details = event.target.closest('details');
        while (details) {
            details.open = true;
            details = details.parentElement?.closest('details');
        }
    }

}
