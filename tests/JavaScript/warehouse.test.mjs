import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function warehouse() {
    const source = readFileSync(new URL('../../assets/controllers/warehouse_controller.js', import.meta.url), 'utf8')
        .replace(/^import .*;$/gm, '').replace('export default class', 'this.Warehouse = class');
    let prints = 0;
    const context = { Controller: class {}, window: {innerWidth:1440,innerHeight:1000,print(){ prints++; }} };
    runInNewContext(source, context);
    const controller = new context.Warehouse();
    const pair = () => {
        const attributes = {};
        const button = {setAttribute: (key,value) => {attributes[key]=value;}, getBoundingClientRect: () => ({left:1300,top:80,bottom:100})};
        const popover = {hidden:true,style:{},offsetWidth:300,offsetHeight:100};
        const parent = {querySelector: selector => selector.includes('stock-button') ? button : popover};
        button.parentElement = popover.parentElement = parent;
        return {button,popover,attributes};
    };
    const one = pair();
    const two = pair();
    controller.popoverTargets = [one.popover,two.popover];
    return {controller,one,two,prints:()=>prints};
}

test('stock popover toggles, closes its predecessor and stays within the viewport', () => {
    const {controller,one,two} = warehouse();
    controller.toggle({currentTarget:one.button});
    assert.equal(one.popover.hidden, false);
    assert.equal(one.attributes['aria-expanded'], 'true');
    assert.equal(one.popover.style.left, '1132px');
    assert.equal(one.popover.style.top, '106px');
    controller.toggle({currentTarget:two.button});
    assert.equal(one.popover.hidden, true);
    assert.equal(two.popover.hidden, false);
    controller.toggle({currentTarget:two.button});
    assert.equal(two.popover.hidden, true);
});

test('inside clicks keep the popover open, outside clicks and Escape action close it', () => {
    const {controller,one} = warehouse();
    controller.toggle({currentTarget:one.button});
    controller.outside({target:{closest:()=>one.popover}});
    assert.equal(one.popover.hidden, false);
    controller.outside({target:{closest:()=>null}});
    assert.equal(one.popover.hidden, true);
    controller.toggle({currentTarget:one.button});
    controller.closeAll();
    assert.equal(one.attributes['aria-expanded'], 'false');
    const template = readFileSync(new URL('../../templates/warehouse/index.html.twig', import.meta.url), 'utf8');
    assert.match(template, /keydown\.esc@window->warehouse#closeAll/);
});

test('inventory invokes native printing and its controller is registered', () => {
    const {controller,prints} = warehouse();
    controller.print();
    assert.equal(prints(), 1);
    const bootstrap = readFileSync(new URL('../../assets/stimulus_bootstrap.js', import.meta.url), 'utf8');
    assert.match(bootstrap, /app\.register\('warehouse', WarehouseController\)/);
});
