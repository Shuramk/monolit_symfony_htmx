import { Controller } from '@hotwired/stimulus';
import { postJson, validationMessage } from '../lib/json_form.js';
import { adjustPrices } from '../lib/product_prices.js';

export default class extends Controller {
    static targets = ['row', 'price', 'delta', 'mode', 'reset', 'message'];

    connect() {
        this.original = new Map(this.rowTargets.map(row => [row, JSON.stringify(this.rowData(row))]));
        this.refreshState();
        this.unload = event => {
            if (this.rowTargets.some(row => this.isChanged(row))) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this.unload);
    }

    disconnect() {
        window.removeEventListener('beforeunload', this.unload);
    }

    rowData(row) {
        return Object.fromEntries(Array.from(row.querySelectorAll('[data-product-field]'), field => [field.dataset.productField, field.value]));
    }

    isChanged(row) {
        return this.original.get(row) !== JSON.stringify(this.rowData(row));
    }

    changed(event) {
        event.target.setCustomValidity?.('');
        this.refreshState();
        this.messageTarget.textContent = this.rowTargets.some(row => this.isChanged(row))
            ? 'Изменения ещё не сохранены.' : 'Нет несохранённых изменений.';
        this.messageTarget.classList.remove('is-error');
    }

    refreshState() {
        this.rowTargets.forEach(row => row.classList.toggle('product-row-dirty', this.isChanged(row)));
        if (this.hasResetTarget) {
            this.resetTarget.disabled = this.saving || !this.rowTargets.some(row => {
                const price = row.querySelector('[data-product-field=price]');
                return price && price.value !== JSON.parse(this.original.get(row)).price;
            });
        }
    }

    resetPrices() {
        if (this.saving) return;
        this.rowTargets.forEach(row => {
            const price = row.querySelector('[data-product-field=price]');
            if (price) {
                price.value = JSON.parse(this.original.get(row)).price;
                price.setCustomValidity?.('');
            }
        });
        this.changed({ target: this.resetTarget });
        this.messageTarget.textContent = this.rowTargets.some(row => this.isChanged(row))
            ? 'Цены восстановлены. Остальные изменения ещё не сохранены.' : 'Цены восстановлены. Несохранённых изменений нет.';
    }

    invalid(event) {
        event.target.setCustomValidity('');
        event.target.setCustomValidity(validationMessage(event.target.validity, event.target));
    }

    adjust() {
        try {
            const prices = adjustPrices(this.priceTargets.map(field => field.value), this.deltaTarget.value, this.modeTarget.value);
            this.priceTargets.forEach((field, index) => { field.value = prices[index]; });
            this.changed({ target: this.deltaTarget });
        } catch (error) {
            this.error(error);
        }
    }

    error(error) {
        this.messageTarget.textContent = error instanceof TypeError ? 'Не удалось связаться с сервером. Повторите сохранение.' : error.message;
        this.messageTarget.classList.add('is-error');
    }

    async submit(event) {
        event.preventDefault();
        if (this.saving || !this.element.reportValidity()) return;
        const rows = this.rowTargets.filter(row => this.isChanged(row));
        if (!rows.length) {
            this.messageTarget.textContent = 'Нет изменений для сохранения.';
            return;
        }
        const payload = { rows: rows.map(row => this.rowData(row)) };
        const token = this.element.querySelector('[name=_token]').value;
        const controls = Array.from(this.element.querySelectorAll('input, select, button')).filter(field => !field.disabled);
        this.saving = true;
        controls.forEach(field => { field.disabled = true; });
        this.element.setAttribute('aria-busy', 'true');
        try {
            const data = await postJson(this.element.action, payload, token);
            if (!data.versions && data.location) {
                const destination = new URL(data.location, window.location.href);
                if (destination.origin !== window.location.origin) throw new Error('Некорректный адрес перехода.');
                // Preserve the unsaved warning when the session has expired.
                window.location.assign(destination.href);
                return;
            }
            if (!data.versions || rows.some(row => data.versions[this.rowData(row).id] === undefined)) throw new Error('Сервер не подтвердил сохранение всех строк.');
            rows.forEach(row => {
                const version = row.querySelector('[data-product-field=version]');
                version.value = data.versions[this.rowData(row).id];
                this.original.set(row, JSON.stringify(this.rowData(row)));
                row.classList.remove('product-row-dirty');
            });
            this.messageTarget.textContent = `Сохранено строк: ${rows.length}.`;
            this.messageTarget.classList.remove('is-error');
        } catch (error) {
            this.error(error);
        } finally {
            controls.forEach(field => { field.disabled = false; });
            this.saving = false;
            this.element.removeAttribute('aria-busy');
            this.refreshState();
        }
    }
}
