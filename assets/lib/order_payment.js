export function paymentPayload(amount, toCard, operationId) {
    const normalized = String(amount).trim().replace(',', '.');
    if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(normalized) || !/[1-9]/.test(normalized)) {
        throw new Error('Введите положительную сумму: до 10 целых и 2 дробных знаков.');
    }
    return {amount: normalized, toCard: Boolean(toCard), operationId};
}
