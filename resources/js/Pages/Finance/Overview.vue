<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowTrendingDownIcon, ArrowTrendingUpIcon, BanknotesIcon, ChevronRightIcon, LockClosedIcon, ScaleIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import FinanceHeader from './FinanceHeader.vue';
import { formatMoney } from '@/utils/format';

const props = defineProps({
    overview: Object,
    years: Array,
    isEmpty: Boolean,
});

const monthNames = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
const now = new Date();
const currentMonth = props.overview.year === now.getFullYear() ? now.getMonth() : -1;

// Months still to come only show what the contracts will bring (plan values)
const isFuture = (index) => props.overview.year > now.getFullYear() || (props.overview.year === now.getFullYear() && index > now.getMonth());
const monthKey = (index) => `${props.overview.year}-${String(index + 1).padStart(2, '0')}`;
const openTotal = computed(() => props.overview.open.reduce((sum, count) => sum + count, 0));
const monthClass = (index) => [index === currentMonth && 'bg-primary-500/[0.04]', isFuture(index) && 'opacity-45'];

const summary = computed(() => props.overview.summary);
const savingsRate = computed(() => summary.value.totals.income > 0 ? Math.round(summary.value.totals.surplus / summary.value.totals.income * 100) : null);

const cards = computed(() => [
    { label: 'Einnahmen', value: summary.value.totals.income, icon: ArrowTrendingUpIcon, tone: 'text-primary-600 dark:text-primary-300' },
    { label: 'Fixkosten', value: summary.value.totals.fixed, icon: BanknotesIcon, tone: 'text-gray-900 dark:text-white', warning: openTotal.value ? `${openTotal.value} Abbuchungen noch nicht bestätigt` : null },
    { label: 'Variable Ausgaben', value: summary.value.totals.variable, icon: ArrowTrendingDownIcon, tone: 'text-gray-900 dark:text-white' },
    { label: 'Überschuss', value: summary.value.totals.surplus, icon: ScaleIcon, tone: summary.value.totals.surplus >= 0 ? 'text-primary-600 dark:text-primary-300' : 'text-red-500', hint: savingsRate.value !== null ? `${savingsRate.value} % der Einnahmen` : null },
]);

// Bar chart: income vs. expenses per month
const chartMax = computed(() => Math.max(1, ...summary.value.income, ...summary.value.expenses));
const barHeight = (value) => `${Math.max(value > 0 ? 2 : 0, value / chartMax.value * 100)}%`;

// Expanded categories, open by default for the fixed costs (few rows, like the spreadsheet)
const expanded = ref(new Set(props.overview.groups.find(group => group.key === 'fixed')?.categories.map(category => `fixed:${category.category}`) ?? []));
const toggle = (key) => {
    const set = new Set(expanded.value);
    set.has(key) ? set.delete(key) : set.add(key);
    expanded.value = set;
};

const cell = (value) => value ? formatMoney(value).replace(/\s?€/, '') : '';
const percentOfIncome = (values) => values.map((value, index) => summary.value.income[index] > 0 && value ? `${Math.round(value / summary.value.income[index] * 100)} %` : '');

const sourceHint = { invoices: 'Automatisch aus den Zahlungseingängen deiner Rechnungen', inventory: 'Automatisch aus dem Inventar (Kaufpreis brutto)' };

const changeYear = (year) => router.get(route('finance.index'), { year, ...(props.overview.scope === 'all' ? {} : { scope: props.overview.scope }) }, { preserveScroll: true });
</script>

