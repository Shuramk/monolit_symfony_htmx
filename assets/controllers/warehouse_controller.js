import { Controller } from '@hotwired/stimulus';
import { warehouseRequest, minimumPayload, selectedProduction } from '../lib/warehouse.js';
import { validationMessage } from '../lib/json_form.js';

export default class extends Controller {
    static targets = ['popover', 'stockDialog', 'needDialog', 'notice'];
    static values = { csrf: String };

    connect() {
        this.element.querySelectorAll('[name=operationId]').forEach(field => { if (!field.value) field.value = crypto.randomUUID(); });
        this.element.querySelectorAll('[data-warehouse-minimum]').forEach(field => { field.dataset.saved = field.value; });
        const key = 'warehouse-warnings:' + window.location.pathname;
        const message = sessionStorage.getItem(key);
        if (message && this.hasNoticeTarget) {
            this.noticeTarget.textContent = message;
            this.noticeTarget.hidden = false;
            sessionStorage.removeItem(key);
        }
    }
    invalid(event) {
        event.target.setCustomValidity('');
        event.target.setCustomValidity(validationMessage(event.target.validity,event.target));
    }
    clearValidity(event) {
        if (typeof event.target.setCustomValidity === 'function') event.target.setCustomValidity('');
    }
    openStock(event) {
        const button = event.currentTarget;
        const dialog = this.stockDialogTarget;
        const form = dialog.querySelector('form');
        form.reset();
        form.action = button.dataset.url;
        form.querySelector('[name=operationId]').value = crypto.randomUUID();
        dialog.querySelector('[data-stock-title]').textContent = (button.dataset.add === '1' ? 'Добавить. ' : 'Списать. ') + button.dataset.name;
        dialog.querySelector('[data-stock-unit]').textContent = button.dataset.unit;
        const production = dialog.querySelector('[data-stock-production]');
        production.hidden = button.dataset.add !== '1';
        production.disabled = production.hidden;
        dialog.querySelector('[data-stock-submit]').textContent = button.dataset.add === '1' ? 'Добавить' : 'Списать';
        form.querySelector('.warehouse-form-error').hidden = true;
        dialog.showModal();
    }
    closeDialog(event) {
        const dialog = event.currentTarget.closest('dialog');
        if (!dialog.querySelector('[aria-busy=true]')) dialog.close();
    }
    async openNeed() {
        const form = this.needDialogTarget.querySelector('form');
        form.dataset.needReady = '0';
        const buttons = Array.from(form.querySelectorAll('button'));
        buttons.forEach(button => { button.disabled = true; });
        form.querySelector('.warehouse-form-error').hidden = true;
        this.needDialogTarget.showModal();
        try {
            await Promise.allSettled(this.minimumRequests || []);
            const response = await fetch(form.dataset.needUrl, {credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok || response.redirected || !response.headers.get('Content-Type')?.includes('json')) throw new Error('Не удалось обновить потребность. Обновите страницу.');
            const data = await response.json();
            data.products.forEach(product => {
                const checkbox = form.querySelector('[data-need-choice][value="' + product.productId + '"]');
                if (!checkbox) return;
                checkbox.dataset.quantity = product.quantity;
                checkbox.closest('tr').querySelector('td:nth-child(2)').textContent = product.quantity + ' ' + product.unit;
            });
            form.dataset.needReady = '1';
            buttons.forEach(button => { button.disabled = false; });
        } catch (error) { this.showError(form,error.message); }
    }
    rememberMinimum(event) {
        const field = event.currentTarget;
        if (field.dataset.saved === undefined) field.dataset.saved = field.value;
    }
    async saveMinimum(event) {
        event.preventDefault();
        const field = event.currentTarget;
        if (field.disabled || field.value === field.dataset.saved) return;
        try {
            const payload = minimumPayload(field.value, field.dataset.version);
            field.disabled = true;
            const request = warehouseRequest(field.dataset.url, payload, this.csrfValue);
            this.minimumRequests = [...(this.minimumRequests || []),request];
            const result = await request;
            field.value = result.quantity;
            field.dataset.saved = result.quantity;
            field.dataset.version = result.version;
            field.classList.remove('warehouse-field-error');
            field.title = '';
        } catch (error) {
            field.classList.add('warehouse-field-error');
            field.title = error.message;
            if (this.hasNoticeTarget) { this.noticeTarget.textContent = error.message; this.noticeTarget.hidden = false; }
        } finally { field.disabled = false; }
    }
    async submitStock(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const payload = Object.fromEntries(new FormData(form));
        if ('shift' in payload) payload.shift = payload.shift === '' ? null : Number(payload.shift);
        if ('storage' in payload) payload.storage = Number(payload.storage);
        if ('date' in payload && payload.date === '') payload.date = null;
        await this.send(form, payload);
    }
    async submitOperation(event) {
        event.preventDefault();
        const form = event.currentTarget;
        await this.send(form, Object.fromEntries(new FormData(form)));
    }
    async createTasks(event) {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            if (form.dataset.needReady !== '1') throw new Error('Потребность ещё не обновлена. Закройте окно и попробуйте снова.');
            const lines = selectedProduction(form.querySelectorAll('[data-need-choice]'));
            if (lines.some(line => Number(line.quantity) <= 0)) throw new Error('Для выбранной продукции нет потребности в производстве.');
            await this.send(form, { ...Object.fromEntries(new FormData(form)), lines });
        } catch (error) { this.showError(form,error.message); }
    }
    printNeed(event) {
        const form = event.currentTarget.closest('form');
        try {
            if (form.dataset.needReady !== '1') throw new Error('Потребность ещё не обновлена.');
            const lines = selectedProduction(form.querySelectorAll('[data-need-choice]'));
            const url = new URL(form.dataset.printUrl, window.location.href);
            lines.forEach(line => url.searchParams.append('products[]', line.productId));
            window.open(url.href, '_blank', 'noopener');
        } catch (error) { this.showError(form,error.message); }
    }
    async closeCover(event) {
        if (!window.confirm('Закрыть переложку без внесения на склад?')) return;
        await this.send(event.currentTarget.closest('form'), {}, event.currentTarget.dataset.url, false);
    }
    showError(form,message) {
        const field = form.querySelector('.warehouse-form-error');
        field.textContent = message;
        field.hidden = false;
    }
    async send(form,payload,url=form.action,validate=true) {
        if (form.getAttribute('aria-busy') === 'true') return;
        if (validate && !form.reportValidity()) return;
        form.setAttribute('aria-busy','true');
        const buttons = Array.from(form.querySelectorAll('button'));
        buttons.forEach(button => { button.disabled = true; });
        form.querySelector('.warehouse-form-error').hidden = true;
        try {
            const result = await warehouseRequest(url,payload,this.csrfValue);
            if (result.warnings?.length) sessionStorage.setItem('warehouse-warnings:' + window.location.pathname,result.warnings.join('\n'));
            if (result.location) {
                const target = new URL(result.location,window.location.href);
                if (target.origin !== window.location.origin) throw new Error('Некорректный адрес перехода.');
                window.location.assign(target.href);
            } else window.location.reload();
        } catch (error) { this.showError(form,error.message); }
        finally {
            form.removeAttribute('aria-busy');
            buttons.forEach(button => { button.disabled = false; });
        }
    }
    toggle(event) {
        const button = event.currentTarget;
        const popover = button.parentElement.querySelector('[data-warehouse-target=popover]');
        const open = popover.hidden;
        this.closeAll();
        if (!open) return;
        popover.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        const rect = button.getBoundingClientRect();
        popover.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - popover.offsetWidth - 8))}px`;
        popover.style.top = `${rect.bottom + popover.offsetHeight + 14 < window.innerHeight ? rect.bottom + 6 : Math.max(8, rect.top - popover.offsetHeight - 6)}px`;
    }
    closeAll() {
        this.popoverTargets.forEach(popover => {
            popover.hidden = true;
            popover.parentElement.querySelector('[data-warehouse-stock-button]').setAttribute('aria-expanded', 'false');
        });
    }
    outside(event) {
        if (!event.target.closest('[data-warehouse-stock-button], [data-warehouse-target=popover]')) this.closeAll();
    }
    print() {
        window.print();
    }
}
