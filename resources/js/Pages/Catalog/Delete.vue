<script setup>
import { useForm } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import { formatMoney } from '@/utils/format';
import { rateLabel } from '@/utils/timePricing';

const props = defineProps({
    item: Object,
});

const form = useForm({});

const destroy = () => {
    form.delete(route('catalog.destroy', props.item.id));
};
</script>

<template>
    <Layout title="Leistung löschen" :backTo="route('catalog.edit', item.id)">
        <div class="mx-auto max-w-xl">
            <div class="card relative overflow-hidden border-red-500/30 p-8 text-center dark:border-red-500/30">
                <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-80 -translate-x-1/2 rounded-full bg-red-500/20 blur-[80px]" aria-hidden="true"></div>

                <div class="relative">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/30">
                        <ExclamationTriangleIcon class="h-7 w-7 text-red-500" />
                    </div>

                    <h2 class="mt-5 font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white">„{{ item.name }}“ löschen?</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Die Leistung wird aus dem Katalog entfernt. Bestehende Rechnungen bleiben unverändert.
                    </p>
                    <p class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ item.pricing === 'time' ? rateLabel(item) : `${formatMoney(item.unit_price)} / ${item.unit || 'Einheit'}` }}</p>

                    <div class="mt-8 flex justify-center gap-3">
                        <VButton :href="route('catalog.edit', item.id)" variant="secondary">Abbrechen</VButton>
                        <VButton variant="danger" icon="TrashIcon" :loading="form.processing" :disabled="form.processing" @click="destroy">
                            Endgültig löschen
                        </VButton>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
