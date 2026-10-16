<script setup>
import { confirm } from '@/composables/useConfirm';
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { BanknotesIcon, ClockIcon, ArrowUturnLeftIcon, CheckCircleIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';
import ContractForm from './ContractForm.vue';
import StatusBadge from './StatusBadge.vue';
import MonitorCard from './MonitorCard.vue';
import { formatDate, formatMoney, relativeDays, toDate, toIsoDate, today } from '@/utils/format';

const props = defineProps({
    contract: Object,
    customers: Array,
    types: Array,
});

const invoiceForm = useForm({
    invoiced_at: today(),
    invoice_number: '',
});

const periodEnd = computed(() => {
    const end = toDate(props.contract.next_invoice_date);
    end.setFullYear(end.getFullYear() + 1);
    end.setDate(end.getDate() - 1);
    return toIsoDate(end);
});

const markInvoiced = () => {
    invoiceForm.post(route('contracts.invoices.store', props.contract.id), {
        preserveScroll: true,
        onSuccess: () => invoiceForm.reset('invoice_number'),
    });
};

const undo = async (invoice) => {
    if (!(await confirm({
        title: 'Verrechnung rückgängig machen?',
        message: `Die Verrechnung für ${formatDate(invoice.period_start)} – ${formatDate(invoice.period_end)} wird entfernt und der Vertrag ist wieder fällig.`,
        confirmText: 'Rückgängig machen',
        variant: 'danger',
    }))) return;

    router.delete(route('contracts.invoices.destroy', [props.contract.id, invoice.id]), { preserveScroll: true });
};

const dueClass = computed(() => ({
    overdue: 'text-red-600 dark:text-red-400',
    due_soon: 'text-amber-600 dark:text-accent',
}[props.contract.status] ?? 'text-primary-600 dark:text-primary-300'));
</script>

<template>
    <Layout :title="contract.name" :backTo="route('contracts.index')">
        <template v-slot:actions>
            <a :href="route('contracts.pdf', contract.id)" class="btn cursor-pointer border-gray-200! bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10! dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10" title="Vertragsinformation für den Kunden als PDF">
                <DocumentArrowDownIcon class="mr-2 h-5 w-5" />Vertrags-PDF
            </a>
            <VButton :href="route('contracts.delete', contract.id)" variant="secondary" icon="TrashIcon">Löschen</VButton>
        </template>

        <!-- Subtitle -->
        <div class="-mt-6 mb-6 flex flex-wrap items-center gap-3 text-sm text-gray-500">
            <StatusBadge :status="contract.status" />
            <span>{{ contract.type_label }}</span>
            <span>·</span>
            <Link :href="route('customers.edit', contract.customer.id)" class="font-medium text-gray-700 hover:text-primary-500 dark:text-gray-300">{{ contract.customer.display_name }}</Link>
            <template v-if="contract.url">
                <span>·</span>
                <a :href="contract.url" target="_blank" rel="noopener" class="hover:text-primary-500">{{ contract.url }}</a>
            </template>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Billing panel -->
            <section class="card relative overflow-hidden p-6 xl:col-span-2">
                <div class="pointer-events-none absolute -top-20 -right-20 h-64 w-64 rounded-full blur-[90px]"
                    :class="contract.status === 'overdue' ? 'bg-red-500/20' : 'bg-primary-500/20'" aria-hidden="true"></div>

                <div class="relative">
                    <h2 class="card-title"><BanknotesIcon class="h-5 w-5 text-primary-500" />Nächste Rechnung</h2>

                    <template v-if="contract.status !== 'ended'">
                        <div class="mt-5 flex flex-wrap items-end justify-between gap-6">
                            <div>
                                <div class="font-display text-4xl font-bold tracking-tight text-gray-900 dark:text-white">
                                    {{ formatDate(contract.next_invoice_date) }}
                                </div>
                                <div class="mt-1 text-sm font-medium" :class="dueClass">
                                    {{ contract.status === 'overdue' ? 'Überfällig' : 'Fällig' }} {{ relativeDays(contract.next_invoice_date) }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-display text-3xl font-bold text-gray-900 dark:text-white">{{ formatMoney(contract.annual_price) }}</div>
                                <div class="mt-1 text-sm text-gray-500">Zeitraum {{ formatDate(contract.next_invoice_date) }} – {{ formatDate(periodEnd) }}</div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-6 dark:border-white/[0.08]">
                            <VButton :href="route('invoices.create', { contract: contract.id })" icon="DocumentTextIcon">Rechnung erstellen</VButton>
                            <span class="text-sm text-gray-500">Rechnung mit vorausgefüllter Position, markiert den Vertrag automatisch als verrechnet.</span>
                        </div>

                        <p class="mt-6 text-xs font-medium uppercase tracking-[0.18em] text-gray-500 font-mono">Oder Rechnung von woanders vermerken</p>
                        <form @submit.prevent="markInvoiced" class="mt-3 grid grid-cols-1 items-end gap-4 sm:grid-cols-3">
                            <VInput id="invoiced_at" type="date" label="Gesendet am" v-model="invoiceForm.invoiced_at" :error="invoiceForm.errors.invoiced_at" />
                            <VInput id="invoice_number" label="Rechnungsnummer (optional)" placeholder="RE-2026-001" v-model="invoiceForm.invoice_number" :error="invoiceForm.errors.invoice_number" />
                            <VButton type="submit" icon="CheckIcon" :loading="invoiceForm.processing" :disabled="invoiceForm.processing">Rechnung gesendet</VButton>
                        </form>
                    </template>

                    <p v-else class="mt-5 text-sm text-gray-500">
                        Der Vertrag ist zum {{ formatDate(contract.end_date) }} beendet, es ist keine Rechnung mehr fällig.
                    </p>
                </div>
            </section>

            <!-- Invoice history -->
            <section class="card p-6">
                <h2 class="card-title"><ClockIcon class="h-5 w-5 text-primary-500" />Verlauf</h2>

                <p v-if="contract.invoices.length === 0" class="mt-5 text-sm text-gray-500">Noch keine Rechnung vermerkt.</p>

                <ol v-else class="mt-5 space-y-4">
                    <li v-for="(invoice, index) in contract.invoices" :key="invoice.id" class="flex gap-3">
                        <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-primary-500" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(invoice.amount) }}</span>
                                <button v-if="index === 0" type="button" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Rückgängig machen" @click="undo(invoice)">
                                    <ArrowUturnLeftIcon class="h-3.5 w-3.5" />Rückgängig
                                </button>
                            </div>
                            <div class="text-xs text-gray-500">{{ formatDate(invoice.period_start) }} – {{ formatDate(invoice.period_end) }}</div>
                            <div class="text-xs text-gray-500">
                                Gesendet {{ formatDate(invoice.invoiced_at) }}
                                <template v-if="invoice.invoice_id"> · <Link :href="route('invoices.edit', invoice.invoice_id)" class="font-mono text-primary-600 hover:underline dark:text-primary-300">{{ invoice.invoice_number }}</Link></template>
                                <template v-else-if="invoice.invoice_number"> · <span class="font-mono">{{ invoice.invoice_number }}</span></template>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <MonitorCard :contract="contract" class="mb-6" />

        <ContractForm :contract="contract" :customers="customers" :types="types" />
    </Layout>
</template>
