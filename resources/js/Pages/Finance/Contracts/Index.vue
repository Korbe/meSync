<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowPathIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import FinanceHeader from '../FinanceHeader.vue';
import ScopeBadge from '../ScopeBadge.vue';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    contracts: Array,
    scope: String,
});

const search = ref('');
const showEnded = ref(false);

const inScope = computed(() => props.contracts.filter(contract => props.scope === 'all' || contract.scope === props.scope));
const ended = computed(() => inScope.value.filter(contract => !contract.is_active && contract.next_due_date === null));

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    return inScope.value
        .filter(contract => showEnded.value || contract.is_active || contract.next_due_date !== null)
        .filter(contract => !term || [contract.name, contract.category_label, contract.notes].some(value => value?.toLowerCase().includes(term)));
});

// Grouped by type: income first, then expenses by category
const groups = computed(() => {
    const result = [];
    for (const type of ['income', 'expense']) {
        const contracts = filtered.value.filter(contract => contract.type === type);
        if (contracts.length) result.push({ type, label: type === 'income' ? 'Regelmäßige Einnahmen' : 'Fixkosten & Verträge', contracts });
    }
    return result;
});

// Only running contracts count for the monthly sums
const running = computed(() => inScope.value.filter(contract => contract.is_active));
const sumMonthly = (type, scope = null) => running.value
    .filter(contract => contract.type === type && (!scope || contract.scope === scope))
    .reduce((sum, contract) => sum + contract.monthly_amount, 0);

const stats = computed(() => {
    const expenses = sumMonthly('expense');
    const income = sumMonthly('income');
    return [
        { label: 'Fixkosten pro Monat', value: expenses, sub: `${formatMoney(expenses * 12)} im Jahr` },
        { label: 'davon Privat', value: sumMonthly('expense', 'private'), sub: 'pro Monat', hidden: props.scope !== 'all' },
        { label: 'davon Firma', value: sumMonthly('expense', 'business'), sub: 'pro Monat', hidden: props.scope !== 'all' },
        { label: 'Fixe Einnahmen pro Monat', value: income, sub: income > 0 ? `${Math.round(expenses / income * 100)} % davon fix gebunden` : 'z. B. Gehalt', hidden: props.scope === 'business' && income === 0 },
    ].filter(stat => !stat.hidden);
});

const edit = (contract) => router.visit(route('finance.contracts.edit', contract.id));
</script>

<template>
    <Layout title="Finanzen">
        <template v-slot:actions>
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="search" placeholder="Vertrag suchen …" class="form-input w-full pl-9 sm:w-56" />
            </div>
            <VButton :href="route('finance.contracts.create', scope === 'all' ? {} : { scope })" icon="PlusIcon">Neuer Vertrag</VButton>
        </template>

        <FinanceHeader active="contracts" :scope="scope" />

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="card p-5">
                <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ stat.label }}</div>
                <div class="mt-1.5 font-display text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ formatMoney(stat.value) }}</div>
                <div class="mt-0.5 text-xs text-gray-500">{{ stat.sub }}</div>
            </div>
        </div>

        <div v-if="filtered.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <ArrowPathIcon class="h-7 w-7 text-primary-500" />
                </div>
                <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">{{ contracts.length ? 'Keine Treffer' : 'Noch keine Verträge' }}</h2>
                <p class="mt-2 text-sm text-gray-500">Miete, Versicherungen, Handy, Abos, Hosting, Gehalt – alles was regelmäßig kommt oder geht.</p>
                <div class="mt-6"><VButton :href="route('finance.contracts.create')" icon="PlusIcon">Neuer Vertrag</VButton></div>
            </div>
        </div>

        <section v-for="group in groups" :key="group.type" class="card mb-6 overflow-hidden">
            <div class="p-6 pb-3"><h2 class="card-title">{{ group.label }}</h2></div>
            <table class="w-full text-left text-sm">
                <thead class="border-y border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Vertrag</th>
                        <th class="hidden px-5 py-2.5 font-medium md:table-cell">Kategorie</th>
                        <th class="hidden px-5 py-2.5 text-right font-medium sm:table-cell">Betrag</th>
                        <th class="px-5 py-2.5 text-right font-medium">Pro Monat</th>
                        <th class="hidden px-5 py-2.5 text-right font-medium lg:table-cell">Pro Jahr</th>
                        <th class="hidden px-5 py-2.5 font-medium sm:table-cell">Nächste Zahlung</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <tr v-for="contract in group.contracts" :key="contract.id" class="cursor-pointer transition hover:bg-primary-500/[0.04]" :class="!contract.is_active && 'opacity-60'" @click="edit(contract)">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ contract.name }}</span>
                                <ScopeBadge v-if="scope === 'all'" :scope="contract.scope" />
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ contract.interval_label }}<template v-if="contract.share_percent < 100"> · Anteil {{ contract.share_percent }} %</template>
                                <template v-if="contract.end_date"> · bis {{ formatDate(contract.end_date) }}</template>
                                <template v-if="!contract.is_active && contract.next_due_date"> · ab {{ formatDate(contract.start_date) }}</template>
                            </div>
                        </td>
                        <td class="hidden px-5 py-3 text-gray-600 md:table-cell dark:text-gray-400">{{ contract.category_label }}</td>
                        <td class="hidden px-5 py-3 text-right text-gray-600 tabular-nums sm:table-cell dark:text-gray-400">
                            {{ formatMoney(contract.own_amount) }}
                            <div v-if="contract.share_percent < 100" class="text-xs text-gray-400">von {{ formatMoney(contract.amount) }}</div>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold text-gray-900 tabular-nums dark:text-gray-100">{{ formatMoney(contract.monthly_amount) }}</td>
                        <td class="hidden px-5 py-3 text-right text-gray-600 tabular-nums lg:table-cell dark:text-gray-400">{{ formatMoney(contract.yearly_amount) }}</td>
                        <td class="hidden px-5 py-3 text-gray-600 sm:table-cell dark:text-gray-400">{{ contract.next_due_date ? formatDate(contract.next_due_date) : 'beendet' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <button v-if="ended.length" type="button" class="cursor-pointer text-sm text-gray-500 underline hover:text-primary-500" @click="showEnded = !showEnded">
            {{ showEnded ? 'Beendete Verträge ausblenden' : `${ended.length} beendete Verträge anzeigen` }}
        </button>
    </Layout>
</template>
