import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../assets/lib/product_prices.js', import.meta.url), 'utf8');
const { adjustPrices } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('bulk prices preserve four decimal places without float rounding', () => {
    assert.deepEqual(adjustPrices(['330.1234','370.0001','0'], '5'), ['335.1234','375.0001','5.0000']);
    assert.deepEqual(adjustPrices(['0.1000','330.1234'], '0.2'), ['0.3000','330.3234']);
    assert.deepEqual(adjustPrices(['330.1234'], '-5,25'), ['324.8734']);
});

test('table controller is registered in the actual application bootstrap', () => {
    const bootstrap = readFileSync(new URL('../../assets/stimulus_bootstrap.js', import.meta.url), 'utf8');
    assert.match(bootstrap, /import ProductTableController from '\.\/controllers\/product_table_controller\.js'/);
    assert.match(bootstrap, /app\.register\('product-table', ProductTableController\)/);
});

test('invalid bulk decrease never changes part of the input', () => {
    const prices = ['330.1234','0.0000'];
    assert.throws(() => adjustPrices(prices, '-5'), /от 0/);
    assert.deepEqual(prices, ['330.1234','0.0000']);
});

test('bulk price boundaries and malformed adjustments are rejected', () => {
    assert.deepEqual(adjustPrices(['99999999.9998'], '0.0001'), ['99999999.9999']);
    assert.throws(() => adjustPrices(['99999999.9999'], '0.0001'), /от 0/);
    for (const delta of ['', '1.00001','NaN','1e3','--5']) assert.throws(() => adjustPrices(['1'], delta));
    assert.deepEqual(adjustPrices(['5.0000'], '-5'), ['0.0000']);
});

test('percentage adjustments increase and decrease current prices exactly', () => {
    assert.deepEqual(adjustPrices(['330','370','0'], '5', 'percent'), ['346.5000','388.5000','0.0000']);
    assert.deepEqual(adjustPrices(['330','370'], '-5', 'percent'), ['313.5000','351.5000']);
    assert.deepEqual(adjustPrices(['100'], '2,5', 'percent'), ['102.5000']);
    assert.deepEqual(adjustPrices(['330.1234'], '0', 'percent'), ['330.1234']);
    assert.deepEqual(adjustPrices(['330.1234','0'], '−100', 'percent'), ['0.0000','0.0000']);
});

test('percentage results round half up to four decimal places without floats', () => {
    assert.deepEqual(adjustPrices(['0.0001','0.0003','123.4567'], '50', 'percent'), ['0.0002','0.0005','185.1851']);
    assert.deepEqual(adjustPrices(['0.0001','0.0003'], '-50', 'percent'), ['0.0001','0.0002']);
    assert.deepEqual(adjustPrices(['100'], '0.0001', 'percent'), ['100.0001']);
});

test('invalid percentages and overflows leave the entire source list untouched', () => {
    const prices = ['330.0000','99999999.9999'];
    assert.throws(() => adjustPrices(prices, '5', 'percent'), /от 0/);
    assert.deepEqual(prices, ['330.0000','99999999.9999']);
    assert.throws(() => adjustPrices(['0','100'], '-100.0001', 'percent'), /100/);
    assert.throws(() => adjustPrices(['100'], '1.00001', 'percent'));
    assert.throws(() => adjustPrices(['100'], '5', 'unknown'));
});

