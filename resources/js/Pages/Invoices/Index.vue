<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, DocumentTextIcon, BanknotesIcon, ReceiptPercentIcon, ArrowDownTrayIcon, PencilSquareIcon, TrashIcon, WrenchScrewdriverIcon, ClockIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import PaymentStatusBadge from './PaymentStatusBadge.vue';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    invoices: Array,
});

const year = new Date().getFullYear();
const thisYear = computed(() => props.invoices.filter(i => i.invoice_date.startsWith(String(year))));
const sum = (invoices, field) => invoices.reduce((total, i) => total + Number(i[field]), 0);

const unpaid = computed(() => props.invoices.filter(i => ['open', 'partial', 'overdue'].includes(i.payment_status)));
const overdue = computed(() => props.invoices.filter(i => i.payment_status === 'overdue'));

const stats = computed(() => [
    { label: `Umsatz netto ${year}`, value: formatMoney(sum(thisYear.value, 'subtotal')), hint: `${thisYear.value.length} Rechnungen`, icon: BanknotesIcon, tone: 'primary' },
    { label: `USt. ${year}`, value: formatMoney(sum(thisYear.value, 'tax_amount')), hint: 'für die Voranmeldung', icon: ReceiptPercentIcon, tone: 'primary' },
    { label: 'Offen', value: formatMoney(sum(unpaid.value, 'outstanding')), hint: `${unpaid.value.length} Rechnungen`, icon: ClockIcon, tone: unpaid.value.length ? 'amber' : 'primary' },
    { label: 'Überfällig', value: formatMoney(sum(overdue.value, 'outstanding')), hint: overdue.value.length ? `${overdue.value.length} Rechnungen` : 'Alles im grünen Bereich', icon: DocumentTextIcon, tone: overdue.value.length ? 'red' : 'primary' },
]);

const toneClasses = {
    primary: { icon: 'bg-primary-500/10 text-primary-500 ring-primary-500/30', glow: 'bg-primary-500/15' },
    amber: { icon: 'bg-accent/10 text-accent ring-accent/40', glow: 'bg-accent/20' },
    red: { icon: 'bg-red-500/10 text-red-500 ring-red-500/30', glow: 'bg-red-500/25' },
};

// Filters
const tabs = computed(() => [
    { key: 'all', label: 'Alle', count: props.invoices.length },
    { key: 'unpaid', label: 'Offen', count: unpaid.value.length },
    { key: 'overdue', label: 'Überfällig', count: overdue.value.length },
    { key: 'paid', label: 'Bezahlt', count: props.invoices.filter(i => i.payment_status === 'paid').length },
]);
const activeTab = ref('all');

const search = ref('');
const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.invoices
        .filter(i => ({
            all: true,
            unpaid: ['open', 'partial', 'overdue'].includes(i.payment_status),
            overdue: i.payment_status === 'overdue',
            paid: i.payment_status === 'paid',
        })[activeTab.value])
        .filter(i => !term || [i.number, i.recipient?.name].some(value => value?.toLowerCase().includes(term)));
});

const edit = (invoice) => router.visit(route('invoices.edit', invoice.id));
</script>

