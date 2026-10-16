<script setup>
import { TableCellsIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import InventoryForm from './InventoryForm.vue';
import { formatMoney } from '@/utils/format';

defineProps({
    item: Object,
    receipt: Object,
    schedule: Array,
    categories: Array,
    gwgLimit: Number,
});

const currentYear = new Date().getFullYear();
</script>

<template>
    <Layout :title="item.name" :backTo="route('inventory.index')">
        <template v-slot:actions>
            <VButton :href="route('inventory.delete', item.id)" variant="secondary" icon="TrashIcon">Löschen</VButton>
        </template>

        <!-- Key form re-mounts the form after saving, so it shows the stored values -->
        <InventoryForm :key="item.updated_at" :item="item" :receipt="receipt" :categories="categories" :gwgLimit="gwgLimit" />

        <!-- Saved depreciation schedule -->
        <section v-if="schedule.length" class="card mx-auto mt-6 max-w-5xl overflow-hidden">
            <div class="p-6 pb-3">
                <h2 class="card-title"><TableCellsIcon class="h-5 w-5 text-primary-500" />Abschreibungsplan</h2>
                <p class="mt-1 text-sm text-gray-500">Richtwerte nach linearer AfA und Halbjahresregel – im Zweifel mit dem Steuerberater abstimmen.</p>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="border-y border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                    <tr>
                        <th class="px-6 py-2.5 font-medium">Jahr</th>
                        <th class="px-6 py-2.5 text-right font-medium">Abschreibung</th>
                        <th class="px-6 py-2.5 text-right font-medium">Buchwert 31.12.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <tr v-for="row in schedule" :key="row.year" :class="row.year === currentYear && 'bg-primary-500/[0.05]'">
                        <td class="px-6 py-2.5 font-mono text-gray-900 dark:text-gray-100">
                            {{ row.year }}
                            <span v-if="row.year === currentYear" class="ml-2 rounded-full bg-primary-500/15 px-2 py-0.5 font-sans text-[11px] font-medium text-primary-600 dark:text-primary-300">aktuell</span>
                        </td>
                        <td class="px-6 py-2.5 text-right text-gray-700 dark:text-gray-300">− {{ formatMoney(row.depreciation) }}</td>
                        <td class="px-6 py-2.5 text-right font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(row.book_value) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </Layout>
</template>
