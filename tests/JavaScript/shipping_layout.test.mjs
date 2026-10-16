import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('shipping table preserves full width, legacy deadline colours and action columns', () => {
    const css = readFileSync(new URL('../../assets/styles/app.css', import.meta.url), 'utf8');
    const template = readFileSync(new URL('../../templates/shipping/index.html.twig', import.meta.url), 'utf8');
    assert.match(css, /\.shipping-page\s*\{\s*max-width:\s*none/);
    assert.match(css, /\.shipping-due-today[^}]*#ffd6d8/s);
    assert.match(css, /\.shipping-due-tomorrow[^}]*#ffe083/s);
    assert.match(css, /\.shipping-due-future[^}]*#bcef7a/s);
    assert.match(css, /\.shipping-list\s*>\s*thead[^}]*linear-gradient/s);
    assert.match(template, /shipping-col-comment/);
    assert.match(template, /Накладная\(с ценами\)/);
    assert.match(template, /Охрана/);
});

test('first action in every shipping column starts at the same top edge', () => {
    const css = readFileSync(new URL('../../assets/styles/app.css', import.meta.url), 'utf8');
    const firstForm = css.match(/\.shipping-list > tbody > tr > td > form\s*\{([^}]+)\}/);
    const confirmation = css.match(/\.shipping-confirm\s*\{([^}]+)\}/);
    assert.ok(firstForm, 'Direct action forms must have an explicit margin reset');
    assert.match(firstForm[1], /margin:\s*0\s*;/);
    assert.match(confirmation[1], /margin:\s*0\s*;/);
});

test('reservation popup header is centred and cannot inherit stripes from the outer stock table', () => {
    const css = readFileSync(new URL('../../assets/styles/app.css', import.meta.url), 'utf8');
    assert.ok(css.includes('.shipping-stock-overview > tbody > tr:nth-child(odd)'), 'Stock stripes must only apply to direct body rows');
    const header = css.match(/\.shipping-form-page \.shipping-reservations > table > thead > tr > th\s*\{([^}]+)\}/);
    assert.ok(header, 'Popup header needs its own scoped appearance');
    assert.match(header[1], /background:\s*linear-gradient/);
    assert.match(header[1], /text-align:\s*center/);
});
