import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function board(postJson, options = {}) {
    const source = readFileSync(new URL('../../assets/controllers/crm_board_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Board = class');
    const context = { Controller: class {}, postJson, window: { location: { href: 'http://localhost/crm/deals?managers[]=1' } }, ...options };
    runInNewContext(source, context);
    const controller = new context.Board();
    controller.errorTarget = { hidden: true, textContent: '', focus() {} };

    return controller;
}

test('counter updates its value and prevents duplicate submissions while saving', async () => {
    let finish;
    let calls = 0;
    const controller = board(() => { calls++; return new Promise(resolve => { finish = resolve; }); });
    const button = { disabled: false };
    const value = { textContent: '0' };
    const form = { action: '/crm/counters', elements: { kind: { value: 'calls' }, _token: { value: 'csrf' } }, querySelector: selector => selector === 'button' ? button : value };
    const event = { preventDefault() {}, currentTarget: form };
    const saving = controller.counter(event);
    await controller.counter(event);
    assert.equal(calls, 1);
    assert.equal(button.disabled, true);
    finish({ counters: { calls: 7 } });
    await saving;
    assert.equal(value.textContent, 7);
    assert.equal(button.disabled, false);
});

function approvalForm() {
    const checkbox = { checked: true, disabled: false };
    const form = { action: '/crm/shipments/10/approval', elements: { allowed: checkbox, expectedAllowed: { value: '0' }, _token: { value: 'csrf' } } };
    const event = { preventDefault() {}, currentTarget: { closest: () => form } };

    return { form, checkbox, event };
}

test('failed approval restores the checkbox and exposes the server error', async () => {
    const controller = board(async () => { throw new Error('Задача уже изменена.'); });
    const { checkbox, event } = approvalForm();
    await controller.approval(event);
    assert.equal(checkbox.checked, false);
    assert.equal(checkbox.disabled, false);
    assert.equal(controller.errorTarget.hidden, false);
    assert.equal(controller.errorTarget.textContent, 'Задача уже изменена.');
});

test('successful approval refreshes totals without losing any column scroll position', async () => {
    let request;
    let replaced;
    const columns = ['deals', 'orders', 'shipments', 'approved'].map((name, index) => ({ dataset: { column: name }, scroll: { scrollTop: index * 30 }, querySelector() { return this.scroll; } }));
    const newBoard = {};
    const controller = board(async (url, payload) => { request = { url, payload }; }, {
        fetch: async () => ({ ok: true, text: async () => '<board>' }),
        DOMParser: class { parseFromString() { return { querySelector: () => newBoard }; } },
    });
    controller.element = { querySelectorAll: () => columns, querySelector: () => ({ replaceWith(value) { replaced = value; columns.forEach(column => { column.scroll.scrollTop = 0; }); } }) };
    const { checkbox, event } = approvalForm();
    await controller.approval(event);
    assert.equal(request.url, '/crm/shipments/10/approval');
    assert.equal(request.payload.allowed, 1);
    assert.equal(request.payload.expectedAllowed, 0);
    assert.equal(replaced, newBoard);
    assert.deepEqual(columns.map(column => column.scroll.scrollTop), [0, 30, 60, 90]);
    assert.equal(checkbox.disabled, false);
});

test('refresh failure after a saved approval does not pretend the saved state was reverted', async () => {
    const controller = board(async () => {}, { fetch: async () => ({ ok: false }) });
    controller.element = { querySelectorAll: () => [] };
    const { checkbox, form, event } = approvalForm();
    await controller.approval(event);
    assert.equal(checkbox.checked, true);
    assert.equal(form.elements.expectedAllowed.value, '1');
    assert.equal(controller.errorTarget.hidden, false);
});
