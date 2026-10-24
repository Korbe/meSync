<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ClipboardDocumentListIcon, DocumentTextIcon, WrenchScrewdriverIcon, ArrowRightIcon, CheckIcon, ChatBubbleBottomCenterTextIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';
import VModal from '@/components/VModal.vue';
import ProjectFiles from './ProjectFiles.vue';
import OfferStatusBadge from '@/Pages/Offers/OfferStatusBadge.vue';
import PaymentStatusBadge from '@/Pages/Invoices/PaymentStatusBadge.vue';
import ContractStatusBadge from '@/Pages/MaintenanceContracts/StatusBadge.vue';
import { confirm } from '@/composables/useConfirm';
import { formatDate, formatMoney, today } from '@/utils/format';

const props = defineProps({
    project: Object,
    phase: String,
    lost: Boolean,
    next: Object,       // label (situation), hint, action (button), url
    phases: Object,
    offers: Array,
    invoices: Array,
    contract: Object,
    canReopen: Boolean,
    files: Array,
    offerDocuments: Array,   // offers written outside meSync (PDF)
    assignable: Object,   // offers, invoices of the customer without a project
});

// Upload an offer written elsewhere
const offerUpload = useForm({ document: null });
const uploadOffer = (event) => {
    offerUpload.document = event.target.files[0] ?? null;
    event.target.value = '';
    if (offerUpload.document) offerUpload.post(route('projects.offer-documents.store', props.project.id), { forceFormData: true, preserveScroll: true });
};
const removeOfferDocument = async (document) => {
    if (await confirm({ title: `„${document.name}“ löschen?`, confirmText: 'Löschen', variant: 'danger' })) {
        router.delete(route('projects.files.destroy', [props.project.id, document.id]), { preserveScroll: true });
    }
};

// Assign an existing offer or invoice of the customer, or take it off the project
const attach = (type, id) => {
    if (!id) return;
    router.post(route('projects.attach', props.project.id), { type, id }, { preserveScroll: true });
};
const detach = async (type, document) => {
    if (await confirm({
        title: `${type === 'offer' ? 'Angebot' : 'Rechnung'} ${document.number} vom Projekt lösen?`,
        message: 'Sie bleibt erhalten und gehört dann nur keinem Projekt mehr an.',
        confirmText: 'Lösen',
    })) router.delete(route('projects.detach', [props.project.id, type, document.id]), { preserveScroll: true });
};

// The actual work is done: from then on the project invoice can be written
const completing = ref(false);
const completeForm = useForm({ completed_at: today() });
const complete = () => completeForm.post(route('projects.complete', props.project.id), {
    preserveScroll: true,
    onSuccess: () => completing.value = false,
});

// Ordered elsewhere (own contract, by phone or mail): skip the offer
const ordering = ref(false);
const orderForm = useForm({ ordered_at: today(), document: null });
const order = () => orderForm.post(route('projects.order', props.project.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => ordering.value = false,
});
const unorder = async () => {
    if (await confirm({ title: 'Auftrag zurücknehmen?', message: 'Das Projekt ist dann wieder in der Anfrage bzw. beim Angebot.', confirmText: 'Zurücknehmen' })) {
        router.delete(route('projects.unorder', props.project.id), { preserveScroll: true });
    }
};

const reopen = async () => {
    if (await confirm({ title: 'Umsetzung wieder öffnen?', message: 'Das Projekt ist dann wieder in Arbeit, die Projektrechnung kommt erst nach dem nächsten Abschluss.', confirmText: 'Wieder öffnen' })) {
        router.delete(route('projects.reopen', props.project.id), { preserveScroll: true });
    }
};

const steps = computed(() => Object.entries(props.phases).map(([key, label], index, all) => ({
    key,
    label,
    done: all.findIndex(([k]) => k === props.phase) > index,
    current: key === props.phase,
})));

const remove = async () => {
    if (!(await confirm({
        title: `Projekt „${props.project.name}“ löschen?`,
        message: 'Angebote, Rechnungen und der Wartungsvertrag bleiben erhalten – sie gehören dann nur keinem Projekt mehr an.',
        confirmText: 'Löschen',
        variant: 'danger',
    }))) return;

    router.delete(route('projects.destroy', props.project.id));
};
</script>

