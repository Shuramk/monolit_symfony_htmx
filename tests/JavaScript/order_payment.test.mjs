import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../assets/lib/order_payment.js', import.meta.url), 'utf8');
const { paymentPayload } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));

test('payment payload keeps exact decimal strings, accepts comma and explicit checkbox state', () => {
    assert.deepEqual(paymentPayload(' 123,45 ', true, 'uuid'), {amount:'123.45',toCard:true,operationId:'uuid'});
    assert.deepEqual(paymentPayload('9999999999.99', undefined, 'uuid'), {amount:'9999999999.99',toCard:false,operationId:'uuid'});
    assert.equal(paymentPayload('0.01', false, 'uuid').amount, '0.01');
});
test('invalid payment amounts cannot be silently rounded or submitted', () => {
    for (const amount of ['','0','0.00','-1','1.001','10000000000','1e3','abc']) {
        assert.throws(() => paymentPayload(amount, false, 'uuid'));
    }
});
