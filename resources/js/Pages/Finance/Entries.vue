<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronLeftIcon, ChevronRightIcon, PencilSquareIcon, TrashIcon, ReceiptPercentIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VModal from '@/components/VModal.vue';
import FinanceHeader from './FinanceHeader.vue';
import EntryForm from './EntryForm.vue';
import ScopeBadge from './ScopeBadge.vue';
import ContractChecklist from './ContractChecklist.vue';
import { confirm } from '@/composables/useConfirm';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    month: String,
    scope: String,
    entries: Array,
    contracts: Array,
    openMonths: Array,
    categories: Array,
    suggestions: Array,
});

const monthDate = computed(() => new Date(`${props.month}-01T00:00:00`));
const monthLabel = computed(() => monthDate.value.toLocaleDateString('de-AT', { month: 'long', year: 'numeric' }));

const shift = (offset) => {
    const date = new Date(monthDate.value);
    date.setMonth(date.getMonth() + offset);
    const month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    router.get(route('finance.entries.index'), { month, ...(props.scope === 'all' ? {} : { scope: props.scope }) }, { preserveScroll: true });
};

// Entries grouped by day
const days = computed(() => {
    const groups = [];
    for (const entry of props.entries) {
        const date = entry.date.substring(0, 10);
        const group = groups.at(-1)?.date === date ? groups.at(-1) : groups[groups.push({ date, entries: [] }) - 1];
        group.entries.push(entry);
    }
    return groups;
});

const sumOf = (items, type, amount = item => Number(item.amount)) => items.filter(item => item.type === type).reduce((sum, item) => sum + amount(item), 0);
// Contracts count with the confirmed amount (own share) when there is one
const contractAmount = (contract) => contract.payment ? Math.round(contract.payment.amount * contract.share_percent) / 100 : contract.own_amount;
const totals = computed(() => {
    const income = sumOf(props.entries, 'income') + sumOf(props.contracts, 'income', contractAmount);
    const fixed = sumOf(props.contracts, 'expense', contractAmount);
    const variable = sumOf(props.entries, 'expense');
    return { income, fixed, variable, surplus: income - fixed - variable };
});

const editing = ref(null);

const monthName = (month) => new Date(`${month}-01T00:00:00`).toLocaleDateString('de-AT', { month: 'short', year: 'numeric' });
const goTo = (month) => router.get(route('finance.entries.index'), { month, ...(props.scope === 'all' ? {} : { scope: props.scope }) }, { preserveScroll: true });

const remove = async (entry) => {
    if (!(await confirm({
        title: 'Buchung löschen?',
        message: `„${entry.description}“ über ${formatMoney(entry.amount)} vom ${formatDate(entry.date)} wird gelöscht.`,
        confirmText: 'Löschen',
        variant: 'danger',
    }))) return;

    router.delete(route('finance.entries.destroy', entry.id), { preserveScroll: true });
};
</script>

