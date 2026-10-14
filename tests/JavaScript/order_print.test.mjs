import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function controller() {
    const source = readFileSync(new URL('../../assets/controllers/order_print_controller.js', import.meta.url), 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", '')
        .replace('export default class', 'this.Print = class');
    let printCalls = 0;
    const context = { Controller: class {}, window: { print() { printCalls++; } } };
    runInNewContext(source, context);
    return { print: new context.Print(), printCalls: () => printCalls };
}

test('print button invokes the browser print dialog', () => {
    const { print, printCalls } = controller();
    print.print();
    assert.equal(printCalls(), 1);
});

test('paid checkbox changes only the local stamp, in both directions', () => {
    const { print } = controller();
    print.stampTarget = { hidden: true };
    print.togglePaid({ target: { checked: true } });
    assert.equal(print.stampTarget.hidden, false);
    print.togglePaid({ target: { checked: false } });
    assert.equal(print.stampTarget.hidden, true);
});

test('paper size is selected by the printer, not forced to A4 and scaled down', () => {
    const css = readFileSync(new URL('../../assets/styles/order_print.css', import.meta.url), 'utf8');
    assert.match(css, /@page\s*\{\s*size:\s*auto;/);
    assert.doesNotMatch(css, /size:\s*A[45]\b/i);
    assert.match(css, /@media print[\s\S]*\.order-print-document\s*\{\s*width:\s*100%;/);
    assert.doesNotMatch(css, /\b(?:zoom|transform):/);
});
