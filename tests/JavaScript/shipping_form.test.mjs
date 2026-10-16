import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function form() {
    const source = readFileSync(new URL('../../assets/controllers/shipping_form_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Shipping = class');
    const context = { Controller: class {} };
    runInNewContext(source, context);
    const controller = new context.Shipping();
    controller.pickupTarget = { checked: false };
    controller.deliveryTarget = { hidden: false, disabled: false };
    controller.storageTarget = { value: '2' };
    controller.totalWeightTarget = { textContent: '' };
    const weight = { textContent: '' };
    const classes = new Set();
    const input = {
        value: '123', max: '515', dataset: { weight: '13', freeOffice: '200', freeWorkshop: '93' },
        classList: { toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); } },
        closest: () => ({ querySelector: () => weight }),
    };
    controller.quantityTargets = [input];

    return { controller, input, weight, classes };
}

test('shipping quantities update row and total weight, with shortage colour for the selected warehouse', () => {
    const { controller, input, weight, classes } = form();
    controller.connect();
    assert.equal(weight.textContent, '1.599т');
    assert.equal(controller.totalWeightTarget.textContent, '1.599т');
    assert.equal(classes.has('shipping-shortage'), true);
    controller.storageTarget.value = '1';
    controller.recalculate();
    assert.equal(classes.has('shipping-shortage'), false);
    input.value = '0';
    input.dataset.freeOffice = '-10';
    controller.recalculate();
    assert.equal(controller.totalWeightTarget.textContent, '0.000т');
    assert.equal(classes.has('shipping-shortage'), false);
});

test('pickup disables carrier fields and quantity is limited by the remaining order amount', () => {
    const { controller, input } = form();
    controller.pickupTarget.checked = true;
    controller.connect();
    assert.equal(controller.deliveryTarget.hidden, true);
    assert.equal(controller.deliveryTarget.disabled, true);
    controller.pickupTarget.checked = false;
    controller.togglePickup();
    assert.equal(controller.deliveryTarget.hidden, false);
    assert.equal(controller.deliveryTarget.disabled, false);
    input.value = '999';
    controller.recalculate();
    assert.equal(input.value, '515');
});
