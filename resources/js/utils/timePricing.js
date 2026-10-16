import { formatMoney } from '@/utils/format';

// Same rule as CatalogItem::priceForMinutes(): the base price covers the first
// base_minutes, every started step after that costs step_price.
export const extraSteps = (item, minutes) =>
    Math.ceil(Math.max(0, minutes - item.base_minutes) / Math.max(1, item.step_minutes));

export const priceForMinutes = (item, minutes) =>
    Math.round((Number(item.unit_price) + extraSteps(item, minutes) * Number(item.step_price)) * 100) / 100;

// 80 -> "1 Std. 20 Min."
export const formatDuration = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return [hours ? `${hours} Std.` : '', rest || !hours ? `${rest} Min.` : ''].filter(Boolean).join(' ');
};

// "80", "1:20", "1h 20", "1,5h" -> minutes (null if invalid)
export const parseDuration = (input) => {
    const value = String(input ?? '').trim().toLowerCase().replace(',', '.');
    if (!value) return null;

    const clock = value.match(/^(\d+):(\d{1,2})$/);
    if (clock) return Number(clock[1]) * 60 + Number(clock[2]);

    const hoursMinutes = value.match(/^(\d+(?:\.\d+)?)\s*h(?:\s*(\d+)\s*(?:m|min)?)?$/);
    if (hoursMinutes) return Math.round(Number(hoursMinutes[1]) * 60) + Number(hoursMinutes[2] ?? 0);

    const minutes = value.match(/^(\d+)\s*(?:m|min)?$/);
    return minutes ? Number(minutes[1]) : null;
};

// "erste 30 Min. 50,00 € + 4 × 15 Min. à 20,00 €"
export const breakdown = (item, minutes) => {
    const steps = extraSteps(item, minutes);
    const base = `erste ${item.base_minutes} Min. ${formatMoney(item.unit_price)}`;
    return steps ? `${base} + ${steps} × ${item.step_minutes} Min. à ${formatMoney(item.step_price)}` : base;
};

// Short price label for lists: "50,00 € / 30 Min. + 20,00 € je 15 Min."
export const rateLabel = (item) =>
    `${formatMoney(item.unit_price)} / ${item.base_minutes} Min. + ${formatMoney(item.step_price)} je ${item.step_minutes} Min.`;

// Reverse calculation: which duration does an amount pay for?
// Returns the full duration covered (e.g. 130 € -> 90 Min.) or, if the amount
// doesn't fit the rate, the nearest valid amounts below/above.
export const minutesForPrice = (item, amount) => {
    const base = Number(item.unit_price);
    const step = Number(item.step_price);
    const value = Math.round(Number(amount) * 100) / 100;

    if (!(value > 0)) return null;
    if (value <= base) return { minutes: item.base_minutes, exact: value === base, lower: null, upper: base };
    if (!(step > 0)) return { minutes: null, exact: false, lower: base, upper: null };

    const steps = (value - base) / step;
    const whole = Math.round(steps);

    if (Math.abs(steps - whole) < 0.001) {
        return { minutes: item.base_minutes + whole * item.step_minutes, exact: true, lower: null, upper: null };
    }

    return {
        minutes: null,
        exact: false,
        lower: Math.round((base + Math.floor(steps) * step) * 100) / 100,
        upper: Math.round((base + Math.ceil(steps) * step) * 100) / 100,
    };
};
