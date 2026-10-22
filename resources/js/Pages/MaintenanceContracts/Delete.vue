<script setup>
import { useForm } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    contract: Object,
});

const form = useForm({});

const destroy = () => {
    form.delete(route('contracts.destroy', props.contract.id));
};
</script>

<template>
    <Layout title="Wartungsvertrag löschen" :backTo="route('contracts.edit', contract.id)">
        <div class="mx-auto max-w-xl">
            <div class="card relative overflow-hidden border-red-500/30 p-8 text-center dark:border-red-500/30">
                <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-80 -translate-x-1/2 rounded-full bg-red-500/20 blur-[80px]" aria-hidden="true"></div>

                <div class="relative">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/30">
                        <ExclamationTriangleIcon class="h-7 w-7 text-red-500" />
                    </div>

                    <h2 class="mt-5 font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ contract.name }} löschen?
                    </h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Der Vertrag und sein Rechnungsverlauf ({{ contract.invoices_count }} {{ contract.invoices_count === 1 ? 'Rechnung' : 'Rechnungen' }}) werden endgültig gelöscht.
                        Wenn der Kunde gekündigt hat, trag stattdessen besser „Gekündigt zum“ ein.
                    </p>

                    <dl class="mt-6 space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 text-left text-sm dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Kunde</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ contract.customer.display_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Art</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ contract.type_label }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Preis</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(contract.monthly_price) }} / Monat</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Seit</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ contract.start_date ? formatDate(contract.start_date) : 'mit Go-Live' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex justify-center gap-3">
                        <VButton :href="route('contracts.edit', contract.id)" variant="secondary">Abbrechen</VButton>
                        <VButton variant="danger" icon="TrashIcon" :loading="form.processing" :disabled="form.processing" @click="destroy">
                            Endgültig löschen
                        </VButton>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
