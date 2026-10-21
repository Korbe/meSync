<script setup>
import { confirm } from '@/composables/useConfirm';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, ExclamationTriangleIcon, BellAlertIcon, CheckBadgeIcon, ArrowTrendingUpIcon, GlobeAltIcon, CommandLineIcon, WrenchScrewdriverIcon, CheckIcon, DocumentTextIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import StatusBadge from './StatusBadge.vue';
import DueTodayBanner from './DueTodayBanner.vue';
import SiteStatusDot from './SiteStatusDot.vue';
import { formatDate, formatMoney, relativeDays } from '@/utils/format';

const props = defineProps({
    contracts: Array,
    dueSoonDays: Number,
});

const typeIcons = { website: GlobeAltIcon, webapp: CommandLineIcon };

const billable = computed(() => props.contracts.filter(c => c.status !== 'ended'));
const overdue = computed(() => props.contracts.filter(c => c.status === 'overdue'));
const dueSoon = computed(() => props.contracts.filter(c => ['due_today', 'due_soon'].includes(c.status)));
const dueToday = computed(() => props.contracts.filter(c => c.status === 'due_today'));
const sum = (contracts) => contracts.reduce((total, c) => total + Number(c.annual_price), 0);

const stats = computed(() => [
    {
        label: 'Überfällig',
        value: overdue.value.length,
        hint: overdue.value.length ? formatMoney(sum(overdue.value)) + ' offen' : 'Alles erledigt',
        icon: ExclamationTriangleIcon,
        tone: overdue.value.length ? 'red' : 'neutral',
    },
    {
        label: `Fällig in ${props.dueSoonDays} Tagen`,
        value: dueSoon.value.length,
        hint: formatMoney(sum(dueSoon.value)),
        icon: BellAlertIcon,
        tone: dueSoon.value.length ? 'amber' : 'neutral',
    },
    {
        label: 'Aktive Verträge',
        value: billable.value.length,
        hint: `${billable.value.filter(c => c.type === 'website').length} Websites · ${billable.value.filter(c => c.type === 'webapp').length} Webanwendungen`,
        icon: CheckBadgeIcon,
        tone: 'primary',
    },
    {
        label: 'Wiederkehrend pro Jahr',
        value: formatMoney(sum(billable.value)),
        hint: formatMoney(sum(billable.value) / 12) + ' / Monat',
        icon: ArrowTrendingUpIcon,
        tone: 'primary',
    },
]);

const toneClasses = {
    red: { icon: 'bg-red-500/10 text-red-500 ring-red-500/30', glow: 'bg-red-500/25' },
    amber: { icon: 'bg-accent/10 text-accent ring-accent/40', glow: 'bg-accent/20' },
    primary: { icon: 'bg-primary-500/10 text-primary-500 ring-primary-500/30', glow: 'bg-primary-500/15' },
    neutral: { icon: 'bg-gray-500/10 text-gray-500 ring-gray-500/20', glow: '' },
};

// Filters
const tabs = computed(() => [
    { key: 'all', label: 'Alle', count: props.contracts.length },
    { key: 'due', label: 'Fällig', count: overdue.value.length + dueSoon.value.length },
    { key: 'website', label: 'Websites', count: billable.value.filter(c => c.type === 'website').length },
    { key: 'webapp', label: 'Webanwendungen', count: billable.value.filter(c => c.type === 'webapp').length },
    { key: 'ended', label: 'Beendet', count: props.contracts.length - billable.value.length },
]);

const activeTab = ref(overdue.value.length + dueSoon.value.length > 0 ? 'due' : 'all');
const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.contracts
        .filter(c => ({
            all: true,
            due: ['overdue', 'due_today', 'due_soon'].includes(c.status),
            website: c.type === 'website' && c.status !== 'ended',
            webapp: c.type === 'webapp' && c.status !== 'ended',
            ended: c.status === 'ended',
        })[activeTab.value])
        .filter(c => !term || [c.name, c.url, c.customer.display_name, c.customer.company]
            .some(value => value?.toLowerCase().includes(term)));
});

const dueTextClass = (contract) => ({
    overdue: 'text-red-600 dark:text-red-400',
    due_today: 'font-semibold text-amber-600 dark:text-accent',
    due_soon: 'text-amber-600 dark:text-accent',
}[contract.status] ?? 'text-gray-500');

const marking = ref(null);

const markInvoiced = async (contract) => {
    if (!(await confirm({
        title: 'Als verrechnet markieren?',
        message: `Die Jahresrechnung über ${formatMoney(contract.annual_price)} für ${contract.name} wird als heute gesendet eingetragen.`,
        confirmText: 'Als verrechnet markieren',
    }))) return;

    marking.value = contract.id;
    router.post(route('contracts.invoices.store', contract.id), {}, {
        preserveScroll: true,
        onFinish: () => marking.value = null,
    });
};

const edit = (contract) => router.visit(route('contracts.edit', contract.id));
</script>