function table(transport = async () => ({ versions: { 1: 4, 2: 8 }, location: '/settings/products/10' })) {
    const controllerSource = readFileSync(new URL('../../assets/controllers/product_table_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Table = class');
    const context = { Controller: class {}, postJson: transport, adjustPrices, validationMessage: () => '', window: { addEventListener() {}, removeEventListener() {} } };
    runInNewContext(controllerSource, context);
    const classes = () => ({ values: new Set(), toggle(name, value) { value ? this.values.add(name) : this.values.delete(name); }, add(name) { this.values.add(name); }, remove(name) { this.values.delete(name); } });
    const row = (id, version, price) => {
        const fields = Object.entries({ id, version, name: 'серый', nameUk: 'сірий', price, sorting: '100', enabled: '1' })
            .map(([key,value]) => ({ dataset: { productField: key }, value, disabled: false }));
        return { fields, classList: classes(), querySelectorAll: () => fields, querySelector: selector => fields.find(field => selector === `[data-product-field=${field.dataset.productField}]`) };
    };
    const editor = new context.Table();
    editor.rowTargets = [row('1','3','330.1234'),row('2','7','370.0001')];
    editor.priceTargets = editor.rowTargets.map(row => row.querySelector('[data-product-field=price]'));
    editor.deltaTarget = { value: '5' };
    editor.modeTarget = { value: 'amount' };
    editor.hasResetTarget = true;
    editor.resetTarget = { disabled: false };
    editor.messageTarget = { textContent: '', classList: classes() };
    editor.element = { action: '/settings/products/10/variants', reportValidity: () => true,
        querySelector: () => ({ value: 'csrf-token' }), querySelectorAll: () => editor.rowTargets.flatMap(row => row.fields), setAttribute() {}, removeAttribute() {} };
    editor.connect();
    return editor;
}

test('table saves changed rows as JSON and remembers returned versions for the next edit', async () => {
    let sent;
    const editor = table(async (url, payload, token) => {
        sent = JSON.parse(JSON.stringify({url,payload,token}));
        return { versions: {1:4,2:8}, location: '/settings/products/10' };
    });
    editor.adjust();
    assert.deepEqual(editor.priceTargets.map(field => field.value), ['335.1234','375.0001']);
    assert.ok(editor.rowTargets.every(row => editor.isChanged(row)));
    await editor.submit({preventDefault() {}});
    assert.equal(sent.token, 'csrf-token');
    assert.equal(sent.payload.rows[0].price, '335.1234');
    assert.equal(sent.payload.rows[0].version, '3');
    assert.ok(editor.rowTargets.every(row => !editor.isChanged(row)));
    assert.equal(editor.rowTargets[0].querySelector('[data-product-field=version]').value, 4);
    assert.ok(editor.rowTargets.flatMap(row => row.fields).every(field => !field.disabled));
    assert.match(editor.messageTarget.textContent, /Сохранено строк: 2/);
    assert.equal(editor.resetTarget.disabled, true);
    editor.adjust();
    editor.resetPrices();
    assert.deepEqual(editor.priceTargets.map(field => field.value), ['335.1234','375.0001']);
    assert.ok(editor.rowTargets.every(row => !editor.isChanged(row)));
});

test('reset restores manual and bulk price edits but keeps all other unsaved fields', () => {
    const editor = table();
    assert.equal(editor.resetTarget.disabled, true);
    editor.modeTarget.value = 'percent';
    editor.adjust();
    assert.deepEqual(editor.priceTargets.map(field => field.value), ['346.6296','388.5001']);
    assert.equal(editor.resetTarget.disabled, false);
    editor.rowTargets[0].querySelector('[data-product-field=name]').value = 'новый цвет';
    editor.rowTargets[0].querySelector('[data-product-field=sorting]').value = '5';
    editor.rowTargets[0].querySelector('[data-product-field=enabled]').value = '0';
    editor.priceTargets[1].value = '900.0000';
    editor.resetPrices();
    assert.deepEqual(editor.priceTargets.map(field => field.value), ['330.1234','370.0001']);
    assert.equal(editor.rowTargets[0].querySelector('[data-product-field=name]').value, 'новый цвет');
    assert.equal(editor.rowTargets[0].querySelector('[data-product-field=sorting]').value, '5');
    assert.equal(editor.rowTargets[0].querySelector('[data-product-field=enabled]').value, '0');
    assert.equal(editor.isChanged(editor.rowTargets[0]), true);
    assert.equal(editor.isChanged(editor.rowTargets[1]), false);
    assert.equal(editor.resetTarget.disabled, true);
});

test('table keeps every edit and re-enables inputs after a failed atomic save', async () => {
    const editor = table(async () => { throw new Error('Заказ уже изменён'); });
    editor.adjust();
    await editor.submit({preventDefault() {}});
    assert.equal(editor.priceTargets[0].value, '335.1234');
    assert.ok(editor.rowTargets.every(row => editor.isChanged(row)));
    assert.ok(editor.rowTargets.flatMap(row => row.fields).every(field => !field.disabled));
    assert.ok(editor.messageTarget.classList.values.has('is-error'));
    assert.equal(editor.resetTarget.disabled, false);
    editor.resetPrices();
    assert.ok(editor.rowTargets.every(row => !editor.isChanged(row)));
    assert.equal(editor.resetTarget.disabled, true);
});

test('table does not send untouched rows or allow an invalid bulk preview', async () => {
    let requests = 0;
    const editor = table(async () => { requests++; });
    await editor.submit({preventDefault() {}});
    assert.equal(requests, 0);
    editor.deltaTarget.value = '-500';
    editor.adjust();
    assert.deepEqual(editor.priceTargets.map(field => field.value), ['330.1234','370.0001']);
    assert.ok(editor.rowTargets.every(row => !editor.isChanged(row)));
});
