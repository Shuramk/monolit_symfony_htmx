import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function editor() {
    const source = readFileSync(new URL('../../assets/controllers/crm_controller.js',import.meta.url),'utf8')
        .replace(/^import .*;$/gm,'').replace('export default class','this.Crm = class');
    const context = { Controller: class {} }; runInNewContext(source,context);
    const controller = new context.Crm();
    const rows = [{fields:[{name:'lines[9][price]'}]}];
    controller.linesTarget = {querySelectorAll:()=>rows.flatMap(row=>row.fields),append:row=>rows.push(row)};
    controller.lineTemplateTarget = {content:{cloneNode:()=>({fields:['productId','quantity','price'].map(field=>({dataset:{field}})),querySelectorAll(){return this.fields;}})}};
    controller.connect();

    return {controller,rows};
}
test('separate contact date and time retain the datetime value submitted to the server', () => {
    const {controller} = editor();
    controller.contactDateTarget = {value:'2026-10-15'};
    controller.contactTimeTarget = {value:'14:26'};
    controller.contactAtTarget = {value:''};
    controller.syncContactAt();
    assert.equal(controller.contactAtTarget.value,'2026-10-15T14:26');
    controller.contactTimeTarget.value = '';
    controller.syncContactAt();
    assert.equal(controller.contactAtTarget.value,'');
});
test('changing customer or stacker updates plain-text information and closes the picker', () => {
    const {controller} = editor();
    const picker = {open:true};
    controller.customerSelectTarget = {selectedOptions:[{dataset:{name:'Customer <fixture>',phone:'(067) 123-45-67'}}],closest:()=>picker};
    controller.customerNameTarget = {textContent:''};
    controller.customerPhoneTarget = {textContent:''};
    controller.customer();
    assert.equal(controller.customerNameTarget.textContent,'Customer <fixture>');
    assert.equal(controller.customerPhoneTarget.textContent,'(067) 123-45-67');
    assert.equal(picker.open,false);
    controller.stackerSelectTarget = {selectedOptions:[{dataset:{name:'',phone:''}}],closest:()=>picker};
    controller.stackerNameTarget = {textContent:'Previous'};
    controller.stackerPhoneTarget = {textContent:'Previous'};
    controller.stacker();
    assert.equal(controller.stackerNameTarget.textContent,'');
    assert.equal(controller.stackerPhoneTarget.textContent,'');
});
test('invalid fields inside collapsed sections reveal all containing details', () => {
    const {controller} = editor();
    const outer = {open:false,parentElement:{closest:()=>null}};
    const inner = {open:false,parentElement:{closest:()=>outer}};
    controller.reveal({target:{closest:()=>inner}});
    assert.equal(inner.open,true);
    assert.equal(outer.open,true);
});
