<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import { formatMoney } from '@/utils/format';
import { rateLabel } from '@/utils/timePricing';

const props = defineProps({
    items: Array,
});

const search = ref('');
const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.items;

    return props.items.filter(item => [item.name, item.description].some(value => value?.toLowerCase().includes(term)));
});

const edit = (item) => router.visit(route('catalog.edit', item.id));
</script>

<template>
    <Layout title="Leistungen">
        <template v-slot:actions>
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="search" placeholder="Leistung suchen …" class="form-input w-full pl-9 sm:w-64" />
            </div>
            <VButton :href="route('catalog.create')" icon="PlusIcon">Neue Leistung</VButton>
        </template>

        <p class="mb-4 text-sm text-gray-500">Häufige Leistungen mit Preis, die du in Rechnungen per Klick einfügen kannst.</p>

        <!-- Empty state -->
        <div v-if="filtered.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <Squares2X2Icon class="h-7 w-7 text-primary-500" />
                </div>
                <template v-if="items.length === 0">
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch keine Leistungen</h2>
                    <p class="mt-2 text-sm text-gray-500">Lege z. B. „PC-Hilfe pro Stunde“ oder „Website Basic“ an.</p>
                    <div class="mt-6">
                        <VButton :href="route('catalog.create')" icon="PlusIcon">Neue Leistung</VButton>
                    </div>
                </template>
                <template v-else>
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Keine Treffer</h2>
                    <p class="mt-2 text-sm text-gray-500">Für „{{ search }}“ wurde keine Leistung gefunden.</p>
                </template>
            </div>
        </div>

        <div v-else class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                    <tr>
                        <th class="px-5 py-3 font-medium">Leistung</th>
                        <th class="hidden px-5 py-3 font-medium sm:table-cell">Einheit</th>
                        <th class="px-5 py-3 text-right font-medium">Preis netto</th>
                        <th class="px-5 py-3"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <tr v-for="item in filtered" :key="item.id" class="group cursor-pointer transition hover:bg-primary-500/[0.04]" @click="edit(item)">
                        <td class="px-5 py-3.5">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</div>
                            <div v-if="item.description" class="line-clamp-1 text-xs text-gray-500">{{ item.description }}</div>
                        </td>
                        <td class="hidden px-5 py-3.5 text-gray-600 sm:table-cell dark:text-gray-400">{{ item.pricing === 'time' ? 'Zeittarif' : item.unit }}</td>
                        <td class="px-5 py-3.5 text-right font-medium text-gray-900 dark:text-gray-100">
                            <template v-if="item.pricing === 'time'"><span class="text-xs font-normal sm:text-sm">{{ rateLabel(item) }}</span></template>
                            <template v-else>{{ formatMoney(item.unit_price) }}</template>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1 opacity-60 transition group-hover:opacity-100">
                                <Link :href="route('catalog.edit', item.id)" class="rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="Bearbeiten" @click.stop>
                                    <PencilSquareIcon class="h-4 w-4" />
                                </Link>
                                <Link :href="route('catalog.delete', item.id)" class="rounded-lg p-2 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Löschen" @click.stop>
                                    <TrashIcon class="h-4 w-4" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Layout>
</template>
