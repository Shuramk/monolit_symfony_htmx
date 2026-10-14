import { Controller } from '@hotwired/stimulus';
import { postJson } from '../lib/json_form.js';

export default class extends Controller {
    static targets = ['error'];

    connect() {
        this.element.classList.add('crm-board-enhanced');
        const viewport = this.element.querySelector('.crm-board-viewport');
        this.resize = () => {
            const height = Math.max(260, window.innerHeight - viewport.getBoundingClientRect().top - 12);
            viewport.style.height = `${height}px`;
        };
        this.resize();
        window.addEventListener('resize', this.resize);
        this.element.querySelector('.crm-board-filter').addEventListener('toggle', this.resize);
    }

    disconnect() {
        window.removeEventListener('resize', this.resize);
        this.element.querySelector('.crm-board-filter').removeEventListener('toggle', this.resize);
    }

    async counter(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button');
        if (button.disabled) {

            return;
        }
        button.disabled = true;
        this.errorTarget.hidden = true;
        try {
            const data = await postJson(form.action, { kind: form.elements.kind.value }, form.elements._token.value);
            form.querySelector('[data-counter-value]').textContent = data.counters[form.elements.kind.value];
        } catch (error) {
            this.showError(error);
        } finally {
            button.disabled = false;
        }
    }

    async approval(event) {
        event.preventDefault();
        const form = event.currentTarget.closest('form');
        const checkbox = form.elements.allowed;
        if (checkbox.disabled) {

            return;
        }
        const expectedAllowed = Number(form.elements.expectedAllowed.value);
        const allowed = checkbox.checked ? 1 : 0;
        let saved = false;
        checkbox.disabled = true;
        this.errorTarget.hidden = true;
        try {
            await postJson(form.action, { allowed, expectedAllowed }, form.elements._token.value);
            saved = true;
            form.elements.expectedAllowed.value = String(allowed);
            const positions = new Map(Array.from(this.element.querySelectorAll('[data-column]'), column => [column.dataset.column, column.querySelector('.crm-column-scroll').scrollTop]));
            const response = await fetch(window.location.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Не удалось обновить CRM. Обновите страницу.');
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const board = document.querySelector('.crm-board');
            if (!board) throw new Error('Не удалось обновить CRM. Обновите страницу.');
            this.element.querySelector('.crm-board').replaceWith(board);
            for (const column of this.element.querySelectorAll('[data-column]')) {
                column.querySelector('.crm-column-scroll').scrollTop = positions.get(column.dataset.column) ?? 0;
            }
        } catch (error) {
            checkbox.checked = (saved ? allowed : expectedAllowed) === 1;
            this.showError(error);
        } finally {
            checkbox.disabled = false;
        }
    }

    showError(error) {
        this.errorTarget.textContent = error instanceof TypeError ? 'Не удалось связаться с сервером.' : error.message;
        this.errorTarget.hidden = false;
        this.errorTarget.focus();
    }
}