<template>
    <Layout title="Rechnungen">
        <template v-slot:actions>
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="search" placeholder="Nummer oder Kunde …" class="form-input w-full pl-9 sm:w-64" />
            </div>
            <VButton :href="route('invoices.create')" icon="PlusIcon">Neue Rechnung</VButton>
        </template>

        <!-- Stats -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="card relative overflow-hidden p-5">
                <div class="pointer-events-none absolute -top-12 -right-12 h-32 w-32 rounded-full blur-[60px]" :class="toneClasses[stat.tone].glow" aria-hidden="true"></div>
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

        <!-- Filter tabs -->
        <div class="mb-4 flex flex-wrap gap-1 self-start rounded-xl border border-gray-200 bg-white/60 p-1 dark:border-white/10 dark:bg-white/[0.03]" style="width: fit-content;">
            <button v-for="tab in tabs" :key="tab.key" type="button"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-primary-500 text-white shadow-[0_8px_20px_-8px_rgba(31,173,174,0.8)]'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white'"
                @click="activeTab = tab.key">
                {{ tab.label }}
                <span class="rounded-full px-1.5 text-xs" :class="activeTab === tab.key ? 'bg-white/20' : 'bg-gray-100 dark:bg-white/10'">{{ tab.count }}</span>
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="filtered.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <DocumentTextIcon class="h-7 w-7 text-primary-500" />
                </div>
                <template v-if="invoices.length === 0">
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch keine Rechnungen</h2>
                    <p class="mt-2 text-sm text-gray-500">Erstelle deine erste Rechnung, sie wird als PDF erzeugt, sobald du sie herunterlädst.</p>
                    <div class="mt-6">
                        <VButton :href="route('invoices.create')" icon="PlusIcon">Neue Rechnung</VButton>
                    </div>
                </template>
                <template v-else>
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Keine Treffer</h2>
                    <p class="mt-2 text-sm text-gray-500">In dieser Ansicht gibt es keine passenden Rechnungen.</p>
                </template>
            </div>
        </div>

        <template v-else>
            <!-- Desktop: table -->
            <div class="card hidden overflow-hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                        <tr>
                            <th class="px-5 py-3 font-medium">Nummer</th>
                            <th class="px-5 py-3 font-medium">Kunde</th>
                            <th class="px-5 py-3 font-medium">Datum</th>
                            <th class="px-5 py-3 font-medium">Fällig</th>
                            <th class="px-5 py-3 text-right font-medium">Betrag</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <tr v-for="invoice in filtered" :key="invoice.id" class="group cursor-pointer transition hover:bg-primary-500/[0.04]" :class="invoice.cancellation && 'opacity-55'" @click="edit(invoice)">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2 font-mono font-medium text-gray-900 dark:text-gray-100">
                                    {{ invoice.number }}
                                    <span v-if="invoice.maintenance_contract_id" class="inline-flex items-center gap-1 rounded-full bg-primary-500/10 px-2 py-0.5 font-sans text-[10px] font-medium text-primary-700 ring-1 ring-inset ring-primary-500/25 dark:text-primary-300" title="Aus einem Wartungsvertrag erstellt">
                                        <WrenchScrewdriverIcon class="h-3 w-3" />Wartung
                                    </span>
                                </div>
                                <span v-if="invoice.cancellation" class="mt-1 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-600 ring-1 ring-inset ring-red-500/30 dark:text-red-400">
                                    Storniert · {{ invoice.cancellation.number }}
                                </span>
                                <span v-else-if="invoice.cancels" class="mt-1 inline-flex rounded-full bg-gray-500/10 px-2 py-0.5 text-[11px] font-medium text-gray-600 ring-1 ring-inset ring-gray-500/25 dark:text-gray-400">
                                    Storno zu {{ invoice.cancels.number }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300">{{ invoice.recipient.name }}</td>
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ formatDate(invoice.invoice_date) }}</td>
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ formatDate(invoice.due_date) }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="font-medium text-gray-900 dark:text-gray-100" :class="invoice.cancellation && 'line-through'">{{ formatMoney(invoice.total) }}</div>
                                <div v-if="invoice.payment_status === 'partial'" class="text-xs text-amber-600 dark:text-accent">noch {{ formatMoney(invoice.outstanding) }} offen</div>
                                <div v-else class="text-xs text-gray-500">{{ formatMoney(invoice.subtotal) }} netto</div>
                            </td>
                            <td class="px-5 py-3.5"><PaymentStatusBadge :status="invoice.payment_status" /></td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-1 opacity-60 transition group-hover:opacity-100">
                                    <a :href="route('invoices.download', invoice.id)" class="rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="PDF herunterladen" @click.stop>
                                        <ArrowDownTrayIcon class="h-4 w-4" />
                                    </a>
                                    <Link :href="route('invoices.edit', invoice.id)" class="rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="Bearbeiten" @click.stop>
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <Link v-if="!invoice.cancellation" :href="route('invoices.delete', invoice.id)" class="rounded-lg p-2 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Löschen" @click.stop>
                                        <TrashIcon class="h-4 w-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards -->
            <div class="space-y-3 md:hidden">
                <div v-for="invoice in filtered" :key="invoice.id" class="card flex items-center gap-3 p-4">
                    <Link :href="route('invoices.edit', invoice.id)" class="min-w-0 flex-1">
                        <div class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ invoice.number }}</div>
                        <div class="truncate text-xs text-gray-500">{{ invoice.recipient.name }} · {{ formatDate(invoice.invoice_date) }}</div>
                        <div v-if="invoice.cancellation" class="text-[11px] font-medium text-red-600 dark:text-red-400">Storniert · {{ invoice.cancellation.number }}</div>
                        <div v-else-if="invoice.cancels" class="text-[11px] font-medium text-gray-500">Storno zu {{ invoice.cancels.number }}</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(invoice.total) }}</div>
                    </Link>
                    <a :href="route('invoices.download', invoice.id)" class="glass-button" title="PDF herunterladen">
                        <ArrowDownTrayIcon class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </template>
    </Layout>
</template>
