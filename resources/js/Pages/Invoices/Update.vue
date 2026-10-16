<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ArrowUturnLeftIcon, NoSymbolIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';
import InvoiceForm from './InvoiceForm.vue';
import PaymentsPanel from './PaymentsPanel.vue';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    invoice: Object,
    customers: Array,
    catalog: Array,
    paymentTermsDays: Number,
    profileComplete: Boolean,
    locked: Boolean,
    canCancel: Boolean,
    canDelete: Boolean,
});

const confirmingCancel = ref(false);
const cancelForm = useForm({});

const cancelInvoice = () => {
    cancelForm.post(route('invoices.cancel', props.invoice.id), {
        onFinish: () => confirmingCancel.value = false,
    });
};
</script>

<template>
    <Layout :title="`${invoice.cancels ? 'Stornorechnung' : 'Rechnung'} ${invoice.number}`" :backTo="route('invoices.index')">
        <template v-slot:actions>
            <!-- Plain link: the PDF is a file download, not an Inertia page -->
            <a :href="route('invoices.download', invoice.id)" class="btn cursor-pointer bg-primary-500 text-white hover:bg-primary-600 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)]">
                <ArrowDownTrayIcon class="mr-2 h-5 w-5" />PDF herunterladen
            </a>
            <VButton v-if="canCancel" variant="secondary" @click="confirmingCancel = true">
                <span class="inline-flex items-center"><ArrowUturnLeftIcon class="mr-2 h-5 w-5" />Stornieren</span>
            </VButton>
            <VButton v-if="canDelete" :href="route('invoices.delete', invoice.id)" variant="secondary" icon="TrashIcon">Löschen</VButton>
        </template>

        <div v-if="invoice.maintenance_contract" class="-mt-4 mb-6 text-sm text-gray-500">
            Erstellt für den Wartungsvertrag
            <Link :href="route('contracts.edit', invoice.maintenance_contract.id)" class="font-medium text-gray-700 hover:text-primary-500 dark:text-gray-300">{{ invoice.maintenance_contract.name }}</Link>
        </div>

        <!-- Locked: cancellation invoice or cancelled original -->
        <div v-if="invoice.cancels" class="mb-6 flex items-start gap-3 rounded-xl bg-gray-500/10 px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-500/20 dark:text-gray-300">
            <ArrowUturnLeftIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <span>
                Stornorechnung zu
                <Link :href="route('invoices.edit', invoice.cancels.id)" class="font-mono font-semibold underline">{{ invoice.cancels.number }}</Link>
                vom {{ formatDate(invoice.cancels.invoice_date) }}. Sie kann nicht mehr bearbeitet werden.
            </span>
        </div>
        <div v-else-if="invoice.cancellation" class="mb-6 flex items-start gap-3 rounded-xl bg-red-500/10 px-4 py-3 text-sm text-red-700 ring-1 ring-red-500/30 dark:text-red-400">
            <NoSymbolIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <span>
                Diese Rechnung wurde mit
                <Link :href="route('invoices.edit', invoice.cancellation.id)" class="font-mono font-semibold underline">{{ invoice.cancellation.number }}</Link>
                storniert und kann nicht mehr bearbeitet werden.
            </span>
        </div>

        <!-- Payments: not for cancelled invoices or cancellation invoices -->
        <PaymentsPanel v-if="invoice.payment_status !== 'cancelled'" :invoice="invoice" />

        <InvoiceForm :invoice="invoice" :customers="customers" :catalog="catalog" :paymentTermsDays="paymentTermsDays" :profileComplete="profileComplete" :readonly="locked" is-edit />

        <!-- Cancel confirmation -->
        <VModal :show="confirmingCancel" @close="confirmingCancel = false">
            <template #title>Rechnung {{ invoice.number }} stornieren?</template>

            <template #content>
                Es wird eine <strong>Stornorechnung</strong> mit der nächsten Rechnungsnummer erstellt, die alle Positionen mit negativem Betrag enthält
                (insgesamt {{ formatMoney(-invoice.total) }}). Beide Rechnungen bleiben erhalten und können nicht mehr bearbeitet werden.
                <p v-if="invoice.maintenance_contract" class="mt-3">
                    Der Wartungsvertrag „{{ invoice.maintenance_contract.name }}“ wird zurückgesetzt und ist dann wieder fällig.
                </p>
            </template>

            <template #footer>
                <VButton variant="secondary" @click="confirmingCancel = false">Abbrechen</VButton>
                <VButton variant="danger" :loading="cancelForm.processing" :disabled="cancelForm.processing" @click="cancelInvoice">Stornorechnung erstellen</VButton>
            </template>
        </VModal>
    </Layout>
</template>
