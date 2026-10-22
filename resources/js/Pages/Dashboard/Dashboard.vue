<script setup>
import DueTodayBanner from '@/Pages/MaintenanceContracts/DueTodayBanner.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { BanknotesIcon, ClockIcon, ArrowTrendingUpIcon, CalendarDaysIcon, SignalIcon, WrenchScrewdriverIcon, DocumentTextIcon, UsersIcon, ChartBarIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import StatusBadge from '@/Pages/MaintenanceContracts/StatusBadge.vue';
import SiteStatusDot from '@/Pages/MaintenanceContracts/SiteStatusDot.vue';
import PaymentStatusBadge from '@/Pages/Invoices/PaymentStatusBadge.vue';
import { formatDate, formatMoney, relativeDays, timeAgo } from '@/utils/format';

const props = defineProps({
    stats: Object,
    revenueByMonth: Array,
    year: Number,
    month: Number,
    dueContracts: Array,
    dueToday: Array,
    openInvoices: Array,
    sites: Array,
    recentCustomers: Array,
});

const firstName = computed(() => usePage().props.auth.user.name.split(' ')[0]);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 11) return 'Guten Morgen';
    if (hour < 18) return 'Hallo';
    return 'Guten Abend';
});

const cards = computed(() => [
    { label: 'Umsatz diesen Monat', value: formatMoney(props.stats.revenueMonth), hint: 'netto', icon: BanknotesIcon, tone: 'primary' },
    { label: `Umsatz ${props.year}`, value: formatMoney(props.stats.revenueYear), hint: 'netto', icon: ArrowTrendingUpIcon, tone: 'primary' },
    {
        label: 'Offene Rechnungen',
        value: formatMoney(props.stats.outstanding),
        hint: props.stats.overdueCount ? `${props.stats.overdueCount} überfällig` : `${props.stats.unpaidCount} offen`,
        icon: ClockIcon,
        tone: props.stats.overdueCount ? 'red' : (props.stats.unpaidCount ? 'amber' : 'primary'),
    },
    { label: 'Wartung pro Jahr', value: formatMoney(props.stats.recurringYear), hint: `${formatMoney(props.stats.recurringYear / 12)} / Monat`, icon: CalendarDaysIcon, tone: 'primary' },
]);

const toneClasses = {
    primary: { icon: 'bg-primary-500/10 text-primary-500 ring-primary-500/30', glow: 'bg-primary-500/15' },
    amber: { icon: 'bg-accent/10 text-accent ring-accent/40', glow: 'bg-accent/20' },
    red: { icon: 'bg-red-500/10 text-red-500 ring-red-500/30', glow: 'bg-red-500/25' },
};

// Revenue chart
const monthNames = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
const maxRevenue = computed(() => Math.max(1, ...props.revenueByMonth.map(Number)));
const barHeight = (value) => `${Math.max(0, Number(value)) / maxRevenue.value * 100}%`;

const sitesDown = computed(() => props.sites.filter(site => site.status === 'down'));
</script>

