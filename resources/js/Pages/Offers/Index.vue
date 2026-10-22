<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ClipboardDocumentListIcon, CheckBadgeIcon, WrenchScrewdriverIcon, ChartPieIcon, ArrowDownTrayIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VPagination from '@/components/VPagination.vue';
import VSearchInput from '@/components/VSearchInput.vue';
import OfferStatusBadge from './OfferStatusBadge.vue';
import { useListLoading } from '@/composables/useListLoading';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    offers: Object,     // paginator
    tab: String,
    search: String,
    year: Number,
    stats: Object,
    counts: Object,
});

const search = ref(props.search ?? '');
const load = (params = {}) => {
    const query = { tab: props.tab, search: search.value || undefined, ...params };
    if (query.tab === 'all') delete query.tab;
    router.get(route('offers.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
const { loading, typing } = useListLoading();
let timeout = null;
watch(search, () => {
    typing();
    clearTimeout(timeout);
    timeout = setTimeout(() => load(), 300);
});
const setTab = (tab) => load({ tab });

const statCards = computed(() => [
    { label: 'Offen', value: formatMoney(props.stats.open.sum), hint: `${props.stats.open.count} Angebote · netto`, icon: ClipboardDocumentListIcon, tone: props.stats.open.count ? 'amber' : 'primary' },
    { label: `Angenommen ${props.year}`, value: formatMoney(props.stats.accepted.sum), hint: `${props.stats.accepted.count} Angebote · netto`, icon: CheckBadgeIcon, tone: 'primary' },
    { label: 'Neue Wartung / Monat', value: formatMoney(props.stats.maintenance), hint: `aus angenommenen Angeboten ${props.year}`, icon: WrenchScrewdriverIcon, tone: 'primary' },
    { label: 'Annahmequote', value: props.stats.rate === null ? '–' : `${props.stats.rate} %`, hint: `entschiedene Angebote ${props.year}`, icon: ChartPieIcon, tone: 'primary' },
]);

const toneClasses = {
    primary: { icon: 'bg-primary-500/10 text-primary-500 ring-primary-500/30', glow: 'bg-primary-500/15' },
    amber: { icon: 'bg-accent/10 text-accent ring-accent/40', glow: 'bg-accent/20' },
};

const tabs = computed(() => [
    { key: 'all', label: 'Alle' },
    { key: 'open', label: 'Offen' },
    { key: 'accepted', label: 'Angenommen' },
    { key: 'declined', label: 'Abgelehnt' },
    { key: 'expired', label: 'Abgelaufen' },
].map(item => ({ ...item, count: props.counts[item.key] })));

const edit = (offer) => router.visit(route('offers.edit', offer.id));
</script>

<template>
    <Layout title="Angebote">
        <template v-slot:actions>
            <VSearchInput v-model="search" placeholder="Nummer, Projekt oder Kunde …" :loading="loading" />
            <VButton :href="route('offers.create')" icon="PlusIcon">Neues Angebot</VButton>
        </template>

        <!-- Stats -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="stat in statCards" :key="stat.label" class="card relative overflow-hidden p-5">
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
            <button v-for="item in tabs" :key="item.key" type="button"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab === item.key
                    ? 'bg-primary-500 text-white shadow-[0_8px_20px_-8px_rgba(31,173,174,0.8)]'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white'"
                @click="setTab(item.key)">
                {{ item.label }}
                <span class="rounded-full px-1.5 text-xs" :class="tab === item.key ? 'bg-white/20' : 'bg-gray-100 dark:bg-white/10'">{{ item.count }}</span>
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="offers.data.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <ClipboardDocumentListIcon class="h-7 w-7 text-primary-500" />
                </div>
                <template v-if="counts.all === 0">
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch keine Angebote</h2>
                    <p class="mt-2 text-sm text-gray-500">Angebote für Websites und Web-Apps – die laufende Betreuung ist immer dabei und wird bei Annahme zum Wartungsvertrag.</p>
                    <div class="mt-6">
                        <VButton :href="route('offers.create')" icon="PlusIcon">Neues Angebot</VButton>
                    </div>
                </template>
                <template v-else>
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Keine Treffer</h2>
                    <p class="mt-2 text-sm text-gray-500">In dieser Ansicht gibt es keine passenden Angebote.</p>
                </template>
            </div>
        </div>

        <div v-else class="transition-opacity duration-200" :class="loading && 'pointer-events-none opacity-50'">
            <!-- Desktop: table -->
            <div class="card hidden overflow-hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                        <tr>
                            <th class="px-5 py-3 font-medium">Nummer</th>
                            <th class="px-5 py-3 font-medium">Projekt</th>
                            <th class="px-5 py-3 font-medium">Datum</th>
                            <th class="px-5 py-3 font-medium">Gültig bis</th>
                            <th class="px-5 py-3 text-right font-medium">Betrag</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <tr v-for="offer in offers.data" :key="offer.id" class="group cursor-pointer transition hover:bg-primary-500/[0.04]" @click="edit(offer)">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2 font-mono font-medium text-gray-900 dark:text-gray-100">
                                    {{ offer.number }}
                                    <EnvelopeIcon v-if="offer.sent_to" class="h-3.5 w-3.5 text-primary-500" :title="`Per E-Mail verschickt an ${offer.sent_to}`" />
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ offer.title }}</div>
                                <div class="text-xs text-gray-500">{{ offer.recipient.name }} · {{ offer.type_label }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ formatDate(offer.offer_date) }}</td>
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ formatDate(offer.valid_until) }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(offer.total) }}</div>
                                <div class="text-xs text-gray-500">+ {{ formatMoney(offer.maintenance_monthly_price) }}/Monat Betreuung</div>
                            </td>
                            <td class="px-5 py-3.5"><OfferStatusBadge :status="offer.status" /></td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end opacity-60 transition group-hover:opacity-100">
                                    <a :href="route('offers.download', offer.id)" class="rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="PDF herunterladen" @click.stop>
                                        <ArrowDownTrayIcon class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards -->
            <div class="space-y-3 md:hidden">
                <div v-for="offer in offers.data" :key="offer.id" class="card flex items-center gap-3 p-4">
                    <Link :href="route('offers.edit', offer.id)" class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ offer.number }}</span>
                            <OfferStatusBadge :status="offer.status" />
                        </div>
                        <div class="truncate text-sm text-gray-700 dark:text-gray-300">{{ offer.title }}</div>
                        <div class="truncate text-xs text-gray-500">{{ offer.recipient.name }} · {{ formatDate(offer.offer_date) }}</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(offer.total) }}</div>
                    </Link>
                    <a :href="route('offers.download', offer.id)" class="glass-button" title="PDF herunterladen">
                        <ArrowDownTrayIcon class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <VPagination :paginator="offers" label="Angebote" />
        </div>
    </Layout>
</template>
