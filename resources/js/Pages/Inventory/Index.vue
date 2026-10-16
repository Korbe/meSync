<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArchiveBoxIcon, ArrowDownTrayIcon, PaperClipIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VPagination from '@/components/VPagination.vue';
import VSearchInput from '@/components/VSearchInput.vue';
import { useListLoading } from '@/composables/useListLoading';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    items: Object,       // paginator
    filters: Object,     // status, category, search
    hasItems: Boolean,
    categories: Array,   // only the ones in use
    totals: Object,
});

const search = ref(props.filters.search ?? '');

// Filters and search run on the server; the page starts at 1 again
const load = (params = {}) => {
    const query = { status: props.filters.status, category: props.filters.category || undefined, search: search.value || undefined, ...params };
    if (query.status === 'active') delete query.status;
    router.get(route('inventory.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
const { loading, typing } = useListLoading();
let timeout = null;
watch(search, () => {
    typing();
    clearTimeout(timeout);
    timeout = setTimeout(() => load(), 300);
});

const statusFilters = [
    { value: 'active', label: 'In Verwendung' },
    { value: 'retired', label: 'Verkauft / ausgeschieden' },
    { value: 'all', label: 'Alle' },
];

const statusLabel = { sold: 'Verkauft', disposed: 'Ausgeschieden' };
const currentYear = new Date().getFullYear();

const stats = computed(() => [
    { label: 'Gegenstände', value: props.totals.count, money: false },
    { label: 'Anschaffungswert', value: props.totals.price, money: true },
    { label: `Buchwert Ende ${currentYear}`, value: props.totals.book_value, money: true, highlight: true },
    { label: `Gekauft ${currentYear}`, value: props.totals.bought_this_year, money: true },
]);

const edit = (item) => router.visit(route('inventory.edit', item.id));
</script>

<template>
    <Layout title="Inventar">
        <template v-slot:actions>
            <VSearchInput v-model="search" placeholder="Gegenstand suchen …" :loading="loading" />
            <a v-if="hasItems" :href="route('inventory.csv')" class="btn cursor-pointer border-gray-200! bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10! dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10" title="Anlagenverzeichnis als CSV">
                <ArrowDownTrayIcon class="h-5 w-5 sm:mr-2" /><span class="hidden sm:inline">CSV</span>
            </a>
            <VButton :href="route('inventory.create')" icon="PlusIcon">Neuer Gegenstand</VButton>
        </template>

        <p class="-mt-4 mb-6 max-w-2xl text-gray-500">Geräte und Gegenstände, die du fürs Geschäft gekauft hast – mit Wert, Beleg und Abschreibung.</p>

        <!-- Totals -->
        <div v-if="hasItems" class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="card relative overflow-hidden p-5">
                <div v-if="stat.highlight" class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-primary-500/20 blur-[70px]" aria-hidden="true"></div>
                <div class="relative">
                    <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ stat.label }}</div>
                    <div class="mt-1.5 font-display text-2xl font-semibold tracking-tight" :class="stat.highlight ? 'text-primary-600 dark:text-primary-300' : 'text-gray-900 dark:text-white'">
                        {{ stat.money ? formatMoney(stat.value) : stat.value }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div v-if="hasItems" class="mb-4 flex flex-wrap items-center gap-2">
            <button v-for="filter in statusFilters" :key="filter.value" type="button"
                class="cursor-pointer rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition"
                :class="filters.status === filter.value
                    ? 'bg-primary-500 text-white ring-primary-500'
                    : 'text-gray-600 ring-gray-200 hover:text-primary-600 hover:ring-primary-400 dark:text-gray-400 dark:ring-white/10 dark:hover:text-primary-300'"
                @click="load({ status: filter.value })">
                {{ filter.label }}
            </button>
            <select v-if="categories.length > 1" :value="filters.category" class="form-select ml-auto py-1.5 text-sm" @change="load({ category: $event.target.value || undefined })">
                <option value="">Alle Kategorien</option>
                <option v-for="option in categories" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </div>

        <!-- Empty state -->
        <div v-if="items.data.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <ArchiveBoxIcon class="h-7 w-7 text-primary-500" />
                </div>
                <template v-if="!hasItems">
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch nichts im Inventar</h2>
                    <p class="mt-2 text-sm text-gray-500">Trag deinen Laptop, Werkzeug oder Messgeräte mit Kaufpreis und Beleg ein.</p>
                    <div class="mt-6">
                        <VButton :href="route('inventory.create')" icon="PlusIcon">Neuer Gegenstand</VButton>
                    </div>
                </template>
                <template v-else>
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Keine Treffer</h2>
                    <p class="mt-2 text-sm text-gray-500">Mit diesen Filtern wurde nichts gefunden.</p>
                </template>
            </div>
        </div>

        <div v-else class="card overflow-hidden transition-opacity duration-200" :class="loading && 'pointer-events-none opacity-50'">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                    <tr>
                        <th class="px-5 py-3 font-medium">Gegenstand</th>
                        <th class="hidden px-5 py-3 font-medium md:table-cell">Kategorie</th>
                        <th class="hidden px-5 py-3 font-medium sm:table-cell">Gekauft</th>
                        <th class="px-5 py-3 text-right font-medium">Netto</th>
                        <th class="hidden px-5 py-3 text-right font-medium lg:table-cell">Buchwert</th>
                        <th class="px-5 py-3"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <tr v-for="item in items.data" :key="item.id" class="group cursor-pointer transition hover:bg-primary-500/[0.04]" @click="edit(item)">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100" :class="item.status !== 'active' && 'line-through decoration-gray-400/60'">{{ item.name }}</span>
                                <PaperClipIcon v-if="item.has_receipt" class="h-3.5 w-3.5 text-gray-400" title="Beleg vorhanden" />
                                <span v-if="item.is_gwg" class="rounded-full bg-accent/15 px-1.5 py-0.5 text-[10px] font-semibold text-accent ring-1 ring-inset ring-accent/30">GWG</span>
                                <span v-if="item.status !== 'active'" class="rounded-full bg-gray-500/10 px-2 py-0.5 text-[11px] font-medium text-gray-500 ring-1 ring-inset ring-gray-500/20">{{ statusLabel[item.status] }}</span>
                            </div>
                            <div v-if="item.serial_number || item.supplier" class="mt-0.5 text-xs text-gray-500">
                                {{ [item.supplier, item.serial_number && `SN ${item.serial_number}`].filter(Boolean).join(' · ') }}
                            </div>
                        </td>
                        <td class="hidden px-5 py-3.5 text-gray-600 md:table-cell dark:text-gray-400">{{ item.category_label }}</td>
                        <td class="hidden px-5 py-3.5 text-gray-600 sm:table-cell dark:text-gray-400">{{ formatDate(item.purchased_at) }}</td>
                        <td class="px-5 py-3.5 text-right font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(item.price) }}</td>
                        <td class="hidden px-5 py-3.5 text-right text-gray-600 lg:table-cell dark:text-gray-400">{{ item.status === 'active' ? formatMoney(item.book_value) : '–' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1 opacity-60 transition group-hover:opacity-100">
                                <Link :href="route('inventory.edit', item.id)" class="rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="Bearbeiten" @click.stop>
                                    <PencilSquareIcon class="h-4 w-4" />
                                </Link>
                                <Link :href="route('inventory.delete', item.id)" class="rounded-lg p-2 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Löschen" @click.stop>
                                    <TrashIcon class="h-4 w-4" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <VPagination v-if="items.data.length" :paginator="items" label="Gegenständen" />
    </Layout>
</template>
