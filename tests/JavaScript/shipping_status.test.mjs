import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function fixture(post) {
    const source = readFileSync(new URL('../../assets/controllers/shipping_status_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.ShippingStatus = class');
    const button = () => ({
        disabled: false, textContent: '', attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        classList: { toggle() {} },
    });
    const context = {
        Controller: class {}, TypeError,
        postJson: post,
        serializeForm: (entries) => Object.fromEntries(entries),
        FormData: class {
            constructor(form) { return Object.entries(form.elements).filter(([name]) => name !== '_token').map(([name, field]) => [name, field.value]); }
        },
    };
    runInNewContext(source, context);
    const controller = new context.ShippingStatus();
    controller.idValue = 7;
    controller.hasCollectFormTarget = true;
    controller.hasApprovalFormTarget = true;
    controller.collectButtonTarget = button();
    controller.collectCheckTarget = { hidden: true };
    controller.approvalButtonTarget = button();
    controller.approvalSummaryTarget = { textContent: 'Согласовано: —' };
    const completeButton = button();
    const versions = [{ value: '0' }, { value: '0' }, { value: '0' }];
    controller.element = {
        setAttribute() {}, removeAttribute() {},
        querySelectorAll(selector) { return selector.includes('button') ? [controller.collectButtonTarget, controller.approvalButtonTarget, completeButton] : versions; },
    };
    controller.collectFormTarget = {
        action: '/shipping/7/collect', hidden: false,
        elements: { version: versions[0], _token: { value: 'csrf' } },
    };
    controller.approvalFormTarget = {
        action: '/shipments/7/approval',
        elements: { allowed: { value: '1' }, expectedAllowed: { value: '0' }, _token: { value: 'csrf' } },
    };
    controller.errorTarget = { hidden: true, textContent: '', focus() { this.focused = true; } };
    const event = (form) => ({ preventDefault() {}, currentTarget: form });

    return { controller, versions, completeButton, event };
}

test('collection and approval update in place and synchronize all action versions', async () => {
    const calls = [];
    const { controller, versions, event } = fixture(async (url, payload, token) => {
        calls.push({ url, payload, token });
        const allowed = calls.length > 1;
        return { location: '/shipping', task: { id: 7, version: calls.length, status: 0, collected: true, allowed } };
    });
    await controller.submit(event(controller.collectFormTarget));
    assert.equal(calls.length, 1);
    assert.equal(calls[0].token, 'csrf');
    assert.equal(controller.collectFormTarget.action, '/shipping/7/collect');
    assert.equal(controller.collectFormTarget.hidden, true);
    assert.equal(controller.collectCheckTarget.hidden, false);
    assert.equal(controller.collectButtonTarget.disabled, true);
    assert.deepEqual(versions.map(field => field.value), ['1', '1', '1']);
    await controller.submit(event(controller.approvalFormTarget));
    assert.deepEqual(versions.map(field => field.value), ['2', '2', '2']);
    assert.equal(controller.approvalButtonTarget.textContent, 'Снять');
    assert.equal(controller.approvalSummaryTarget.textContent, 'Согласовано: ✓');
    assert.equal(controller.approvalFormTarget.elements.allowed.value, '0');
    assert.equal(controller.approvalFormTarget.elements.expectedAllowed.value, '1');
    assert.equal(controller.errorTarget.hidden, true);
});

test('pending requests block repeated and competing actions in the same row', async () => {
    let finish;
    let calls = 0;
    const { controller, completeButton, event } = fixture(() => {
        calls++;
        return new Promise(resolve => { finish = resolve; });
    });
    const pending = controller.submit(event(controller.collectFormTarget));
    assert.equal(controller.approvalButtonTarget.disabled, true);
    assert.equal(completeButton.disabled, true);
    await controller.submit(event(controller.approvalFormTarget));
    assert.equal(calls, 1);
    finish({ task: { id: 7, version: 1, status: 0, collected: true, allowed: false } });
    await pending;
    assert.equal(completeButton.disabled, false);
});

test('a conflict or network failure keeps the old state and displays an inline error', async () => {
    const { controller, versions, event } = fixture(async () => { throw new Error('Задача уже изменена. Обновите страницу.'); });
    await controller.submit(event(controller.collectFormTarget));
    assert.equal(controller.collectFormTarget.action, '/shipping/7/collect');
    assert.deepEqual(versions.map(field => field.value), ['0', '0', '0']);
    assert.equal(controller.errorTarget.hidden, false);
    assert.match(controller.errorTarget.textContent, /уже изменена/);
    assert.equal(controller.collectButtonTarget.disabled, false);
});

test('collection is one-way and repeated clicks cannot send a second request', async () => {
    let calls = 0;
    const { controller, event } = fixture(async () => {
        calls++;
        return { task: { id: 7, version: 3, status: 0, collected: true, allowed: true } };
    });
    await controller.submit(event(controller.collectFormTarget));
    assert.equal(controller.collectFormTarget.action, '/shipping/7/collect');
    assert.equal(controller.collectFormTarget.hidden, true);
    assert.equal(controller.approvalSummaryTarget.textContent, 'Согласовано: ✓');
    await controller.submit(event(controller.collectFormTarget));
    assert.equal(calls, 1);
    const invalid = fixture(async () => ({ task: { id: 8, version: 4, status: 0, collected: true, allowed: false } }));
    await invalid.controller.submit(invalid.event(invalid.controller.collectFormTarget));
    assert.equal(invalid.controller.errorTarget.hidden, false);
    assert.deepEqual(invalid.versions.map(field => field.value), ['0', '0', '0']);
});

test('network errors do not navigate and a completed task disables further submissions', async () => {
    const network = fixture(async () => { throw new TypeError('Failed to fetch'); });
    await network.controller.submit(network.event(network.controller.collectFormTarget));
    assert.match(network.controller.errorTarget.textContent, /Не удалось связаться/);
    assert.equal(network.controller.approvalButtonTarget.disabled, false);
    let calls = 0;
    const completed = fixture(async () => {
        calls++;
        return { task: { id: 7, version: 4, status: 1, collected: true, allowed: true } };
    });
    await completed.controller.submit(completed.event(completed.controller.collectFormTarget));
    assert.equal(completed.completeButton.disabled, true);
    await completed.controller.submit(completed.event(completed.controller.approvalFormTarget));
    assert.equal(calls, 1);
});

test('users with only approval rights do not need collection controls', async () => {
    const { controller, event, versions } = fixture(async () => ({ task: { id: 7, version: 1, status: 0, collected: false, allowed: true } }));
    controller.hasCollectFormTarget = false;
    delete controller.collectButtonTarget;
    controller.element.querySelectorAll = (selector) => selector.includes('button') ? [controller.approvalButtonTarget] : versions;
    await controller.submit(event(controller.approvalFormTarget));
    assert.equal(controller.approvalSummaryTarget.textContent, 'Согласовано: ✓');
    assert.equal(controller.errorTarget.hidden, true);
});
