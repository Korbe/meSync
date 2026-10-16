<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ArchiveBoxArrowDownIcon, CalendarDaysIcon, ReceiptPercentIcon, DocumentTextIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VInput from '@/components/VInput.vue';
import PaymentStatusBadge from '@/Pages/Invoices/PaymentStatusBadge.vue';
import { formatDate, formatMoney, toIsoDate } from '@/utils/format';

const props = defineProps({
    from: String,
    to: String,
    summary: Array,
    invoices: Array,
    paymentsTotal: Number,
    paymentsCount: Number,
});

const from = ref(props.from);
const to = ref(props.to);

const load = (range) => {
    from.value = range.from;
    to.value = range.to;
    router.get(route('export.index'), range, { preserveState: true, preserveScroll: true, replace: true });
};

// Preset periods
const now = new Date();
const quarterStart = (year, quarter) => new Date(year, quarter * 3, 1);
const quarterEnd = (year, quarter) => new Date(year, quarter * 3 + 3, 0);
const currentQuarter = Math.floor(now.getMonth() / 3);
const lastQuarterYear = currentQuarter === 0 ? now.getFullYear() - 1 : now.getFullYear();
const lastQuarter = (currentQuarter + 3) % 4;

const presets = [
    { label: 'Dieser Monat', from: new Date(now.getFullYear(), now.getMonth(), 1), to: new Date(now.getFullYear(), now.getMonth() + 1, 0) },
    { label: 'Letzter Monat', from: new Date(now.getFullYear(), now.getMonth() - 1, 1), to: new Date(now.getFullYear(), now.getMonth(), 0) },
    { label: `Q${currentQuarter + 1} ${now.getFullYear()}`, from: quarterStart(now.getFullYear(), currentQuarter), to: quarterEnd(now.getFullYear(), currentQuarter) },
    { label: `Q${lastQuarter + 1} ${lastQuarterYear}`, from: quarterStart(lastQuarterYear, lastQuarter), to: quarterEnd(lastQuarterYear, lastQuarter) },
    { label: `Jahr ${now.getFullYear()}`, from: new Date(now.getFullYear(), 0, 1), to: new Date(now.getFullYear(), 11, 31) },
    { label: `Jahr ${now.getFullYear() - 1}`, from: new Date(now.getFullYear() - 1, 0, 1), to: new Date(now.getFullYear() - 1, 11, 31) },
].map(preset => ({ label: preset.label, from: toIsoDate(preset.from), to: toIsoDate(preset.to) }));

const isActive = (preset) => preset.from === props.from && preset.to === props.to;

const totals = computed(() => ({
    net: props.summary.reduce((sum, line) => sum + line.net, 0),
    tax: props.summary.reduce((sum, line) => sum + line.tax, 0),
    gross: props.summary.reduce((sum, line) => sum + line.gross, 0),
}));

const query = computed(() => ({ from: props.from, to: props.to }));
</script>

