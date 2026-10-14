import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { setPermission, isPermissionVisible } from '../../assets/lib/user_permissions.js';

const nodes = [{id:200,parent:0,expanded:true},{id:220,parent:200,expanded:false},{id:227,parent:220},{id:228,parent:220},{id:270,parent:200},{id:100,parent:0,expanded:false}];
test('selecting a section selects descendants, not unrelated sections', () => {
    assert.deepEqual([...setPermission(nodes,new Set(),200,true)].sort((a,b)=>a-b),[200,220,227,228,270]);
});
test('removing a section preserves other branches and selects parents only while children remain', () => {
    assert.deepEqual([...setPermission(nodes,new Set([100,200,220,227,228,270]),220,false)].sort((a,b)=>a-b),[100,200,270]);
    assert.deepEqual([...setPermission(nodes,new Set([200,220,227]),227,false)],[]);
});
test('a single permission selects its ancestors without granting siblings', () => {
    assert.deepEqual([...setPermission(nodes,new Set(),227,true)].sort((a,b)=>a-b),[200,220,227]);
});
test('choosing a start page does not grant its child actions', () => {
    assert.deepEqual([...setPermission(nodes,new Set(),220,true,false)].sort((a,b)=>a-b),[200,220]);
});
test('collapse hides all descendant levels and expanding preserves inner collapse state', () => {
    assert.equal(isPermissionVisible(nodes,220),true);
    assert.equal(isPermissionVisible(nodes,227),false);
    assert.equal(isPermissionVisible(nodes.map(node=>({...node,expanded:true})),227),true);
    assert.equal(isPermissionVisible(nodes.map(node=>node.id===200?{...node,expanded:false}:node),270),false);
});

test('reopening a section after collapse-all reveals every function at its logical depth', () => {
    const source = readFileSync(new URL('../../assets/controllers/user_permissions_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Permissions = class');
    const context = { Controller: class {}, setPermission, isPermissionVisible };
    runInNewContext(source, context);
    const rows = nodes.map(node => ({ dataset: { permissionRow: String(node.id), parent: String(node.parent), expanded: 'true' }, querySelector: () => null }));
    const controller = new context.Permissions();
    controller.element = { querySelectorAll: () => rows };
    controller.collapseAll();
    assert.equal(rows.find(row => row.dataset.permissionRow === '227').hidden, true);
    controller.toggle({ currentTarget: { closest: () => rows[0] } });
    for (const id of [220,227,228,270]) assert.equal(rows.find(row => Number(row.dataset.permissionRow) === id).hidden, false);
    assert.equal(rows.find(row => row.dataset.permissionRow === '100').dataset.expanded, 'false');
    controller.expandAll();
    assert.equal(rows.every(row => !row.hidden), true);
});

test('start-page label and saving follow selection and removal of its permission', () => {
    const source = readFileSync(new URL('../../assets/controllers/user_permissions_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Permissions = class');
    const context = { Controller: class {}, setPermission, isPermissionVisible };
    runInNewContext(source, context);
    const rows = nodes.map(node => ({ dataset: { permissionRow: String(node.id), parent: String(node.parent), expanded: 'true' }, querySelector: () => null }));
    const checkboxes = nodes.map(node => ({ value: String(node.id), checked: false, matches: () => true }));
    const page = { name: 'startPageId', value: '220', checked: false, dataset: { startPageTitle: 'Заказы' }, matches: () => false };
    const label = { textContent: '' }; const button = { disabled: false };
    const controller = new context.Permissions();
    controller.element = {
        querySelectorAll: selector => selector === '[data-permission-row]' ? rows : checkboxes,
        querySelector: selector => selector === '[data-start-page-label]' ? label : page.checked ? page : null,
        closest: () => ({ querySelectorAll: () => [button] }),
    };
    controller.connect();
    assert.equal(label.textContent, 'не выбрана'); assert.equal(button.disabled, true);
    page.checked = true; controller.change({ target: page });
    assert.equal(label.textContent, 'Заказы'); assert.equal(button.disabled, false);
    assert.equal(checkboxes.find(input => input.value === '220').checked, true);
    const section = checkboxes.find(input => input.value === '200'); section.checked = false;
    controller.change({ target: section });
    assert.equal(page.checked, false); assert.equal(label.textContent, 'не выбрана'); assert.equal(button.disabled, true);
});
