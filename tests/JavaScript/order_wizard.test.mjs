import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function load(name, globals = {}) {
    const source = readFileSync(new URL(`../../assets/controllers/${name}_controller.js`, import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.ControllerClass = class');
    const context = { Controller: class {}, ...globals };
    runInNewContext(source, context);

    return new context.ControllerClass();
}

test('delivery defaults to pickup, preserves editable values and calculates exact cents', () => {
    const controller = load('order_delivery');
    controller.pickupTarget = { checked: true };
    controller.deliveryTarget = {};
    controller.priceTarget = { value: '36.70' };
    controller.tripsTarget = { value: '3' };
    controller.costTarget = {};
    controller.totalTarget = {};
    controller.hasWithMarkupTarget = true;
    controller.withMarkupTarget = {};
    controller.markupValue = 20;
    controller.connect();
    assert.equal(controller.deliveryTarget.disabled, true);
    assert.equal(controller.costTarget.value, '0.00');
    controller.pickupTarget.checked = false;
    controller.toggle();
    assert.equal(controller.deliveryTarget.hidden, false);
    assert.equal(controller.costTarget.value, '110.10');
    assert.equal(controller.withMarkupTarget.textContent, '132.12');
    controller.pickupTarget.checked = true;
    controller.toggle();
    assert.equal(controller.costTarget.value, '0.00');
    assert.equal(controller.priceTarget.value, '36.70');
});

function node(tag = '') {
    return {
        tag, children: [], dataset: {}, textContent: '', hidden: false, value: '',
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        focus() {},
    };
}

function customer(globals = {}) {
    const destinations = [];
    const controller = load('order_customer', {
        URL, AbortController, TypeError, setTimeout, clearTimeout,
        document: { createElement: node },
        window: { location: { href: 'http://localhost/orders/new/customer', origin: 'http://localhost', assign: url => destinations.push(url) } },
        serializeForm: entries => Object.fromEntries(entries),
        FormData: class { constructor(form) { return Object.entries(form.fields); } },
        ...globals,
    });
    for (const name of ['query', 'results', 'message', 'name', 'phone', 'error']) controller[`${name}Target`] = node();
    controller.errorTarget.hidden = true;
    controller.newFormTarget = { hidden: true, action: '/orders/new/customers', fields: { name: 'Клиент', phone: '0671234567', operationId: 'operation-key' }, reportValidity: () => true };
    controller.searchUrlValue = '/orders/new/customers/search';
    controller.selectUrlValue = '/orders/draft/customer';
    controller.tokenValue = 'csrf';
    controller.element = { querySelectorAll: () => [] };

    return { controller, destinations };
}

test('customer search ignores stale responses and renders client strings as text', async () => {
    const requests = [];
    const { controller } = customer({ fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })) });
    controller.queryTarget.value = 'first';
    const first = controller.search();
    controller.queryTarget.value = '(067) 123-45-67';
    const second = controller.search();
    assert.equal(requests[0].options.signal.aborted, true);
    const response = customers => ({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ customers }) });
    requests[1].resolve(response([{ id: 2, name: '<script>alert(1)</script>', phone: '0671234567' }]));
    await second;
    requests[0].resolve(response([{ id: 1, name: 'Wrong old match', phone: '' }]));
    await first;
    const row = controller.resultsTarget.children[0].children[0];
    assert.equal(row.children[0].textContent, '<script>alert(1)</script>');
    assert.equal(row.children[2].children[0].dataset.customerId, '2');
    assert.equal(requests[1].url.searchParams.get('q'), '(067) 123-45-67');
});

test('new customer is created and selected without leaving the order draft', async () => {
    const requests = [];
    const { controller, destinations } = customer({ postJson: async (url, payload, token) => {
        requests.push({ url, payload, token });
        return requests.length === 1 ? { customer: { id: 42 } } : { location: '/orders/new/delivery' };
    } });
    controller.queryTarget.value = 'Клиент';
    controller.showNew();
    assert.equal(controller.newFormTarget.hidden, false);
    assert.equal(controller.nameTarget.value, 'Клиент');
    await controller.create({ preventDefault() {} });
    assert.equal(requests[0].payload.operationId, 'operation-key');
    assert.equal(requests[0].token, 'csrf');
    assert.equal(requests[1].payload.customerId, 42);
    assert.deepEqual(destinations, ['http://localhost/orders/new/delivery']);
});

test('failed customer saving leaves the error and form values visible', async () => {
    const { controller, destinations } = customer({ postJson: async () => { throw new Error('Укажите корректный телефон клиента.'); } });
    controller.showNew();
    await controller.create({ preventDefault() {} });
    assert.equal(controller.errorTarget.hidden, false);
    assert.match(controller.errorTarget.textContent, /телефон/);
    assert.equal(controller.newFormTarget.hidden, false);
    assert.equal(controller.newFormTarget.fields.phone, '0671234567');
    assert.deepEqual(destinations, []);
    assert.equal(controller.pending, false);
});

test('client search does not request the entire directory for empty or short input', async () => {
    let calls = 0;
    const { controller } = customer({ fetch: async () => { calls++; } });
    controller.queryTarget.value = 'А';
    await controller.search();
    assert.equal(calls, 0);
});
