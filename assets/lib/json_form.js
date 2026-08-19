export function validationMessage(validity, constraints = {}) {
    if (validity.valueMissing) return "Заполните это поле.";
    if (validity.badInput) return "Введите корректное число.";
    if (validity.typeMismatch) return "Введите значение в правильном формате.";
    if (validity.rangeUnderflow) return `Значение должно быть не меньше ${constraints.min}.`;
    if (validity.rangeOverflow) return `Значение должно быть не больше ${constraints.max}.`;
    if (validity.stepMismatch) return `Введите значение с шагом ${constraints.step}.`;
    if (validity.tooShort) return `Введите не менее ${constraints.minLength} символов.`;
    if (validity.tooLong) return `Введите не более ${constraints.maxLength} символов.`;
    if (validity.patternMismatch) return "Введите значение в правильном формате.";

    return validity.valid ? "" : "Проверьте введённое значение.";
}

export function serializeForm(entries) {
    const fields = new Map();
    const collections = new Map();

    for (const [name, value] of entries) {
        if (name === "_token") continue;

        const match = name.match(/^([a-zA-Z]\w*)\[(\d+)\]\[([a-zA-Z]\w*)\]$/);
        if (!match) {
            fields.set(name, value);
            continue;
        }

        const [, collection, index, field] = match;
        if (!collections.has(collection)) collections.set(collection, new Map());
        const rows = collections.get(collection);
        if (!rows.has(index)) rows.set(index, new Map());
        rows.get(index).set(field, value);
    }

    for (const [name, rows] of collections) {
        fields.set(name, Array.from(rows.values(), (row) => Object.fromEntries(row)));
    }

    return Object.fromEntries(fields);
}

export async function postJson(url, payload, csrfToken, fetchImplementation = fetch) {
    const response = await fetchImplementation(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify(payload),
    });

    if (response.redirected) return { location: response.url };

    const data = response.headers.get("Content-Type")?.includes("json")
        ? await response.json()
        : null;

    if (!response.ok) {
        throw new Error(typeof data?.message === "string" ? data.message : `Не удалось отправить форму (HTTP ${response.status}).`);
    }
    if (typeof data?.location !== "string" || data.location === "") {
        throw new Error("Сервер не вернул адрес следующего экрана.");
    }

    return data;
}
