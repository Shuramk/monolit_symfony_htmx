import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('order header keeps print above the shipping and editing actions', () => {
    const css = readFileSync(new URL('../../assets/styles/app.css', import.meta.url), 'utf8');
    const template = readFileSync(new URL('../../templates/order/show.html.twig', import.meta.url), 'utf8');
    const actions = css.match(/\.legacy-order-actions\s*\{([^}]+)\}/);
    assert.match(actions[1], /display:\s*grid/);
    assert.match(actions[1], /grid-template-columns:\s*repeat\(2,/);
    assert.match(css, /\.legacy-order-actions > \.legacy-order-print-button\s*\{\s*grid-column:\s*1 \/ -1/);
    assert.match(css, /\.legacy-order-edit-link\s*\{\s*grid-column:\s*2/);
    assert.ok(template.indexOf('class="legacy-order-print-button"') < template.indexOf('class="legacy-order-shipping-actions"'));
    assert.ok(template.indexOf('class="legacy-order-shipping-actions"') < template.indexOf('class="legacy-order-edit-link"'));
});
