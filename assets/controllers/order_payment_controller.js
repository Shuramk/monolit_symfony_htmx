import { Controller } from '@hotwired/stimulus';
import { paymentPayload } from '../lib/order_payment.js';
import { warehouseRequest } from '../lib/warehouse.js';
import { validationMessage } from '../lib/json_form.js';

export default class extends Controller {
    static targets = ['dialog', 'error'];
    static values = { csrf: String };

    open() {
        if (this.submitting) return;
        const form = this.dialogTarget.querySelector('form');
        if (!form.elements.operationId.value) form.elements.operationId.value = crypto.randomUUID();
        this.errorTarget.hidden = true;
        this.dialogTarget.showModal();
        form.elements.amount.focus();
    }
    close(event) {
        if (this.submitting) { event?.preventDefault(); return; }
        this.dialogTarget.close();
    }
    invalid(event) {
        event.target.setCustomValidity('');
        event.target.setCustomValidity(validationMessage(event.target.validity, event.target));
    }
    clearValidity(event) { event.target.setCustomValidity(''); }
    async submit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (this.submitting || !form.reportValidity()) return;
        const buttons = [...form.querySelectorAll('button')];
        try {
            const payload = paymentPayload(form.elements.amount.value, form.elements.toCard?.checked, form.elements.operationId.value);
            this.submitting = true;
            this.errorTarget.hidden = true;
            buttons.forEach(button => { button.disabled = true; });
            form.setAttribute('aria-busy','true');
            const result = await warehouseRequest(form.action, payload, this.csrfValue);
            if (typeof result.location !== 'string' || result.location === '') throw new Error('Сервер не вернул адрес заказа.');
            const url = new URL(result.location, window.location.href);
            if (url.origin !== window.location.origin) throw new Error('Некорректный адрес перехода.');
            window.location.assign(url.href);
        } catch (error) {
            this.errorTarget.textContent = error instanceof TypeError ? 'Не удалось связаться с сервером. Повторите отправку.' : error.message;
            this.errorTarget.hidden = false;
            this.submitting = false;
            buttons.forEach(button => { button.disabled = false; });
            form.removeAttribute('aria-busy');
        }
    }
}