<template>
    <Layout title="Wartungsverträge">
        <template v-slot:actions>
            <VButton :href="route('contracts.create')" icon="PlusIcon">Neuer Vertrag</VButton>
        </template>

        <DueTodayBanner :contracts="dueToday" />

        <!-- Stats -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="card relative overflow-hidden p-5">
                <div v-if="toneClasses[stat.tone].glow" class="pointer-events-none absolute -top-12 -right-12 h-32 w-32 rounded-full blur-[60px]" :class="toneClasses[stat.tone].glow" aria-hidden="true"></div>
                <div class="relative flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ stat.label }}</div>
                        <div class="mt-2 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ stat.value }}</div>
                        <div class="mt-1 truncate text-xs text-gray-500">{{ stat.hint }}</div>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1" :class="toneClasses[stat.tone].icon">
                        <component :is="stat.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-white/60 p-1 dark:border-white/10 dark:bg-white/[0.03]">
                <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="activeTab === tab.key
                        ? 'bg-primary-500 text-white shadow-[0_8px_20px_-8px_rgba(31,173,174,0.8)]'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white'"
                    @click="activeTab = tab.key">
                    {{ tab.label }}
                    <span class="rounded-full px-1.5 text-xs"
                        :class="activeTab === tab.key ? 'bg-white/20' : 'bg-gray-100 dark:bg-white/10'">{{ tab.count }}</span>
                </button>
            </div>

            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="search" placeholder="Projekt oder Kunde suchen …" class="form-input w-full pl-9 lg:w-72" />
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="filtered.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <WrenchScrewdriverIcon class="h-7 w-7 text-primary-500" />
                </div>
                <template v-if="contracts.length === 0">
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch keine Wartungsverträge</h2>
                    <p class="mt-2 text-sm text-gray-500">Leg deinen ersten Vertrag an, dann siehst du hier, wann welche Rechnung fällig ist.</p>
                    <div class="mt-6">
                        <VButton :href="route('contracts.create')" icon="PlusIcon">Neuer Vertrag</VButton>
                    </div>
                </template>
                <template v-else>
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Nichts gefunden</h2>
                    <p class="mt-2 text-sm text-gray-500">In dieser Ansicht gibt es keine passenden Verträge.</p>
                </template>
            </div>
        </div>

        <template v-else>
            <!-- Desktop: table -->
            <div class="card hidden overflow-hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                        <tr>
                            <th class="px-5 py-3 font-medium">Projekt</th>
                            <th class="px-5 py-3 font-medium">Preis</th>
                            <th class="px-5 py-3 font-medium">Nächste Rechnung</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <tr v-for="contract in filtered" :key="contract.id" class="group cursor-pointer transition hover:bg-primary-500/[0.04]" @click="edit(contract)">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 ring-1 ring-primary-500/25 transition group-hover:shadow-[0_0_18px_-4px_rgba(31,173,174,0.7)] dark:text-primary-300" :title="contract.type_label">
                                        <component :is="typeIcons[contract.type]" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                                            <span class="truncate">{{ contract.name }}</span>
                                            <SiteStatusDot :contract="contract" />
                                        </div>
                                        <div class="truncate text-xs text-gray-500">{{ contract.customer.display_name }} · {{ contract.type_label }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(contract.annual_price) }} <span class="text-xs font-normal text-gray-500">/ Jahr</span></div>
                                <div class="text-xs text-gray-500">{{ formatMoney(contract.monthly_price) }} / Monat</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <template v-if="contract.status !== 'ended'">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(contract.next_invoice_date) }}</div>
                                    <div class="text-xs font-medium" :class="dueTextClass(contract)">{{ relativeDays(contract.next_invoice_date) }}</div>
                                </template>
                                <div v-else class="text-xs text-gray-500">beendet {{ formatDate(contract.end_date) }}</div>
                            </td>
                            <td class="px-5 py-3.5"><StatusBadge :status="contract.status" /></td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <Link v-if="['overdue', 'due_today', 'due_soon'].includes(contract.status)" :href="route('invoices.create', { contract: contract.id })"
                                    class="mr-2 inline-flex items-center gap-1.5 rounded-lg bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-600 hover:shadow-[0_8px_20px_-8px_rgba(31,173,174,0.8)]"
                                    @click.stop>
                                    <DocumentTextIcon class="h-4 w-4" />Rechnung
                                </Link>
                                <button v-if="['overdue', 'due_today', 'due_soon'].includes(contract.status)" type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500/40 bg-primary-500/10 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-500 hover:text-white disabled:opacity-50 dark:text-primary-300"
                                    :disabled="marking === contract.id"
                                    @click.stop="markInvoiced(contract)">
                                    <CheckIcon class="h-4 w-4" />Verrechnet
                                </button>
                                <a :href="route('contracts.pdf', contract.id)" class="ml-1 inline-flex rounded-lg p-2 align-middle text-gray-500 transition hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300"
                                    title="Vertragsinformation als PDF" @click.stop>
                                    <DocumentArrowDownIcon class="h-4 w-4" />
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards -->
            <div class="space-y-3 md:hidden">
                <Link v-for="contract in filtered" :key="contract.id" :href="route('contracts.edit', contract.id)" class="card block p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-gray-900 dark:text-gray-100">{{ contract.name }}</div>
                            <div class="truncate text-xs text-gray-500">{{ contract.customer.display_name }}</div>
                        </div>
                        <StatusBadge :status="contract.status" />
                    </div>
                    <div class="mt-3 flex items-end justify-between text-sm">
                        <div v-if="contract.status !== 'ended'">
                            <div class="text-xs text-gray-500">Nächste Rechnung</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(contract.next_invoice_date) }}
                                <span class="text-xs" :class="dueTextClass(contract)">({{ relativeDays(contract.next_invoice_date) }})</span>
                            </div>
                        </div>
                        <div class="text-right font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(contract.annual_price) }}</div>
                    </div>
                </Link>
            </div>
        </template>
    </Layout>
</template>
