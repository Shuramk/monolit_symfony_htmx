import { Controller } from '@hotwired/stimulus';
import { postJson, serializeForm } from '../lib/json_form.js';

export default class extends Controller {
    static targets = ['query', 'results', 'message', 'newForm', 'name', 'phone', 'error'];
    static values = { searchUrl: String, selectUrl: String, token: String };

    disconnect() {
        clearTimeout(this.timer);
        this.searchRequest?.abort();
    }

    scheduleSearch() {
        clearTimeout(this.timer);
        this.searchRequest?.abort();
        this.timer = setTimeout(() => this.search(), 250);
    }

    async search(event) {
        event?.preventDefault();
        clearTimeout(this.timer);
        this.searchRequest?.abort();
        const query = this.queryTarget.value.trim();
        this.resultsTarget.replaceChildren();
        this.messageTarget.textContent = '';
        this.errorTarget.hidden = true;
        if (query.length < 2) return;
        const request = new AbortController();
        this.searchRequest = request;
        this.messageTarget.textContent = 'Поиск…';
        try {
            const url = new URL(this.searchUrlValue, window.location.href);
            url.searchParams.set('q', query);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('json')) throw new Error('Не удалось найти клиентов. Повторите поиск.');
            const data = await response.json();
            if (request !== this.searchRequest || request.signal.aborted) return;
            this.renderResults(data.customers);
        } catch (error) {
            if (request !== this.searchRequest || request.signal.aborted) return;
            this.messageTarget.textContent = '';
            this.showError(error);
        }
    }

    renderResults(customers) {
        this.messageTarget.textContent = customers.length ? '' : 'Клиент не найден.';
        const table = document.createElement('table');
        table.className = 'grid';
        for (const customer of customers) {
            const row = document.createElement('tr');
            for (const value of [customer.name, customer.phone || '—']) {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.append(cell);
            }
            const cell = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Выбрать';
            button.dataset.customerId = String(customer.id);
            button.dataset.action = 'order-customer#choose';
            cell.append(button);
            row.append(cell);
            table.append(row);
        }
        this.resultsTarget.replaceChildren(table);
    }

    showNew() {
        this.newFormTarget.hidden = false;
        const query = this.queryTarget.value.trim();
        if (/^[+()\d\s-]+$/.test(query)) this.phoneTarget.value ||= query;
        else this.nameTarget.value ||= query;
        this.nameTarget.focus();
    }

    hideNew() {
        this.newFormTarget.hidden = true;
    }

    phoneChanged() {
        this.queryTarget.value = this.phoneTarget.value;
        this.scheduleSearch();
    }

    async choose(event) {
        event.preventDefault();
        if (this.pending) return;
        this.setPending(true);
        try {
            await this.select(Number(event.currentTarget.dataset.customerId));
        } catch (error) {
            this.showError(error);
        } finally {
            this.setPending(false);
        }
    }

    async create(event) {
        event.preventDefault();
        if (this.pending || !this.newFormTarget.reportValidity()) return;
        const payload = serializeForm(new FormData(this.newFormTarget));
        this.setPending(true);
        try {
            const result = await postJson(this.newFormTarget.action, payload, this.tokenValue);
            await this.select(result.customer.id);
        } catch (error) {
            this.showError(error);
        } finally {
            this.setPending(false);
        }
    }

    async select(customerId) {
        const result = await postJson(this.selectUrlValue, { customerId }, this.tokenValue);
        const destination = new URL(result.location, window.location.href);
        if (destination.origin !== window.location.origin) throw new Error('Некорректный адрес перехода.');
        window.location.assign(destination.href);
    }

    setPending(pending) {
        this.pending = pending;
        if (pending) this.errorTarget.hidden = true;
        this.element.querySelectorAll('button').forEach(button => { button.disabled = pending; });
    }

    showError(error) {
        this.errorTarget.textContent = error instanceof TypeError ? 'Не удалось связаться с сервером.' : error.message;
        this.errorTarget.hidden = false;
        this.errorTarget.focus();
    }
}
