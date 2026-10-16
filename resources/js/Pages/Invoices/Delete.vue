<script setup>
import { useForm } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    invoice: Object,
    undoesContractBilling: Boolean,
});

const form = useForm({});

const destroy = () => {
    form.delete(route('invoices.destroy', props.invoice.id));
};
</script>

<template>
    <Layout title="Rechnung löschen" :backTo="route('invoices.edit', invoice.id)">
        <div class="mx-auto max-w-xl">
            <div class="card relative overflow-hidden border-red-500/30 p-8 text-center dark:border-red-500/30">
                <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-80 -translate-x-1/2 rounded-full bg-red-500/20 blur-[80px]" aria-hidden="true"></div>

                <div class="relative">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/30">
                        <ExclamationTriangleIcon class="h-7 w-7 text-red-500" />
                    </div>

                    <h2 class="mt-5 font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Rechnung {{ invoice.number }} löschen?
                    </h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Die Rechnung wird endgültig gelöscht und ihre Nummer wird wieder frei. Lösche nur Rechnungen, die noch nicht verschickt wurden.
                    </p>

                    <p v-if="invoice.cancels" class="mt-3 rounded-lg bg-accent/10 px-3 py-2 text-sm font-medium text-amber-700 ring-1 ring-accent/40 dark:text-accent">
                        Das ist eine Stornorechnung. Nach dem Löschen ist Rechnung {{ invoice.cancels.number }} wieder gültig.
                    </p>

                    <p v-if="undoesContractBilling" class="mt-3 rounded-lg bg-accent/10 px-3 py-2 text-sm font-medium text-amber-700 ring-1 ring-accent/40 dark:text-accent">
                        Der Wartungsvertrag „{{ invoice.maintenance_contract.name }}“ wird wieder auf die Fälligkeit {{ formatDate(invoice.maintenance_invoice.period_start) }} zurückgesetzt.
                    </p>

                    <dl class="mt-6 space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 text-left text-sm dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Kunde</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ invoice.recipient.name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Datum</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ formatDate(invoice.invoice_date) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Betrag</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(invoice.total) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex justify-center gap-3">
                        <VButton :href="route('invoices.edit', invoice.id)" variant="secondary">Abbrechen</VButton>
                        <VButton variant="danger" icon="TrashIcon" :loading="form.processing" :disabled="form.processing" @click="destroy">
                            Endgültig löschen
                        </VButton>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
