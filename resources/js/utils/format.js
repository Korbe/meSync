const euro = new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' });

export const formatMoney = (value) => euro.format(Number(value ?? 0));

// "2026-03-15" -> "15.03.2026" (no Date parsing, so no timezone shifts)
export const formatDate = (value) => {
    if (!value) return '';
    const [year, month, day] = value.slice(0, 10).split('-');
    return `${day}.${month}.${year}`;
};

export const toDate = (value) => {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day);
};

export const toIsoDate = (date) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

export const today = () => toIsoDate(new Date());

// Whole days from today until the given date (negative = in the past)
export const daysUntil = (value) => {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    return Math.round((toDate(value) - start) / 86_400_000);
};

export const relativeDays = (value) => {
    const days = daysUntil(value);

    if (days === 0) return 'heute';
    if (days === 1) return 'morgen';
    if (days === -1) return 'seit gestern';
    if (days < 0) return `seit ${-days} Tagen`;
    if (days < 60) return `in ${days} Tagen`;

    const months = Math.round(days / 30);
    return `in ${months} Monaten`;
};

// ISO timestamp (UTC from Laravel) -> "15.10.2026, 14:05"
export const formatDateTime = (value) => {
    if (!value) return '';
    return new Date(value).toLocaleString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ISO timestamp -> "vor 3 Min." / "vor 2 Std." / "vor 4 Tagen"
export const timeAgo = (value) => {
    if (!value) return '';
    const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);

    if (minutes < 1) return 'gerade eben';
    if (minutes < 60) return `vor ${minutes} Min.`;
    if (minutes < 60 * 24) return `vor ${Math.round(minutes / 60)} Std.`;
    return `vor ${Math.round(minutes / (60 * 24))} Tagen`;
};

// Whole days until an ISO timestamp (negative = in the past)
export const daysUntilTimestamp = (value) => Math.floor((new Date(value).getTime() - Date.now()) / 86_400_000);