<template>
    <Layout :title="project.name" :backTo="route('projects.index')">
        <template v-slot:actions>
            <VButton :href="route('offers.create', { project: project.id })" variant="secondary" icon="ClipboardDocumentListIcon">Angebot</VButton>
            <VButton :href="route('invoices.create', { project: project.id })" variant="secondary" icon="DocumentTextIcon">Rechnung</VButton>
            <VButton :href="route('projects.edit', project.id)" variant="secondary" icon="PencilSquareIcon">Bearbeiten</VButton>
            <VButton variant="secondary" icon="TrashIcon" @click="remove">Löschen</VButton>
        </template>

        <div class="-mt-6 mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <span>{{ project.type_label }}</span>
            <span>·</span>
            <Link :href="route('customers.show', project.customer.id)" class="font-medium text-gray-700 hover:text-primary-500 dark:text-gray-300">{{ project.customer.display_name }}</Link>
            <template v-if="project.url">
                <span>·</span>
                <a :href="project.url" target="_blank" rel="noopener" class="hover:text-primary-500">{{ project.url }}</a>
            </template>
        </div>

        <div class="space-y-6">
            <!-- Phases + next step -->
            <section class="card relative overflow-hidden p-6">
                <div class="pointer-events-none absolute -top-20 -right-20 h-64 w-64 rounded-full bg-primary-500/15 blur-[90px]" aria-hidden="true"></div>
                <ol class="relative grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <li v-for="(step, index) in steps" :key="step.key" class="flex items-center gap-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-1"
                            :class="step.done ? 'bg-primary-500 text-white ring-primary-500'
                                : step.current ? 'bg-primary-500/15 text-primary-700 ring-primary-500 dark:text-primary-300'
                                : 'bg-gray-100 text-gray-400 ring-gray-200 dark:bg-white/5 dark:ring-white/10'">
                            <CheckIcon v-if="step.done" class="h-4 w-4" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <span class="text-sm" :class="step.current ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-500'">{{ step.label }}</span>
                    </li>
                </ol>

                <div class="relative mt-6 flex flex-wrap items-center gap-4 border-t border-gray-200 pt-5 dark:border-white/[0.08]">
                    <div class="min-w-0 flex-1">
                        <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Stand</div>
                        <div class="mt-1 font-display text-xl font-semibold text-gray-900 dark:text-white">{{ lost ? 'Angebot abgelehnt oder abgelaufen' : next.label }}</div>
                        <div class="text-sm text-gray-500">{{ next.hint }}</div>
                        <div v-if="project.ordered_at" class="mt-1 text-xs text-gray-500">
                            Beauftragt am {{ formatDate(project.ordered_at) }}
                            <button v-if="!project.completed_at" type="button" class="ml-1 cursor-pointer underline hover:text-primary-500" @click="unorder">zurücknehmen</button>
                        </div>
                        <div v-if="project.completed_at" class="mt-1 text-xs text-gray-500">
                            Umsetzung abgeschlossen am {{ formatDate(project.completed_at) }}
                            <button v-if="canReopen" type="button" class="ml-1 cursor-pointer underline hover:text-primary-500" @click="reopen">wieder öffnen</button>
                        </div>
                    </div>
                    <VButton v-if="['request', 'offer'].includes(phase) && next.method !== 'order'" variant="secondary" icon="CheckBadgeIcon" @click="ordering = true">Ohne Angebot beauftragt</VButton>
                    <VButton v-if="next.method === 'order'" icon="CheckBadgeIcon" @click="ordering = true">{{ next.action }}</VButton>
                    <VButton v-else-if="next.method === 'complete'" icon="CheckIcon" @click="completing = true">{{ next.action }}</VButton>
                    <VButton v-else :href="next.url">
                        <span class="inline-flex items-center">{{ next.action }}<ArrowRightIcon class="ml-2 h-4 w-4" /></span>
                    </VButton>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <!-- Offers -->
                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-3">
                        <h2 class="card-title"><ClipboardDocumentListIcon class="h-5 w-5 text-primary-500" />Angebote</h2>
                        <select class="form-select w-auto py-1 text-xs" value="" :disabled="!assignable.offers.length" @change="attach('offer', $event.target.value); $event.target.value = ''">
                            <option value="" disabled>{{ assignable.offers.length ? 'Vorhandene zuordnen …' : 'Keine weiteren Angebote des Kunden' }}</option>
                            <option v-for="option in assignable.offers" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <p v-if="offers.length === 0 && offerDocuments.length === 0" class="px-6 pb-3 text-sm text-gray-500">Noch kein Angebot.</p>
                    <ul v-if="offerDocuments.length" class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <li v-for="document in offerDocuments" :key="document.id" class="group relative">
                            <button type="button" class="absolute top-1/2 left-1.5 z-10 -translate-y-1/2 cursor-pointer rounded p-0.5 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500 focus:opacity-100" title="Löschen" @click="removeOfferDocument(document)">×</button>
                            <a :href="document.url" target="_blank" rel="noopener" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]">
                                <DocumentTextIcon class="h-5 w-5 shrink-0 text-gray-400" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ document.name }}</div>
                                    <div class="text-xs text-gray-500">Hochgeladen am {{ formatDate(document.created_at) }}</div>
                                </div>
                                <span class="text-xs text-gray-500">PDF öffnen</span>
                            </a>
                        </li>
                    </ul>
                    <ul v-if="offers.length" class="divide-y divide-gray-100 border-gray-100 dark:divide-white/[0.06] dark:border-white/[0.06]" :class="{ 'border-t': offerDocuments.length }">
                        <li v-for="offer in offers" :key="offer.id" class="group relative">
                            <button type="button" class="absolute top-1/2 left-1.5 z-10 -translate-y-1/2 cursor-pointer rounded p-0.5 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500 focus:opacity-100" title="Vom Projekt lösen" @click="detach('offer', offer)">×</button>
                            <Link :href="route('offers.edit', offer.id)" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]">
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">{{ offer.number }}</div>
                                    <div class="text-xs text-gray-500">{{ formatDate(offer.offer_date) }} · + {{ formatMoney(offer.maintenance_monthly_price) }}/Monat</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(offer.total) }}</div>
                                    <OfferStatusBadge :status="offer.status" class="mt-1" />
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <label class="block cursor-pointer border-t border-gray-100 px-6 py-3 text-xs font-medium text-primary-600 hover:bg-primary-500/[0.04] dark:border-white/[0.06] dark:text-primary-300">
                        <input type="file" accept="application/pdf,image/*" class="hidden" :disabled="offerUpload.processing" @change="uploadOffer">
                        {{ offerUpload.processing ? 'Wird hochgeladen …' : '+ Angebot als PDF hochladen (z. B. schon woanders geschrieben)' }}
                    </label>
                    <div v-if="offerUpload.errors.document" class="px-6 pb-3 text-xs text-red-500">{{ offerUpload.errors.document }}</div>
                </section>

                <!-- Invoices -->
                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-3">
                        <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Rechnungen</h2>
                        <select v-if="assignable.invoices.length" class="form-select w-auto py-1 text-xs" value="" @change="attach('invoice', $event.target.value); $event.target.value = ''">
                            <option value="" disabled>Vorhandene zuordnen …</option>
                            <option v-for="option in assignable.invoices" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <p v-if="invoices.length === 0" class="px-6 pb-3 text-sm text-gray-500">Noch keine Rechnung.</p>
                    <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <li v-for="invoice in invoices" :key="invoice.id" class="group relative">
                            <button type="button" class="absolute top-1/2 left-1.5 z-10 -translate-y-1/2 cursor-pointer rounded p-0.5 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500 focus:opacity-100" title="Vom Projekt lösen" @click="detach('invoice', invoice)">×</button>
                            <Link :href="route('invoices.edit', invoice.id)" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]">
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">{{ invoice.number }}</div>
                                    <div class="text-xs text-gray-500">{{ formatDate(invoice.invoice_date) }} · {{ invoice.maintenance_contract_id ? 'Wartung' : 'Projekt' }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(invoice.total) }}</div>
                                    <PaymentStatusBadge :status="invoice.payment_status" class="mt-1" />
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <Link :href="route('invoices.create', { project: project.id })" class="block border-t border-gray-100 px-6 py-3 text-xs font-medium text-primary-600 hover:bg-primary-500/[0.04] dark:border-white/[0.06] dark:text-primary-300">
                        + Rechnung als PDF erfassen (z. B. schon woanders geschrieben)
                    </Link>
                </section>
            </div>

            <!-- Maintenance contract -->
            <section class="card p-6">
                <h2 class="card-title"><WrenchScrewdriverIcon class="h-5 w-5 text-primary-500" />Wartungsvertrag</h2>
                <p v-if="!contract" class="mt-3 text-sm text-gray-500">Entsteht mit der Projektrechnung eines angenommenen Angebots und wartet dann auf den Go-Live.</p>
                <Link v-else :href="route('contracts.edit', contract.id)" class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-primary-400 dark:border-white/10">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ contract.name }}</div>
                        <div class="text-xs text-gray-500">
                            {{ formatMoney(contract.monthly_price) }}/Monat ·
                            <template v-if="contract.status === 'pending'">startet mit dem Go-Live</template>
                            <template v-else-if="contract.status === 'ended'">beendet {{ formatDate(contract.end_date) }}</template>
                            <template v-else>nächste Rechnung {{ formatDate(contract.next_invoice_date) }}</template>
                        </div>
                    </div>
                    <ContractStatusBadge :status="contract.status" />
                </Link>
            </section>

            <ProjectFiles :project="project" :files="files" />

            <section v-if="project.notes" class="card p-6">
                <h2 class="card-title"><ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-primary-500" />Notizen</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ project.notes }}</p>
            </section>
        </div>

        <!-- Ordered without an offer in meSync -->
        <VModal :show="ordering" max-width="md" @close="ordering = false">
            <template #title>{{ offerDocuments.length ? 'Auftrag eintragen' : 'Ohne Angebot beauftragt' }}</template>
            <template #content>
                <p v-if="offerDocuments.length">Der Kunde hat das hochgeladene Angebot angenommen? Dann springt das Projekt in die Umsetzung. Danach folgen Projektrechnung und Wartungsvertrag.</p>
                <p v-else>Der Auftrag kam anderweitig – eigener Vertrag, telefonisch oder per Mail? Dann springt das Projekt direkt in die Umsetzung. Danach folgen Projektrechnung und Wartungsvertrag.</p>
                <VInput id="ordered_at" class="mt-4" type="date" label="Beauftragt am" v-model="orderForm.ordered_at" :error="orderForm.errors.ordered_at" />
                <label class="mt-4 flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-gray-200 p-3 text-sm text-gray-600 transition hover:border-primary-400 dark:border-white/10 dark:text-gray-400">
                    <input type="file" accept="application/pdf,image/*" class="hidden" @change="orderForm.document = $event.target.files[0] ?? null">
                    <span class="min-w-0 truncate">{{ orderForm.document ? orderForm.document.name : 'Auftrag als PDF anhängen (optional)' }}</span>
                </label>
                <div v-if="orderForm.errors.document" class="mt-1 text-xs text-red-500">{{ orderForm.errors.document }}</div>
                <p class="mt-1 text-xs text-gray-500">Landet in den Projektdateien.</p>
            </template>
            <template #footer>
                <VButton variant="secondary" @click="ordering = false">Abbrechen</VButton>
                <VButton :loading="orderForm.processing" :disabled="orderForm.processing" @click="order">Beauftragt</VButton>
            </template>
        </VModal>

        <!-- Implementation finished -->
        <VModal :show="completing" max-width="md" @close="completing = false">
            <template #title>Umsetzung abschließen</template>
            <template #content>
                <p>Die eigentliche Arbeit ist fertig? Danach erstellst du die Projektrechnung. Das Datum wird beim Vertragsstart als Go-Live vorgeschlagen.</p>
                <VInput id="completed_at" class="mt-4" type="date" label="Fertig am" v-model="completeForm.completed_at" :error="completeForm.errors.completed_at" />
            </template>
            <template #footer>
                <VButton variant="secondary" @click="completing = false">Abbrechen</VButton>
                <VButton :loading="completeForm.processing" :disabled="completeForm.processing" @click="complete">Abschließen</VButton>
            </template>
        </VModal>
    </Layout>
</template>
