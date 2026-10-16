// Same rules as App\Support\Amount: "12,50", "1.234,50", "5,92+6,5+24", "200-172,62"

const parseNumber = (number) => Number(number.includes(',') ? number.replaceAll('.', '').replace(',', '.') : number);

/**
 * Sum of an amount input, or null when it isn't valid (yet).
 */
export const parseAmount = (input) => {
    const value = String(input ?? '').replace(/[\s€]/g, '').replace(/^=/, '');
    if (!value || !/^[+-]?[\d.,]+([+-][\d.,]+)*$/.test(value)) return null;

    let sum = 0;
    for (const [, sign, number] of value.matchAll(/([+-]?)([\d.,]+)/g)) {
        const parsed = parseNumber(number);
        if (Number.isNaN(parsed)) return null;
        sum += sign === '-' ? -parsed : parsed;
    }

    return Math.round(sum * 100) / 100;
};

export const isFormula = (input) => /\d\s*[+-]\s*\d/.test(String(input ?? ''));

export const toAmountInput = (value) => value === null || value === undefined || value === '' ? '' : String(Number(value)).replace('.', ',');