<template>
    <Layout title="Finanzen">
        <template v-slot:actions>
            <div class="flex items-center gap-1 rounded-xl bg-white/80 p-1 ring-1 ring-gray-200 ring-inset dark:bg-white/5 dark:ring-white/10">
                <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/10 dark:hover:text-gray-200" title="Vormonat" @click="shift(-1)">
                    <ChevronLeftIcon class="h-4 w-4" />
                </button>
                <span class="min-w-32 text-center text-sm font-semibold text-gray-900 dark:text-white">{{ monthLabel }}</span>
                <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/10 dark:hover:text-gray-200" title="Nächster Monat" @click="shift(1)">
                    <ChevronRightIcon class="h-4 w-4" />
                </button>
            </div>
        </template>

        <FinanceHeader active="entries" :scope="scope" :query="{ month }" />

        <!-- Earlier months with contracts not checked against the bank statement yet -->
        <div v-if="openMonths.length" class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl bg-accent/[0.08] px-4 py-3 text-sm ring-1 ring-accent/30 ring-inset">
            <ExclamationCircleIcon class="h-5 w-5 shrink-0 text-accent" />
            <span class="text-gray-700 dark:text-gray-300">Noch nicht mit dem Kontoauszug abgeglichen:</span>
            <button v-for="open in openMonths" :key="open.month" type="button"
                class="cursor-pointer rounded-full bg-white/70 px-2.5 py-0.5 text-xs font-medium text-gray-800 ring-1 ring-accent/40 transition hover:bg-accent hover:text-gray-950 dark:bg-white/5 dark:text-gray-200"
                @click="goTo(open.month)">
                {{ monthName(open.month) }} · {{ open.count }}
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <!-- Quick entry -->
                <section class="card relative overflow-hidden p-6">
                    <div class="pointer-events-none absolute -top-20 -right-16 h-48 w-48 rounded-full bg-primary-500/15 blur-[80px]" aria-hidden="true"></div>
                    <div class="relative">
                        <h2 class="card-title mb-4">Neue Buchung</h2>
                        <EntryForm :key="month" :categories="categories" :suggestions="suggestions" :scope="scope" :month="month" />
                    </div>
                </section>

                <!-- Entries of the month -->
                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between p-6 pb-3">
                        <h2 class="card-title">Buchungen im {{ monthLabel }}</h2>
                        <span class="text-sm text-gray-500">{{ entries.length }}</span>
                    </div>
                    <p v-if="entries.length === 0" class="px-6 pb-6 text-sm text-gray-500">In diesem Monat ist noch nichts gebucht.</p>

                    <div v-for="day in days" :key="day.date">
                        <div class="border-y border-gray-100 bg-gray-50/80 px-6 py-1.5 font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500 dark:border-white/[0.06] dark:bg-white/[0.02]">
                            {{ new Date(`${day.date}T00:00:00`).toLocaleDateString('de-AT', { weekday: 'short', day: '2-digit', month: '2-digit' }) }}
                        </div>
                        <div v-for="entry in day.entries" :key="entry.id" class="group flex items-center gap-3 px-6 py-2.5 transition hover:bg-primary-500/[0.04]">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium text-gray-900 dark:text-gray-100">{{ entry.description }}</span>
                                    <ScopeBadge :scope="entry.scope" />
                                </div>
                                <div class="truncate text-xs text-gray-500">
                                    {{ entry.category_label }}<template v-if="entry.amount_input"> · <span class="font-mono">{{ entry.amount_input }}</span></template><template v-if="entry.notes"> · {{ entry.notes }}</template>
                                </div>
                            </div>
                            <div class="font-semibold tabular-nums" :class="entry.type === 'income' ? 'text-primary-600 dark:text-primary-300' : 'text-gray-900 dark:text-gray-100'">
                                {{ entry.type === 'income' ? '+' : '−' }} {{ formatMoney(entry.amount) }}
                            </div>
                            <div class="flex gap-0.5 opacity-50 transition group-hover:opacity-100">
                                <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="Bearbeiten" @click="editing = entry">
                                    <PencilSquareIcon class="h-4 w-4" />
                                </button>
                                <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Löschen" @click="remove(entry)">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Month summary + due contracts -->
            <div class="space-y-6">
                <section class="card p-6">
                    <h2 class="card-title"><ReceiptPercentIcon class="h-5 w-5 text-primary-500" />{{ monthLabel }}</h2>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Einnahmen</dt><dd class="font-medium text-primary-600 tabular-nums dark:text-primary-300">{{ formatMoney(totals.income) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Fixkosten</dt><dd class="font-medium text-gray-900 tabular-nums dark:text-gray-100">− {{ formatMoney(totals.fixed) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Variable Ausgaben</dt><dd class="font-medium text-gray-900 tabular-nums dark:text-gray-100">− {{ formatMoney(totals.variable) }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 dark:border-white/10">
                            <dt class="font-semibold text-gray-900 dark:text-white">Überschuss</dt>
                            <dd class="font-display text-lg font-semibold tabular-nums" :class="totals.surplus < 0 ? 'text-red-500' : 'text-primary-600 dark:text-primary-300'">{{ formatMoney(totals.surplus) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">Ohne Rechnungs-Zahlungseingänge und Inventar – die siehst du in der Übersicht.</p>
                </section>

                <ContractChecklist :key="month" :contracts="contracts" :month="month" :scope="scope" />
            </div>
        </div>

        <VModal :show="editing !== null" max-width="2xl" @close="editing = null">
            <div v-if="editing" class="p-6">
                <h3 class="mb-4 font-display text-lg font-semibold text-gray-900 dark:text-white">Buchung bearbeiten</h3>
                <EntryForm :key="editing.id" :entry="editing" :categories="categories" @saved="editing = null" @cancel="editing = null" />
            </div>
        </VModal>
    </Layout>
</template>