<template>
    <Layout title="Finanzen">
        <template v-slot:actions>
            <select :value="overview.year" class="form-select" @change="changeYear($event.target.value)">
                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
            <VButton :href="route('finance.entries.index', overview.scope === 'all' ? {} : { scope: overview.scope })" icon="PlusIcon">Buchen</VButton>
        </template>

        <FinanceHeader active="overview" :scope="overview.scope" :query="{ year: overview.year }" />

        <!-- First start -->
        <div v-if="isEmpty" class="card relative mb-6 overflow-hidden p-6">
            <div class="pointer-events-none absolute -top-20 -right-10 h-48 w-72 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <SparklesIcon class="h-6 w-6 text-primary-500" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white">Dein Haushaltsbuch – privat und für die Firma</h2>
                    <p class="mt-1 text-sm text-gray-500">Leg zuerst deine Verträge an (Miete, Versicherungen, Abos, Gehalt). Die rechnet meSync jeden Monat automatisch ein, du buchst nur noch die variablen Ausgaben.</p>
                </div>
                <VButton :href="route('finance.contracts.create')" icon="PlusIcon">Ersten Vertrag anlegen</VButton>
            </div>
        </div>

        <!-- Totals -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="card in cards" :key="card.label" class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ card.label }} {{ overview.year }}</span>
                    <component :is="card.icon" class="h-5 w-5 text-gray-400" />
                </div>
                <div class="mt-1.5 font-display text-2xl font-semibold tracking-tight" :class="card.tone">{{ formatMoney(card.value) }}</div>
                <div v-if="card.hint" class="mt-0.5 text-xs text-gray-500">{{ card.hint }}</div>
                <div v-if="card.warning" class="mt-0.5 text-xs font-medium text-accent">{{ card.warning }}</div>
            </div>
        </div>

        <!-- Chart -->
        <section class="card mb-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">Monat für Monat</h2>
                <div class="flex gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary-500"></span>Einnahmen</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-gray-400 dark:bg-gray-500"></span>Ausgaben</span>
                </div>
            </div>
            <div class="mt-5 grid h-44 grid-cols-12 items-end gap-1.5 sm:gap-3">
                <div v-for="(month, index) in monthNames" :key="month" class="flex h-full flex-col">
                    <div class="flex flex-1 items-end justify-center gap-0.5 sm:gap-1" :class="isFuture(index) && 'opacity-40'"
                        :title="`${month}: Einnahmen ${formatMoney(summary.income[index])}, Ausgaben ${formatMoney(summary.expenses[index])}, Überschuss ${formatMoney(summary.surplus[index])}`">
                        <div class="w-1/2 max-w-4 rounded-t bg-primary-500 transition-all" :style="{ height: barHeight(summary.income[index]) }"></div>
                        <div class="w-1/2 max-w-4 rounded-t bg-gray-300 transition-all dark:bg-gray-600" :style="{ height: barHeight(summary.expenses[index]) }"></div>
                    </div>
                    <div class="mt-1.5 text-center font-mono text-[10px] uppercase" :class="index === currentMonth ? 'font-bold text-primary-600 dark:text-primary-300' : 'text-gray-500'">{{ month }}</div>
                    <div class="hidden text-center text-[10px] font-medium sm:block" :class="summary.surplus[index] < 0 ? 'text-red-500' : 'text-gray-500'">
                        {{ summary.income[index] || summary.expenses[index] ? Math.round(summary.surplus[index]).toLocaleString('de-AT') : '' }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Spreadsheet -->
        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-right text-[13px] tabular-nums">
                    <thead class="border-b border-gray-200 font-mono text-[10px] uppercase tracking-[0.15em] text-gray-500 dark:border-white/[0.08]">
                        <tr>
                            <th class="sticky left-0 z-10 bg-white px-4 py-3 text-left font-medium dark:bg-[#121212]">{{ overview.year }}</th>
                            <th v-for="(month, index) in monthNames" :key="month" class="px-2 py-3 font-medium" :class="[index === currentMonth && 'text-primary-600 dark:text-primary-300', isFuture(index) && 'opacity-60']">
                                {{ month }}<span v-if="isFuture(index)" class="block text-[9px] normal-case tracking-normal">Plan</span>
                                <Link v-else-if="overview.open[index]" :href="route('finance.entries.index', { month: monthKey(index), ...(overview.scope === 'all' ? {} : { scope: overview.scope }) })"
                                    class="mt-0.5 block text-[9px] font-semibold normal-case tracking-normal text-accent hover:underline" :title="`${overview.open[index]} Verträge noch nicht bestätigt`">
                                    {{ overview.open[index] }} offen
                                </Link>
                            </th>
                            <th class="px-4 py-3 font-medium">Gesamt</th>
                        </tr>
                    </thead>

                    <tbody v-for="group in overview.groups" :key="group.key" class="border-b border-gray-200 dark:border-white/[0.08]">
                        <tr>
                            <td colspan="14" class="sticky left-0 bg-white px-4 pt-5 pb-2 text-left font-display text-base font-semibold text-gray-900 dark:bg-[#121212] dark:text-white">{{ group.label }}</td>
                        </tr>
                        <tr v-if="group.categories.length === 0">
                            <td colspan="14" class="sticky left-0 bg-white px-4 pb-4 text-left text-sm text-gray-500 dark:bg-[#121212]">Noch nichts erfasst.</td>
                        </tr>

                        <template v-for="category in group.categories" :key="category.category">
                            <tr class="cursor-pointer transition hover:bg-primary-500/[0.04]" @click="toggle(`${group.key}:${category.category}`)">
                                <td class="sticky left-0 z-10 bg-white px-4 py-2 text-left font-medium text-gray-800 dark:bg-[#121212] dark:text-gray-200">
                                    <ChevronRightIcon class="mr-1 inline h-3.5 w-3.5 text-gray-400 transition" :class="expanded.has(`${group.key}:${category.category}`) && 'rotate-90'" />
                                    {{ category.label }}
                                    <span class="ml-1 text-xs font-normal text-gray-400">({{ category.lines.length }})</span>
                                </td>
                                <td v-for="(value, index) in category.values" :key="index" class="px-2 py-2 font-medium text-gray-800 dark:text-gray-200" :class="monthClass(index)">{{ cell(value) }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-900 dark:text-white">{{ cell(category.total) }}</td>
                            </tr>
                            <template v-if="expanded.has(`${group.key}:${category.category}`)">
                                <tr v-for="line in category.lines" :key="`${line.source}:${line.label}:${line.scope}`" class="text-gray-500 dark:text-gray-400">
                                    <td class="sticky left-0 z-10 max-w-[260px] truncate bg-white py-1.5 pr-4 pl-10 text-left dark:bg-[#121212]" :title="sourceHint[line.source] ?? line.label">
                                        <LockClosedIcon v-if="sourceHint[line.source]" class="mr-1 inline h-3 w-3" />
                                        <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full align-middle" :class="line.scope === 'business' ? 'bg-primary-500' : 'bg-accent'"></span>
                                        {{ line.label }}
                                        <span v-if="line.count > 1" class="text-xs text-gray-400">· {{ line.count }}×</span>
                                    </td>
                                    <td v-for="(value, index) in line.values" :key="index" class="px-2 py-1.5" :class="monthClass(index)">
                                        <span v-if="line.status?.[index] === 'open'" class="cursor-help text-accent underline decoration-accent/50 decoration-dotted underline-offset-2" title="Noch nicht mit dem Kontoauszug bestätigt – gerechnet mit dem geplanten Betrag">{{ cell(value) }}</span>
                                        <template v-else>{{ cell(value) }}</template>
                                    </td>
                                    <td class="px-4 py-1.5">{{ cell(line.total) }}</td>
                                </tr>
                            </template>
                        </template>

                        <tr v-if="group.categories.length" class="border-t border-gray-100 font-semibold text-gray-900 dark:border-white/[0.06] dark:text-white">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2.5 text-left dark:bg-[#121212]">Summe {{ group.label }}</td>
                            <td v-for="(value, index) in group.totals" :key="index" class="px-2 py-2.5" :class="monthClass(index)">{{ cell(value) }}</td>
                            <td class="px-4 py-2.5">{{ cell(group.total) }}</td>
                        </tr>
                        <tr v-if="group.key !== 'income' && group.categories.length" class="text-xs text-gray-400">
                            <td class="sticky left-0 z-10 bg-white px-4 pb-3 text-left dark:bg-[#121212]">% der Einnahmen</td>
                            <td v-for="(value, index) in percentOfIncome(group.totals)" :key="index" class="px-2 pb-3">{{ value }}</td>
                            <td class="px-4 pb-3">{{ summary.totals.income > 0 ? `${Math.round(group.total / summary.totals.income * 100)} %` : '' }}</td>
                        </tr>
                    </tbody>

                    <tfoot class="font-semibold">
                        <tr class="text-gray-900 dark:text-white">
                            <td class="sticky left-0 z-10 bg-white px-4 pt-4 pb-1.5 text-left dark:bg-[#121212]">Ausgaben gesamt</td>
                            <td v-for="(value, index) in summary.expenses" :key="index" class="px-2 pt-4 pb-1.5">{{ cell(value) }}</td>
                            <td class="px-4 pt-4 pb-1.5">{{ cell(summary.totals.expenses) }}</td>
                        </tr>
                        <tr class="bg-primary-500/[0.06]">
                            <td class="sticky left-0 z-10 bg-white px-4 py-3 text-left text-primary-700 dark:bg-[#121212] dark:text-primary-300">Monatlicher Überschuss</td>
                            <td v-for="(value, index) in summary.surplus" :key="index" class="px-2 py-3" :class="value < 0 ? 'text-red-500' : 'text-primary-700 dark:text-primary-300'">{{ cell(value) }}</td>
                            <td class="px-4 py-3" :class="summary.totals.surplus < 0 ? 'text-red-500' : 'text-primary-700 dark:text-primary-300'">{{ cell(summary.totals.surplus) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <p v-if="overview.scope !== 'private'" class="mt-3 flex items-center gap-1.5 text-xs text-gray-500">
            <LockClosedIcon class="h-3.5 w-3.5" />
            Zeilen mit Schloss kommen automatisch aus deinen Rechnungen (Zahlungseingänge) und dem Inventar (Anschaffungen).
        </p>
        <p v-if="overview.year >= now.getFullYear()" class="mt-1 text-xs text-gray-500">Kommende Monate (abgeschwächt) zeigen nur, was deine Verträge bringen werden – zur Planung.</p>
    </Layout>
</template>