<template>
    <Layout title="Dashboard">
        <p class="-mt-4 mb-8 text-gray-500">{{ greeting }}, {{ firstName }}. Hier ist dein Überblick.</p>

        <!-- Maintenance invoices due today: big and on top -->
        <DueTodayBanner :contracts="dueToday" />

        <!-- Site outage banner -->
        <Link v-if="sitesDown.length" :href="route('monitoring.index')"
            class="mb-6 flex items-center gap-3 rounded-2xl bg-red-500/10 px-5 py-4 text-red-700 ring-1 ring-red-500/30 transition hover:bg-red-500/15 dark:text-red-300">
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
            </span>
            <span class="font-semibold">
                {{ sitesDown.length === 1 ? `${sitesDown[0].name} ist nicht erreichbar` : `${sitesDown.length} Websites sind nicht erreichbar` }}
            </span>
            <span class="ml-auto text-sm">Details →</span>
        </Link>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in cards" :key="card.label" class="card relative overflow-hidden p-5">
                <div class="pointer-events-none absolute -top-12 -right-12 h-32 w-32 rounded-full blur-[60px]" :class="toneClasses[card.tone].glow" aria-hidden="true"></div>
                <div class="relative flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ card.label }}</div>
                        <div class="mt-2 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ card.value }}</div>
                        <div class="mt-1 truncate text-xs text-gray-500">{{ card.hint }}</div>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1" :class="toneClasses[card.tone].icon">
                        <component :is="card.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Revenue chart -->
            <section class="card p-6 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="card-title"><ChartBarIcon class="h-5 w-5 text-primary-500" />Umsatz {{ year }}</h2>
                    <span class="text-sm text-gray-500">netto pro Monat</span>
                </div>

                <div class="mt-6 flex h-52 items-end gap-2">
                    <div v-for="(value, index) in revenueByMonth" :key="index" class="group flex h-full flex-1 flex-col items-center justify-end gap-2">
                        <div class="relative flex w-full flex-1 items-end">
                            <!-- Value on hover -->
                            <span class="pointer-events-none absolute -top-1 left-1/2 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 transition group-hover:opacity-100 dark:bg-white dark:text-gray-900">
                                {{ formatMoney(value) }}
                            </span>
                            <div class="w-full rounded-t-md transition-all duration-500"
                                :class="index + 1 === month
                                    ? 'bg-primary-500 shadow-[0_0_24px_-4px_rgba(31,173,174,0.8)]'
                                    : (index + 1 < month ? 'bg-primary-500/40 group-hover:bg-primary-500/70' : 'bg-gray-200 dark:bg-white/5')"
                                :style="{ height: Number(value) > 0 ? barHeight(value) : '3px' }"></div>
                        </div>
                        <span class="text-[11px]" :class="index + 1 === month ? 'font-semibold text-primary-600 dark:text-primary-300' : 'text-gray-500'">{{ monthNames[index] }}</span>
                    </div>
                </div>
            </section>

            <!-- Website status -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between p-6 pb-3">
                    <Link :href="route('monitoring.index')" class="card-title hover:text-primary-600 dark:hover:text-primary-300"><SignalIcon class="h-5 w-5 text-primary-500" />Websites</Link>
                    <span v-if="sites.length" class="text-sm" :class="sitesDown.length ? 'font-semibold text-red-600 dark:text-red-400' : 'text-gray-500'">
                        {{ sitesDown.length ? `${sitesDown.length} down` : 'alle online' }}
                    </span>
                </div>
                <p v-if="sites.length === 0" class="px-6 pb-6 text-sm text-gray-500">Noch keine überwachten Websites. Hinterlege bei einem Wartungsvertrag eine URL oder füge im Monitoring Center eine hinzu.</p>
                <ul v-else class="max-h-64 divide-y divide-gray-100 overflow-y-auto dark:divide-white/[0.06]">
                    <li v-for="site in sites" :key="site.id">
                        <Link :href="site.maintenance_contract_id ? route('contracts.edit', site.maintenance_contract_id) : route('monitoring.index')" class="flex items-center gap-3 px-6 py-2.5 transition hover:bg-primary-500/[0.04]">
                            <SiteStatusDot :site="site" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ site.name }}</div>
                                <div class="truncate text-xs text-gray-500">{{ site.url.replace(/^https?:\/\//, '') }}</div>
                            </div>
                            <span class="text-right text-xs text-gray-500">
                                <template v-if="site.status === 'down'"><span class="text-red-600 dark:text-red-400">{{ timeAgo(site.status_since).replace('vor ', 'seit ') }}</span></template>
                                <template v-else-if="site.response_ms">{{ site.response_ms }} ms</template>
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- Due maintenance -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between p-6 pb-3">
                    <h2 class="card-title"><WrenchScrewdriverIcon class="h-5 w-5 text-primary-500" />Fällige Wartung</h2>
                    <Link :href="route('contracts.index')" class="text-sm text-gray-500 hover:text-primary-500">Alle →</Link>
                </div>
                <p v-if="dueContracts.length === 0" class="px-6 pb-6 text-sm text-gray-500">In den nächsten 30 Tagen ist nichts fällig.</p>
                <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <li v-for="contract in dueContracts" :key="contract.id" class="flex items-center gap-3 px-6 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ contract.name }}</div>
                            <div class="text-xs" :class="contract.status === 'overdue' ? 'text-red-600 dark:text-red-400' : contract.status === 'due_today' ? 'font-semibold text-amber-600 dark:text-accent' : 'text-gray-500'">
                                {{ formatDate(contract.next_invoice_date) }} · {{ relativeDays(contract.next_invoice_date) }}
                            </div>
                        </div>
                        <Link :href="route('invoices.create', { contract: contract.id })"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary-500 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-primary-600">
                            <DocumentTextIcon class="h-3.5 w-3.5" />Rechnung
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- Open invoices -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between p-6 pb-3">
                    <h2 class="card-title"><ClockIcon class="h-5 w-5 text-primary-500" />Offene Rechnungen</h2>
                    <Link :href="route('invoices.index')" class="text-sm text-gray-500 hover:text-primary-500">Alle →</Link>
                </div>
                <p v-if="openInvoices.length === 0" class="px-6 pb-6 text-sm text-gray-500">Alle Rechnungen sind bezahlt. 🎉</p>
                <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <li v-for="invoice in openInvoices" :key="invoice.id">
                        <Link :href="route('invoices.edit', invoice.id)" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><span class="font-mono">{{ invoice.number }}</span> · {{ invoice.recipient }}</div>
                                <div class="text-xs text-gray-500">fällig {{ formatDate(invoice.due_date) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(invoice.outstanding) }}</div>
                                <PaymentStatusBadge :status="invoice.payment_status" />
                            </div>
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- Recent customers -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between p-6 pb-3">
                    <h2 class="card-title"><UsersIcon class="h-5 w-5 text-primary-500" />Neue Kunden</h2>
                    <Link :href="route('customers.index')" class="text-sm text-gray-500 hover:text-primary-500">Alle →</Link>
                </div>
                <div v-if="recentCustomers.length === 0" class="px-6 pb-6">
                    <p class="text-sm text-gray-500">Noch keine Kunden.</p>
                    <VButton :href="route('customers.create')" icon="PlusIcon" class="mt-4">Neuer Kunde</VButton>
                </div>
                <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <li v-for="customer in recentCustomers" :key="customer.id">
                        <Link :href="route('customers.show', customer.id)" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ customer.display_name }}</div>
                                <div class="truncate text-xs text-gray-500">{{ customer.city || customer.email || '–' }}</div>
                            </div>
                            <span class="text-xs text-gray-500">{{ timeAgo(customer.created_at) }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </Layout>
</template>
