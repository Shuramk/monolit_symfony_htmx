import { test } from "node:test";
import assert from "node:assert/strict";
import { serializeForm, postJson, validationMessage } from "../../assets/lib/json_form.js";

test("native numeric and required validation messages are Russian", () => {
    assert.equal(validationMessage({ rangeUnderflow: true }, { min: "0.001" }), "Значение должно быть не меньше 0.001.");
    assert.equal(validationMessage({ rangeOverflow: true }, { max: "100" }), "Значение должно быть не больше 100.");
    assert.equal(validationMessage({ stepMismatch: true }, { step: "0.01" }), "Введите значение с шагом 0.01.");
    assert.equal(validationMessage({ valueMissing: true }), "Заполните это поле.");
    assert.equal(validationMessage({ badInput: true }), "Введите корректное число.");
    assert.equal(validationMessage({ valid: true }), "");
});

test("product rows become a compact array while exact decimals stay strings", () => {
    assert.deepEqual(serializeForm([
        ["_token", "secret"], ["markup", "10"],
        ["lines[0][productId]", "15"], ["lines[0][quantity]", "2.500"], ["lines[0][unitPrice]", "100.0000"],
        ["lines[3][productId]", "16"], ["lines[3][quantity]", "1.000"],
    ]), {
        markup: "10", lines: [
            { productId: "15", quantity: "2.500", unitPrice: "100.0000" },
            { productId: "16", quantity: "1.000" },
        ],
    });
});

test("customer step sends only customer fields and preserves text", () => {
    assert.deepEqual(serializeForm([["customerId", "19"], ["shippingCost", "25.50"], ["comments", "<тест>"], ["_token", "secret"]]), {
        customerId: "19", shippingCost: "25.50", comments: "<тест>",
    });
});

test("JSON transport sends credentials and CSRF header and returns next screen", async () => {
    const payload = { markup: "10", lines: [{ productId: "15", quantity: "2.500" }] };
    const data = await postJson("/orders/draft", payload, "csrf", async (url, options) => {
        assert.equal(url, "/orders/draft");
        assert.equal(options.method, "POST");
        assert.equal(options.credentials, "same-origin");
        assert.equal(options.headers["Content-Type"], "application/json");
        assert.equal(options.headers["X-CSRF-Token"], "csrf");
        assert.deepEqual(JSON.parse(options.body), payload);
        return new Response(JSON.stringify({ location: "/orders/new/customer" }), { headers: { "Content-Type": "application/json" } });
    });
    assert.equal(data.location, "/orders/new/customer");
});

test("validation and CSRF messages propagate without altering submitted values", async () => {
    const payload = { shippingCost: "-1" };
    for (const status of [400, 403]) {
        await assert.rejects(postJson("/orders", payload, "csrf", async () => new Response(JSON.stringify({ message: "Ошибка формы" }), {
            status, headers: { "Content-Type": "application/json" },
        })), /Ошибка формы/);
    }
    assert.deepEqual(payload, { shippingCost: "-1" });
});

test("unexpected non-JSON response and missing location do not count as success", async () => {
    await assert.rejects(postJson("/orders", {}, "csrf", async () => new Response("Internal error", { status: 500 })), /HTTP 500/);
    await assert.rejects(postJson("/orders", {}, "csrf", async () => new Response("{}", { headers: { "Content-Type": "application/json" } })), /не вернул адрес/);
});

test("expired session redirect leads to login", async () => {
    const data = await postJson("/orders", {}, "csrf", async () => ({ redirected: true, url: "http://localhost/login" }));
    assert.equal(data.location, "http://localhost/login");
});
