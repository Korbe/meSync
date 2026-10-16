<script setup>
import { router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import RecurringForm from './RecurringForm.vue';
import { confirm } from '@/composables/useConfirm';
import { ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import { formatDate, formatMoney } from '@/utils/format';

const monthName = (period) => new Date(`${period.substring(0, 10)}T00:00:00`).toLocaleDateString('de-AT', { month: 'long', year: 'numeric' });

const props = defineProps({
    contract: Object,
    payments: Array,
    categories: Array,
    intervals: Array,
});

const remove = async () => {
    if (!(await confirm({
        title: `„${props.contract.name}“ löschen?`,
        message: 'Der Vertrag verschwindet auch aus allen vergangenen Monaten. Wenn du ihn gekündigt hast, trag besser ein Enddatum ein – dann bleibt die Vergangenheit stimmig.',
        confirmText: 'Endgültig löschen',
        variant: 'danger',
    }))) return;

    router.delete(route('finance.contracts.destroy', props.contract.id));
};
</script>

<template>
    <Layout :title="contract.name" :backTo="route('finance.contracts.index')">
        <template v-slot:actions>
            <VButton variant="secondary" icon="TrashIcon" @click="remove">Löschen</VButton>
        </template>

        <!-- Re-mount after saving, so the form shows the stored values -->
        <RecurringForm :key="`${contract.id}-${contract.updated_at}`" :contract="contract" :categories="categories" :intervals="intervals" />

        <!-- Payments confirmed against the bank statement -->
        <section class="card mx-auto mt-6 max-w-5xl overflow-hidden">
            <div class="p-6 pb-3">
                <h2 class="card-title"><ClipboardDocumentCheckIcon class="h-5 w-5 text-primary-500" />Bestätigte Zahlungen</h2>
                <p class="mt-1 text-sm text-gray-500">Abgehakt im Kontoabgleich unter Buchungen.</p>
            </div>
            <p v-if="payments.length === 0" class="px-6 pb-6 text-sm text-gray-500">Noch keine Zahlung bestätigt.</p>
            <table v-else class="w-full text-left text-sm">
                <tbody class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/[0.06] dark:border-white/[0.06]">
                    <tr v-for="payment in payments" :key="payment.id">
                        <td class="px-6 py-2.5 text-gray-900 dark:text-gray-100">{{ monthName(payment.period) }}</td>
                        <td class="px-6 py-2.5 text-right tabular-nums">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(payment.amount) }}</span>
                            <span v-if="Number(payment.amount) !== Number(contract.amount)" class="ml-2 text-xs text-accent">abweichend</span>
                        </td>
                        <td class="hidden px-6 py-2.5 text-right text-xs text-gray-500 sm:table-cell">bestätigt am {{ formatDate(payment.confirmed_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </Layout>
</template>
