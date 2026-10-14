import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

function form() {
    const source = readFileSync(new URL('../../assets/controllers/order_form_controller.js',import.meta.url),'utf8').replace(/^import .*;$/gm,'').replace('export default class','this.Form = class');
    const context = {Controller:class {},Option:class {constructor(name,id){this.text=name;this.value=id;this.dataset={};}}};
    runInNewContext(source,context);
    const c = new context.Form();
    const fields = {quantity:{value:'2'},unitPrice:{value:'100'},discount:{value:'5'},total:{textContent:''},vat:{value:''},fop:{value:''},unit:{textContent:''},product:{selectedOptions:[{dataset:{unit:'м²'}}]}};
    const row = {querySelector(selector) {
        if(selector.includes('[quantity]')) return fields.quantity;
        if(selector.includes('[unitPrice]')) return fields.unitPrice;
        if(selector.includes('[discount]')) return fields.discount;
        if(selector.includes('[productId]')) return fields.product;
        return {'[data-order-line-total]':fields.total,'[data-order-line-vat-price]':fields.vat,'[data-order-line-fop-price]':fields.fop,'[data-order-line-unit]':fields.unit}[selector];
    }};
    c.linesTarget = {querySelectorAll:()=>[row]};
    c.markupModeTarget = {value:'vat'};
    c.markupFopTarget = {value:'10'};
    c.markupTarget = {value:''};
    c.totalTarget = {textContent:''};
    c.totalWithMarkupTarget = {textContent:''};
    c.hasBudgetTarget = c.hasVatAmountTarget = c.hasBalanceTarget = true;
    c.budgetTarget = {value:'',readOnly:false};
    c.vatAmountTarget = {textContent:''};
    c.balanceTarget = {textContent:''};

    return {c,fields,row};
}
test('deal VAT totals, budget and units use the same calculation as orders',()=>{
    const {c,fields} = form();
    c.recalculate();
    assert.equal(fields.total.textContent,'190.00 грн');
    assert.equal(fields.vat.value,'114.0000');
    assert.equal(fields.unit.textContent,'м²');
    assert.equal(c.totalWithMarkupTarget.textContent,'228.00 грн');
    assert.equal(c.vatAmountTarget.textContent,'38.00 грн');
    assert.equal(c.balanceTarget.textContent,'228.00 грн');
    assert.equal(c.budgetTarget.value,'228.00');
    assert.equal(c.budgetTarget.readOnly,true);
});
test('FOP, negative discount and editing a VAT price never double the markup',()=>{
    const {c,fields,row} = form();
    c.markupModeTarget.value = 'fop';
    fields.discount.value = '-5';
    c.recalculate();
    assert.equal(c.totalWithMarkupTarget.textContent,'231.00 грн');
    assert.equal(c.vatAmountTarget.textContent,'0.00 грн');
    c.markupModeTarget.value = 'vat';
    c.vatPriceChanged({currentTarget:{value:'120',closest:()=>row}});
    assert.equal(fields.unitPrice.value,'100.0000');
    assert.equal(fields.discount.value,'0.00');
    assert.equal(c.totalWithMarkupTarget.textContent,'240.00 грн');
});
test('deal permits removing its last product and unlocks the estimated budget',()=>{
    const {c,row} = form();
    let rows = [row];
    c.linesTarget.querySelectorAll = ()=>rows;
    row.remove = ()=>{rows=[];};
    c.allowEmptyValue = true;
    c.recalculate();
    c.remove({currentTarget:{closest:()=>row}});
    assert.equal(c.budgetTarget.readOnly,false);
    assert.equal(c.totalWithMarkupTarget.textContent,'0.00 грн');
});
test('fractional discounts round the net price to the four decimals saved by the server',()=>{
    const {c,fields} = form();
    c.markupModeTarget.value = '0';
    fields.quantity.value = '800';
    fields.discount.value = '-2.45793';
    c.recalculate();
    assert.equal(c.budgetTarget.value,'81966.32');
});
test('adding products retains unique indices after earlier rows have been removed',()=>{
    const {c} = form();
    c.hasBudgetTarget = false;
    c.index = 5;
    c.templateTarget = {innerHTML:'lines[__INDEX__][productId]'};
    const added = [];
    c.linesTarget.insertAdjacentHTML = (_position,html)=>added.push(html);
    c.updateMarkupColumns = ()=>{};
    c.add(); c.add();
    assert.deepEqual(added,['lines[5][productId]','lines[6][productId]']);
});
test('choosing a group and variant fills the price and resets the previous discount',()=>{
    const {c,fields,row} = form();
    c.productsValue = {4:[{id:7,name:'Серый <fixture>',price:'123.4500',unit:'м²'}]};
    const options = [];
    fields.product.add = option=>options.push(option);
    c.groupChanged({currentTarget:{value:'4',closest:()=>row}});
    assert.equal(options[0].text,'Серый <fixture>');
    assert.equal(options[0].dataset.unit,'м²');
    assert.equal(fields.unitPrice.value,'0.0000');
    fields.product.selectedOptions = [options[0]];
    c.productChanged({currentTarget:{selectedOptions:[options[0]],closest:()=>row}});
    assert.equal(fields.unitPrice.value,'123.4500');
    assert.equal(fields.discount.value,'0.00');
});
