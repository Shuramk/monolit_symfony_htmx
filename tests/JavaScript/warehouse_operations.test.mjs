import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
const source=readFileSync(new URL('../../assets/lib/warehouse.js',import.meta.url),'utf8').replace(/export /g,'');
const ctx={};runInNewContext(source,ctx);
test('minimum supports comma, empty clearing, zero and three decimal places',()=>{
    assert.equal(JSON.stringify(ctx.minimumPayload('12,125','7')),JSON.stringify({quantity:'12.125',version:7}));
    assert.equal(ctx.minimumPayload('',0).quantity,'');
    assert.equal(ctx.minimumPayload('0',1).quantity,'0');
    for(const bad of ['-1','NaN','1e3','1.2345','1000000000']) assert.throws(()=>ctx.minimumPayload(bad,1));
});
test('production selection is compact and keeps exact quantity strings',()=>{
    const choices=[{checked:false,value:'1',dataset:{quantity:'9.000'}},{checked:true,value:'2',dataset:{quantity:'1.125'}}];
    assert.equal(JSON.stringify(ctx.selectedProduction(choices)),JSON.stringify([{productId:2,quantity:'1.125'}]));
    assert.throws(()=>ctx.selectedProduction([]),/Выберите/);
});
test('JSON transport includes session CSRF and accepts operation response without redirect',async()=>{
    let request;
    const fetcher=async(url,options)=>{request={url,...options};return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({id:5,warnings:[]})};};
    const result=await ctx.warehouseRequest('/warehouse/variants/1/add',{quantity:'1.001'},'token',fetcher);
    assert.equal(result.id,5);assert.equal(request.credentials,'same-origin');
    assert.equal(request.headers['X-CSRF-Token'],'token');
    assert.equal(JSON.parse(request.body).quantity,'1.001');
});
test('failed, non-JSON and expired-session responses never count as successful save',async()=>{
    await assert.rejects(ctx.warehouseRequest('/x',{},'t',async()=>({ok:false,status:409,headers:{get:()=> 'application/json'},json:async()=>({message:'Конфликт'})})),/Конфликт/);
    await assert.rejects(ctx.warehouseRequest('/x',{},'t',async()=>({ok:true,headers:{get:()=> 'text/html'}})),/некорректный/);
    await assert.rejects(ctx.warehouseRequest('/x',{},'t',async()=>({redirected:true})),/Сессия/);
});
