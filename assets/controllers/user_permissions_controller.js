import { Controller } from '@hotwired/stimulus';
import { setPermission, isPermissionVisible } from '../lib/user_permissions.js';

export default class extends Controller {
    connect() { this.refresh(); this.updateStartPage(); }

    updateStartPage() {
        const page = this.element.querySelector('[name=startPageId]:checked');
        const label = this.element.querySelector('[data-start-page-label]');
        if (label) label.textContent = page ? page.dataset.startPageTitle : 'не выбрана';
        const form = this.element.closest('form');
        if (form) form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = !page; });
    }

    get rows() { return Array.from(this.element.querySelectorAll('[data-permission-row]')); }
    get nodes() { return this.rows.map(row => ({ id: Number(row.dataset.permissionRow), parent: Number(row.dataset.parent), expanded: row.dataset.expanded === 'true' })); }

    refresh() {
        const nodes = this.nodes;
        for (const row of this.rows) {
            row.hidden = !isPermissionVisible(nodes, Number(row.dataset.permissionRow));
            const button = row.querySelector('[data-permission-toggle]');
            if (button) {
                button.textContent = row.dataset.expanded === 'true' ? '−' : '+';
                button.setAttribute('aria-expanded', row.dataset.expanded);
            }
        }
    }

    toggle(event) {
        const row = event.currentTarget.closest('[data-permission-row]');
        row.dataset.expanded = row.dataset.expanded === 'true' ? 'false' : 'true';
        this.refresh();
    }
    expandAll() { this.rows.forEach(row => { row.dataset.expanded = 'true'; }); this.refresh(); }
    collapseAll() { this.rows.forEach(row => { row.dataset.expanded = Number(row.dataset.parent) === 0 ? 'false' : 'true'; }); this.refresh(); }

    change(event) {
        const field = event.target;
        const isPage = field.name === 'startPageId';
        if (!field.matches('[data-permission-checkbox]') && !isPage) return;
        const checkboxes = Array.from(this.element.querySelectorAll('[data-permission-checkbox]'));
        const selected = new Set(checkboxes.filter(input => input.checked).map(input => Number(input.value)));
        const result = setPermission(this.nodes, selected, Number(field.value), isPage || field.checked, !isPage);
        checkboxes.forEach(input => { input.checked = result.has(Number(input.value)); });
        const page = this.element.querySelector('[name=startPageId]:checked');
        if (page && !result.has(Number(page.value))) page.checked = false;
        this.updateStartPage();
    }
}
