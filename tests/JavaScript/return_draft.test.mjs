import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('draft rows receive unique names after removing and adding rows', () => {
    const source = readFileSync(new URL('../../assets/controllers/return_draft_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.ReturnDraft = class');
    const context = { Controller: class {} };
    runInNewContext(source, context);
    const controller = new context.ReturnDraft();
    const rows = [];
    controller.rowsTarget = { querySelectorAll: () => rows, appendChild: row => rows.push(row) };
    controller.templateTarget = { content: { cloneNode() {
        const fields = [{ name: 'items[INDEX][productId]' }, { name: 'items[INDEX][quantity]' }];
        return { fields, querySelectorAll: () => fields };
    } } };
    controller.connect();
    controller.add();
    assert.equal(rows[0].fields[0].name, 'items[0][productId]');
    controller.remove({ currentTarget: { closest: () => ({ remove() { rows.splice(0, 1); } }) } });
    controller.add();
    assert.equal(rows[0].fields[0].name, 'items[1][productId]');
    assert.equal(rows[1].fields[1].name, 'items[2][quantity]');
});
