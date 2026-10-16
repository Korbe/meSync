<script setup>
import { confirm } from '@/composables/useConfirm';
import { router, useForm } from '@inertiajs/vue3';
import { BanknotesIcon, CheckCircleIcon, TrashIcon } from '@heroicons/vue/24/outline';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';
import PaymentStatusBadge from './PaymentStatusBadge.vue';
import { formatDate, formatMoney, relativeDays, today } from '@/utils/format';

const props = defineProps({
    invoice: { type: Object, required: true },
});

const toFormNumber = (value) => String(Number(value)).replace('.', ',');

const form = useForm({
    paid_at: today(),
    amount: toFormNumber(props.invoice.outstanding),
    method: 'transfer',
    note: '',
});

const methods = [
    { value: 'transfer', label: 'Überweisung' },
    { value: 'cash', label: 'Bar' },
    { value: 'card', label: 'Karte' },
];
const methodLabel = (value) => methods.find(m => m.value === value)?.label ?? value;

const record = (amount = null) => {
    if (amount !== null) form.amount = toFormNumber(amount);

    form.post(route('invoices.payments.store', props.invoice.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('note');
            form.amount = toFormNumber(props.invoice.outstanding);
        },
    });
};

const remove = async (payment) => {
    if (!(await confirm({
        title: 'Zahlung entfernen?',
        message: `Die Zahlung über ${formatMoney(payment.amount)} vom ${formatDate(payment.paid_at)} wird gelöscht.`,
        confirmText: 'Zahlung entfernen',
        variant: 'danger',
    }))) return;

    router.delete(route('invoices.payments.destroy', [props.invoice.id, payment.id]), { preserveScroll: true });
};
</script>

<template>
    <section class="card relative mb-6 overflow-hidden p-6">
        <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full blur-[90px]"
            :class="invoice.payment_status === 'overdue' ? 'bg-red-500/20' : 'bg-primary-500/15'" aria-hidden="true"></div>

        <div class="relative">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="card-title"><BanknotesIcon class="h-5 w-5 text-primary-500" />Zahlung</h2>
                <PaymentStatusBadge :status="invoice.payment_status" />
            </div>

            <p class="mt-1 text-sm text-gray-500">Nur für deinen Überblick und den Export. Die PDF bleibt so, wie du sie verschickt hast.</p>

            <!-- Amounts -->
            <div class="mt-5 grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                    <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Zu zahlen</div>
                    <div class="mt-1 font-display text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(invoice.amount_due) }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                    <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Eingegangen</div>
                    <div class="mt-1 font-display text-xl font-bold text-primary-600 dark:text-primary-300">{{ formatMoney(invoice.received) }}</div>
                </div>
                <div class="rounded-xl p-3" :class="invoice.outstanding > 0 ? 'bg-primary-500/10 ring-1 ring-primary-500/25' : 'bg-gray-50 dark:bg-white/[0.03]'">
                    <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Offen</div>
                    <div class="mt-1 font-display text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(invoice.outstanding) }}</div>
                    <div v-if="invoice.outstanding > 0" class="text-xs" :class="invoice.payment_status === 'overdue' ? 'text-red-600 dark:text-red-400' : 'text-gray-500'">
                        fällig {{ formatDate(invoice.due_date) }} ({{ relativeDays(invoice.due_date) }})
                    </div>
                </div>
            </div>

            <!-- Payments -->
            <ul v-if="invoice.payments.length" class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-white/[0.06] dark:border-white/10">
                <li v-for="payment in invoice.payments" :key="payment.id" class="flex items-center gap-3 px-4 py-2.5">
                    <CheckCircleIcon class="h-5 w-5 shrink-0 text-primary-500" />
                    <div class="min-w-0 flex-1 text-sm">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(payment.amount) }}</span>
                        <span class="text-gray-500"> am {{ formatDate(payment.paid_at) }}</span>
                        <span class="text-gray-500"> · {{ methodLabel(payment.method) }}</span>
                        <span v-if="payment.note" class="text-gray-500"> · {{ payment.note }}</span>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-500/10 hover:text-red-500" title="Zahlung entfernen" @click="remove(payment)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </li>
            </ul>

            <!-- Record payment -->
            <form v-if="invoice.outstanding > 0" @submit.prevent="record()" class="mt-5 grid grid-cols-1 items-end gap-3 border-t border-gray-200 pt-5 sm:grid-cols-12 dark:border-white/[0.08]">
                <VInput id="paid_at" class="sm:col-span-3" type="date" label="Eingegangen am" v-model="form.paid_at" :error="form.errors.paid_at" />
                <VInput id="pay_amount" class="sm:col-span-3" label="Betrag" suffix="€" v-model="form.amount" :error="form.errors.amount" />
                <div class="sm:col-span-6">
                    <span class="mb-1 block text-sm font-medium">Zahlungsart</span>
                    <div class="inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-white/10">
                        <button v-for="method in methods" :key="method.value" type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="form.method === method.value ? 'bg-primary-500 text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                            @click="form.method = method.value">{{ method.label }}</button>
                    </div>
                </div>
                <VInput id="pay_note" class="sm:col-span-12" label="Notiz (optional)" placeholder="z. B. Teilzahlung" v-model="form.note" :error="form.errors.note" />
                <div class="flex flex-wrap gap-3 sm:col-span-12">
                    <VButton type="submit" variant="secondary" :disabled="form.processing">Zahlung erfassen</VButton>
                    <VButton icon="CheckIcon" :loading="form.processing" :disabled="form.processing" @click="record(invoice.outstanding)">
                        Vollständig bezahlt
                    </VButton>
                </div>
            </form>
        </div>
    </section>
</template>
