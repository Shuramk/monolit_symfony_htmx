import { Controller } from '@hotwired/stimulus';
import { postJson, serializeForm } from '../lib/json_form.js';

export default class extends Controller {
    static targets = ['collectForm', 'collectCheck', 'approvalForm', 'collectButton', 'approvalButton', 'approvalSummary', 'error'];
    static values = { id: Number };

    async submit(event) {
        event.preventDefault();
        if (this.pending || this.closed) return;

        const form = event.currentTarget;
        if (this.hasCollectFormTarget && form === this.collectFormTarget && this.collected) return;
        const payload = serializeForm(new FormData(form));
        const buttons = Array.from(this.element.querySelectorAll('button[type="submit"]'));
        const disabled = buttons.map(button => button.disabled);
        this.pending = true;
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = '';
        buttons.forEach(button => { button.disabled = true; });
        this.element.setAttribute('aria-busy', 'true');

        try {
            const data = await postJson(form.action, payload, form.elements._token.value);
            this.render(data.task);
        } catch (error) {
            this.errorTarget.textContent = error instanceof TypeError
                ? 'Не удалось связаться с сервером. Проверьте соединение и повторите попытку.'
                : error.message;
            this.errorTarget.hidden = false;
            this.errorTarget.focus();
        } finally {
            this.pending = false;
            buttons.forEach((button, index) => {
                button.disabled = disabled[index] || this.closed === true
                    || (this.hasCollectFormTarget && button === this.collectButtonTarget && this.collected === true);
            });
            this.element.removeAttribute('aria-busy');
        }
    }

    render(task) {
        if (!task || task.id !== this.idValue || !Number.isInteger(task.version) || task.version < 0
            || !Number.isInteger(task.status) || typeof task.collected !== 'boolean' || typeof task.allowed !== 'boolean'
        ) {
            throw new Error('Не удалось обновить состояние задачи. Обновите страницу.');
        }
        this.closed = task.status !== 0;
        this.collected = task.collected;
        for (const version of this.element.querySelectorAll('input[name="version"]')) {
            version.value = String(task.version);
        }
        if (this.hasCollectFormTarget) {
            this.collectFormTarget.hidden = task.collected;
        }
        this.collectCheckTarget.hidden = !task.collected;
        if (this.hasApprovalFormTarget) {
            this.approvalFormTarget.elements.allowed.value = task.allowed ? '0' : '1';
            this.approvalFormTarget.elements.expectedAllowed.value = task.allowed ? '1' : '0';
            this.approvalButtonTarget.textContent = task.allowed ? 'Снять' : 'Согласовать';
            this.approvalSummaryTarget.textContent = task.allowed ? 'Согласовано: ✓' : 'Согласовано: —';
        }
    }
}
