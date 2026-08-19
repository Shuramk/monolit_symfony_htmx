import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function controller() {
    const source = readFileSync(new URL('../../assets/controllers/order_search_controller.js', import.meta.url), 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", '')
        .replace('export default class', 'this.Search = class');
    const context = {
        Controller: class {},
        Option: class { constructor(text, value) { this.text = text; this.value = value; } },
        document: { createElement: () => ({
            content: { textContent: '' },
            set innerHTML(value) { this.content.textContent = value.replace(/<[^>]*>/g, ''); },
        }) },
    };
    runInNewContext(source, context);
    return new context.Search();
}

test('changing product group resets previous color and uses only its variants', () => {
    const search = controller();
    const options = [];
    search.productTarget = {
        replaceChildren(option) { options.splice(0, options.length, option); },
        add(option) { options.push(option); },
    };
    search.productsValue = { 10: [{ id: 11, name: 'серый' }, { id: 12, name: '<b>красный</b>' }], 20: [{ id: 21, name: 'белый' }] };
    search.parentTarget = { value: '10' };
    search.changeParent();
    assert.deepEqual(options.map(option => [option.text, option.value]), [['Выбрать цвет', '0'], ['серый', '11'], ['красный', '12']]);
    search.parentTarget.value = '20';
    search.changeParent();
    assert.deepEqual(options.map(option => option.value), ['0', '21']);
    search.parentTarget.value = '0';
    search.changeParent();
    assert.deepEqual(options.map(option => option.value), ['0']);
});

test('popup stays open for inside clicks and closes for outside clicks or Escape', () => {
    const search = controller();
    search.element = { open: true, contains: target => target === 'inside' };
    search.outside({ target: 'inside' });
    assert.equal(search.element.open, true);
    search.outside({ target: 'outside' });
    assert.equal(search.element.open, false);
    search.element.open = true;
    search.close();
    assert.equal(search.element.open, false);
});
