import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../assets/styles/app.css', import.meta.url), 'utf8');

function declarations(selector) {
    const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const rule = css.match(new RegExp(`${escaped}\\s*\\{([^}]+)\\}`));
    assert.ok(rule, `Missing CSS rule: ${selector}`);

    return Object.fromEntries(rule[1].split(';').filter(value => value.trim()).map(value => {
        const [name, ...parts] = value.split(':');

        return [name.trim(), parts.join(':').trim()];
    }));
}

test('CRM board preserves legacy fixed widths instead of growing with the viewport', () => {
    const board = declarations('.crm-board');
    const column = declarations('.crm-board-column');
    const scroll = declarations('.crm-column-scroll');
    const card = declarations('.crm-board-card');
    assert.equal(board.width, '1580px');
    assert.equal(column.flex, '0 0 395px');
    assert.equal(column.width, '395px');
    assert.equal(scroll.width, '391px');
    assert.equal(card.width, '370px');
    assert.equal(card.height, '200px');
    assert.equal(declarations('.crm-board-viewport')['overflow-x'], 'auto');
    assert.equal(scroll['overflow-y'], 'scroll');
});
