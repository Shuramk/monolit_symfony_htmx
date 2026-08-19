import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('reason is visible and required only for cancellation', () => {
    const source = readFileSync(new URL('../../assets/controllers/order_status_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Status = class');
    const context = { Controller: class {} };
    runInNewContext(source, context);
    const controller = new context.Status();
    let status = null;
    controller.element = { querySelector: () => status };
    controller.reasonTarget = { hidden: false };
    controller.commentTarget = { required: true, disabled: false };
    controller.connect();
    assert.equal(controller.reasonTarget.hidden, true);
    assert.equal(controller.commentTarget.required, false);
    assert.equal(controller.commentTarget.disabled, true);
    status = { value: 'cancelled' };
    controller.toggle();
    assert.equal(controller.reasonTarget.hidden, false);
    assert.equal(controller.commentTarget.required, true);
    assert.equal(controller.commentTarget.disabled, false);
    status = { value: 'in_progress' };
    controller.toggle();
    assert.equal(controller.reasonTarget.hidden, true);
    assert.equal(controller.commentTarget.required, false);
    assert.equal(controller.commentTarget.disabled, true);
});
