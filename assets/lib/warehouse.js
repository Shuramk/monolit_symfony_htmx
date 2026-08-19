export async function warehouseRequest(url, payload, token, fetcher = fetch) {
    const response = await fetcher(url, { method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':token},
        body: JSON.stringify(payload) });
    if (response.redirected) throw new Error('Сессия закончилась. Войдите снова и повторите отправку.');
    const data = response.headers.get('Content-Type')?.includes('json') ? await response.json() : null;
    if (!response.ok) throw new Error(data?.message || data?.detail || `Не удалось сохранить (HTTP ${response.status}).`);
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Сервер вернул некорректный ответ.');
    return data;
}
export function minimumPayload(value, version) {
    const quantity = value.trim().replace(',', '.');
    if (!/^(?:\d{1,9}(?:\.\d{1,3})?)?$/.test(quantity)) throw new Error('Введите неотрицательное число, не более трёх знаков после запятой.');
    return {quantity, version: Number(version)};
}
export function selectedProduction(choices) {
    const lines = Array.from(choices).filter(c => c.checked).map(c => ({productId:Number(c.value), quantity:c.dataset.quantity}));
    if (!lines.length) throw new Error('Выберите продукцию.');
    return lines;
}
