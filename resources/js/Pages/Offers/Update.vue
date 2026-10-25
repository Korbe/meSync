<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { EyeIcon, EnvelopeIcon, CheckCircleIcon, XCircleIcon, ArrowUturnLeftIcon, DocumentDuplicateIcon, LockClosedIcon, HandRaisedIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import OfferForm from './OfferForm.vue';
import OfferStatusBadge from './OfferStatusBadge.vue';
import ContractStatusBadge from '@/Pages/MaintenanceContracts/StatusBadge.vue';
import SendInvoiceModal from '@/Pages/Invoices/SendInvoiceModal.vue';
import { confirm } from '@/composables/useConfirm';
import { formatDate, formatDateTime, formatMoney } from '@/utils/format';

const props = defineProps({
    offer: Object,
    mail: Object,
    locked: Boolean,
    canReopen: Boolean,
    customers: Array,
    projects: Array,
    catalog: Array,
    types: Array,
    packages: Array,
    features: Array,
    integrations: Array,
    contentDelivery: Array,
    paymentTerms: Array,
    hints: Object,
});

const sending = ref(false);

// Accepted, project still being worked on: the invoice comes after the implementation
const inImplementation = computed(() => !!props.offer.accepted_at && !props.offer.invoice && props.offer.payment_terms === 'on_completion'
    && !!props.offer.project && !props.offer.project.completed_at);

const post = (name) => router.post(route(name, props.offer.id), {}, { preserveScroll: true });

const accept = async () => {
    if (await confirm({
        title: `Angebot ${props.offer.number} angenommen?`,
        message: 'Danach erstellst du die Projektrechnung, und der Wartungsvertrag wird angelegt. Er wartet auf den Go-Live und wird dort gestartet.',
        confirmText: 'Angenommen',
    })) post('offers.accept');
};

const markSent = async () => {
    if (await confirm({ title: 'Als übergeben markieren?', message: 'Zum Beispiel persönlich oder per Post übergeben. Danach lässt sich das Angebot nicht mehr ändern – Änderungen gehen dann über „Kopie erstellen“.', confirmText: 'Markieren' })) post('offers.sent');
};

const decline = async () => {
    if (await confirm({ title: `Angebot ${props.offer.number} abgelehnt?`, message: 'Das Angebot wird als abgelehnt markiert. Du kannst das später zurücksetzen.', confirmText: 'Als abgelehnt markieren', variant: 'danger' })) post('offers.decline');
};

const reopen = async () => {
    if (await confirm({ title: 'Entscheidung zurücksetzen?', message: 'Das Angebot ist danach wieder offen.', confirmText: 'Zurücksetzen' })) post('offers.reopen');
};

const remove = async () => {
    const message = props.offer.maintenance_contract
        ? 'Der Wartungsvertrag und eine eventuelle Rechnung bleiben erhalten.'
        : 'Das lässt sich nicht rückgängig machen.';
    if (await confirm({ title: `Angebot ${props.offer.number} löschen?`, message, confirmText: 'Löschen', variant: 'danger' })) {
        router.delete(route('offers.destroy', props.offer.id));
    }
};
</script>

<template>
    <Layout :title="`Angebot ${offer.number}`" :backTo="route('offers.index')">
        <template v-slot:actions>
            <!-- Plain link in a new tab: the browser shows the PDF (with its own download button) -->
            <a :href="route('offers.download', offer.id)" target="_blank" rel="noopener\" class="btn cursor-pointer bg-primary-500 text-white hover:bg-primary-600 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)]">
                <EyeIcon class="mr-2 h-5 w-5" />PDF ansehen
            </a>
            <VButton variant="secondary" @click="sending = true">
                <span class="inline-flex items-center"><EnvelopeIcon class="mr-2 h-5 w-5" />Per E-Mail senden</span>
            </VButton>
            <VButton variant="secondary" @click="post('offers.duplicate')">
                <span class="inline-flex items-center"><DocumentDuplicateIcon class="mr-2 h-5 w-5" />Kopie erstellen</span>
            </VButton>
            <VButton variant="secondary" icon="TrashIcon" @click="remove">Löschen</VButton>
        </template>

        <!-- Status + decision -->
        <section class="card mb-6 flex flex-wrap items-center gap-3 p-4">
            <OfferStatusBadge :status="offer.status" :sent-to="offer.sent_to" />
            <Link v-if="offer.project" :href="route('projects.show', offer.project.id)" class="rounded-full bg-primary-500/10 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-500/25 hover:bg-primary-500/20 dark:text-primary-300">
                Projekt: {{ offer.project.name }}
            </Link>
            <span class="text-sm text-gray-600 dark:text-gray-400">
                <template v-if="offer.status === 'accepted'">Angenommen am <strong>{{ formatDate(offer.accepted_at) }}</strong></template>
                <template v-else-if="offer.status === 'declined'">Abgelehnt am <strong>{{ formatDate(offer.declined_at) }}</strong></template>
                <template v-else-if="offer.status === 'expired'">Abgelaufen am {{ formatDate(offer.valid_until) }} – nimmt der Kunde trotzdem an, kannst du es weiterhin als angenommen markieren.</template>
                <template v-else>{{ formatMoney(offer.total) }} brutto · gültig bis {{ formatDate(offer.valid_until) }}</template>
            </span>

            <div class="ml-auto flex flex-wrap gap-2">
                <template v-if="!offer.accepted_at && !offer.declined_at">
                    <VButton v-if="!offer.sent_at" variant="secondary" @click="markSent">
                        <span class="inline-flex items-center"><HandRaisedIcon class="mr-2 h-5 w-5" />Als übergeben markieren</span>
                    </VButton>
                    <VButton variant="secondary" @click="decline">
                        <span class="inline-flex items-center"><XCircleIcon class="mr-2 h-5 w-5" />Abgelehnt</span>
                    </VButton>
                    <VButton @click="accept">
                        <span class="inline-flex items-center"><CheckCircleIcon class="mr-2 h-5 w-5" />Angenommen</span>
                    </VButton>
                </template>
                <VButton v-if="offer.accepted_at && !offer.invoice && !inImplementation" :href="route('invoices.create', { offer: offer.id })" icon="DocumentTextIcon">Rechnung erstellen</VButton>
                <VButton v-if="canReopen" variant="secondary" @click="reopen">
                    <span class="inline-flex items-center"><ArrowUturnLeftIcon class="mr-2 h-5 w-5" />Zurücksetzen</span>
                </VButton>
            </div>
        </section>

        <!-- Accepted: what came out of it -->
        <div v-if="inImplementation" class="mb-6 rounded-xl bg-primary-500/[0.06] px-4 py-3 text-sm text-gray-700 ring-1 ring-primary-500/25 dark:text-gray-300">
            Verrechnet wird bei Fertigstellung. Nächster Schritt: <strong>das Projekt umsetzen</strong>. Sobald die Arbeit fertig ist, im
            <Link :href="route('projects.show', offer.project.id)" class="font-semibold underline">Projekt</Link>
            „Umsetzung abschließen“ – dann kommt die Projektrechnung.
        </div>
        <div v-else-if="offer.accepted_at && !offer.invoice" class="mb-6 rounded-xl bg-primary-500/[0.06] px-4 py-3 text-sm text-gray-700 ring-1 ring-primary-500/25 dark:text-gray-300">
            Nächster Schritt: <strong>Rechnung erstellen</strong> für das Projekt. Der Wartungsvertrag wird dabei angelegt und wartet auf den Go-Live – beim Start kommt die Wartungsrechnung.
        </div>
        <div v-if="offer.maintenance_contract || offer.invoice" class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl bg-primary-500/[0.06] px-4 py-3 text-sm text-gray-700 ring-1 ring-primary-500/25 dark:text-gray-300">
            <span v-if="offer.maintenance_contract" class="inline-flex flex-wrap items-center gap-1.5">
                Wartungsvertrag
                <Link :href="route('contracts.edit', offer.maintenance_contract.id)" class="font-semibold underline">{{ offer.maintenance_contract.name }}</Link>
                <ContractStatusBadge :status="offer.maintenance_contract.status" />
            </span>
            <span v-if="offer.invoice" class="inline-flex items-center gap-1.5">
                <DocumentTextIcon class="h-4 w-4 text-primary-500" />Verrechnet mit Rechnung
                <Link :href="route('invoices.edit', offer.invoice.id)" class="font-mono font-semibold underline">{{ offer.invoice.number }}</Link>
            </span>
            <span v-if="offer.maintenance_contract?.status === 'pending'" class="text-gray-500">Sobald die Seite live ist: Vertrag starten, dann geht die Wartungsrechnung raus.</span>
        </div>

        <!-- Sent -->
        <div v-if="offer.sent_at" class="mb-6 flex items-center gap-3 rounded-xl bg-gray-500/[0.06] px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-500/20 dark:text-gray-300">
            <LockClosedIcon class="h-5 w-5 shrink-0 text-gray-400" />
            <span>
                <template v-if="offer.sent_to">Per E-Mail verschickt am <strong>{{ formatDateTime(offer.sent_at) }}</strong> an <strong>{{ offer.sent_to }}</strong>.</template>
                <template v-else>Übergeben am <strong>{{ formatDateTime(offer.sent_at) }}</strong>.</template>
                Es bleibt so, wie der Kunde es bekommen hat – für Änderungen „Kopie erstellen“.
            </span>
        </div>

        <OfferForm :offer="offer" :customers="customers" :projects="projects" :catalog="catalog" :types="types" :packages="packages" :features="features"
            :integrations="integrations" :contentDelivery="contentDelivery" :paymentTerms="paymentTerms" :hints="hints"
            :readonly="locked" is-edit />

        <SendInvoiceModal :show="sending" :document="offer" label="Angebot" :action="route('offers.send', offer.id)" :mail="mail" @close="sending = false" />

    </Layout>
</template>
