const maximumPrice = 999999999999n;

function scaled(value, signed = false) {
    const normalized = String(value).trim().replace(',', '.').replace('−', '-');
    const match = normalized.match(signed ? /^([+-]?)(\d{1,8})(?:\.(\d{1,4}))?$/ : /^()(\d{1,8})(?:\.(\d{1,4}))?$/);
    if (!match) throw new Error('Введите значение с точностью до 4 знаков после запятой.');
    const amount = BigInt(match[2]) * 10000n + BigInt((match[3] ?? '').padEnd(4, '0'));
    return match[1] === '-' ? -amount : amount;
}

// Compute the entire preview first: an invalid price must not leave half the table changed.
export function adjustPrices(prices, delta, mode = 'amount') {
    if (!['amount', 'percent'].includes(mode)) throw new Error('Выберите изменение в гривнах или процентах.');
    const adjustment = scaled(delta, true);
    const denominator = 1000000n; // 100 percent, with four decimal places.
    const factor = denominator + adjustment;
    if (mode === 'percent' && factor < 0n) throw new Error('Снижение цены не может превышать 100%.');
    return prices.map((price) => {
        const current = scaled(price);
        const result = mode === 'percent'
            ? (current * factor + denominator / 2n) / denominator
            : current + adjustment;
        if (result < 0n || result > maximumPrice) throw new Error('После изменения все цены должны быть от 0 до 99999999.9999 грн.');
        return `${result / 10000n}.${String(result % 10000n).padStart(4, '0')}`;
    });
}