<template>
    <Layout title="Export">
        <p class="-mt-4 mb-6 max-w-2xl text-gray-500">Rechnungen und Zahlungseingänge eines Zeitraums für den Steuerberater, als CSV-Datei oder als ZIP-Paket mit allen Rechnungs-PDFs.</p>

        <!-- Period -->
        <section class="card mb-6 p-6">
            <h2 class="card-title"><CalendarDaysIcon class="h-5 w-5 text-primary-500" />Zeitraum</h2>

            <div class="mt-4 flex flex-wrap gap-2">
                <button v-for="preset in presets" :key="preset.label" type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition"
                    :class="isActive(preset)
                        ? 'bg-primary-500 text-white ring-primary-500 shadow-[0_6px_16px_-6px_rgba(31,173,174,0.8)]'
                        : 'text-gray-600 ring-gray-200 hover:ring-primary-400 hover:text-primary-600 dark:text-gray-400 dark:ring-white/10 dark:hover:text-primary-300'"
                    @click="load(preset)">
                    {{ preset.label }}
                </button>
            </div>

            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="load({ from, to })">
                <VInput id="from" type="date" label="Von" v-model="from" class="w-44" />
                <VInput id="to" type="date" label="Bis" v-model="to" class="w-44" />
                <button type="submit" class="btn cursor-pointer border-gray-200! bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10! dark:bg-white/5 dark:text-gray-200">Anzeigen</button>
            </form>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Summary + downloads -->
            <section class="card relative overflow-hidden p-6 xl:col-span-1">
                <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="card-title"><ReceiptPercentIcon class="h-5 w-5 text-primary-500" />USt.-Übersicht</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ formatDate(from) }} – {{ formatDate(to) }}</p>

                    <table class="mt-5 w-full text-sm">
                        <thead class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">
                            <tr><th class="pb-2 text-left font-medium">Satz</th><th class="pb-2 text-right font-medium">Netto</th><th class="pb-2 text-right font-medium">USt.</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                            <tr v-for="line in summary" :key="line.rate">
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ line.rate }} % <span class="text-xs text-gray-500">({{ line.count }})</span></td>
                                <td class="py-2 text-right text-gray-900 dark:text-gray-100">{{ formatMoney(line.net) }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-gray-100">{{ formatMoney(line.tax) }}</td>
                            </tr>
                            <tr v-if="summary.length === 0"><td colspan="3" class="py-3 text-gray-500">Keine Rechnungen im Zeitraum.</td></tr>
                        </tbody>
                        <tfoot v-if="summary.length" class="border-t border-gray-300 font-semibold text-gray-900 dark:border-white/20 dark:text-white">
                            <tr><td class="pt-2">Gesamt</td><td class="pt-2 text-right">{{ formatMoney(totals.net) }}</td><td class="pt-2 text-right">{{ formatMoney(totals.tax) }}</td></tr>
                        </tfoot>
                    </table>

                    <div class="mt-4 rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/[0.03]">
                        <div class="flex justify-between"><span class="text-gray-500">Brutto gesamt</span><span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(totals.gross) }}</span></div>
                        <div class="mt-1 flex justify-between"><span class="text-gray-500">Zahlungseingänge ({{ paymentsCount }})</span><span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(paymentsTotal) }}</span></div>
                    </div>

                    <!-- Downloads: plain links, files are generated on request and not stored -->
                    <div class="mt-6 space-y-3">
                        <a :href="route('export.zip', query)" class="btn w-full cursor-pointer bg-primary-500 text-white hover:bg-primary-600 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)]">
                            <ArchiveBoxArrowDownIcon class="mr-2 h-5 w-5" />ZIP für den Steuerberater
                        </a>
                        <a :href="route('export.csv', query)" class="btn w-full cursor-pointer border-gray-200! bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10! dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                            <ArrowDownTrayIcon class="mr-2 h-5 w-5" />Nur Rechnungen als CSV
                        </a>
                    </div>

                    <p class="mt-4 flex gap-2 text-xs text-gray-500">
                        <InformationCircleIcon class="h-4 w-4 shrink-0" />
                        Das ZIP enthält rechnungen.csv, zahlungen.csv, ust-uebersicht.csv und alle Rechnungen als PDF. Die Dateien öffnen direkt in Excel.
                    </p>
                </div>
            </section>

            <!-- Invoices in the period -->
            <section class="card overflow-hidden xl:col-span-2">
                <div class="p-6 pb-3">
                    <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Enthaltene Rechnungen ({{ invoices.length }})</h2>
                </div>
                <p v-if="invoices.length === 0" class="px-6 pb-6 text-sm text-gray-500">In diesem Zeitraum gibt es keine Rechnungen.</p>
                <table v-else class="w-full text-left text-sm">
                    <thead class="border-y border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                        <tr>
                            <th class="px-6 py-2.5 font-medium">Nummer</th>
                            <th class="px-6 py-2.5 font-medium">Datum</th>
                            <th class="hidden px-6 py-2.5 font-medium md:table-cell">Kunde</th>
                            <th class="px-6 py-2.5 text-right font-medium">Netto</th>
                            <th class="hidden px-6 py-2.5 font-medium sm:table-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <tr v-for="invoice in invoices" :key="invoice.id" class="cursor-pointer transition hover:bg-primary-500/[0.04]" @click="router.visit(route('invoices.edit', invoice.id))">
                            <td class="px-6 py-2.5 font-mono font-medium text-gray-900 dark:text-gray-100">{{ invoice.number }}</td>
                            <td class="px-6 py-2.5 text-gray-600 dark:text-gray-400">{{ formatDate(invoice.invoice_date) }}</td>
                            <td class="hidden truncate px-6 py-2.5 text-gray-700 md:table-cell dark:text-gray-300">{{ invoice.recipient }}</td>
                            <td class="px-6 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ formatMoney(invoice.subtotal) }}</td>
                            <td class="hidden px-6 py-2.5 sm:table-cell"><PaymentStatusBadge :status="invoice.payment_status" /></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </Layout>
</template>
