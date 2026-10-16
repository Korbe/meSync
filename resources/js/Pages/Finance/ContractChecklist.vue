<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CheckIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import { formatMoney } from '@/utils/format';
import { parseAmount, toAmountInput } from '@/utils/amount';

const props = defineProps({
    contracts: Array,   // due this month, each with "payment" (null = not confirmed)
    month: String,      // YYYY-MM
    scope: String,
});

// Amount per contract as on the bank statement, prefilled with the planned one
const amounts = reactive(Object.fromEntries(props.contracts.map(contract => [contract.id, toAmountInput(contract.amount)])));
const adopt = reactive({});
const busy = ref(null);

const confirmed = computed(() => props.contracts.filter(contract => contract.payment));
const progress = computed(() => props.contracts.length ? confirmed.value.length / props.contracts.length * 100 : 0);
const isFuture = computed(() => props.month > new Date().toISOString().substring(0, 7));

const differs = (contract) => {
    const amount = parseAmount(amounts[contract.id]);
    return amount !== null && amount !== Number(contract.amount);
};

const confirmPayment = (contract) => {
    busy.value = contract.id;
    router.post(route('finance.payments.store', contract.id), {
        period: props.month,
        amount: amounts[contract.id],
        adopt: differs(contract) && adopt[contract.id] ? 1 : 0,
    }, { preserveScroll: true, onFinish: () => busy.value = null });
};

const undo = (contract) => {
    busy.value = contract.id;
    router.delete(route('finance.payments.destroy', contract.id), {
        data: { period: props.month },
        preserveScroll: true,
        onFinish: () => busy.value = null,
    });
};

const confirmAll = () => {
    busy.value = 'all';
    router.post(route('finance.payments.confirm-all'), { period: props.month, scope: props.scope }, { preserveScroll: true, onFinish: () => busy.value = null });
};

const ownShare = (contract, amount) => Math.round(amount * contract.share_percent) / 100;
</script>

<template>
    <section class="card overflow-hidden">
        <div class="p-6 pb-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="card-title"><ClipboardDocumentCheckIcon class="h-5 w-5 text-primary-500" />Kontoabgleich</h2>
                <Link :href="route('finance.contracts.index')" class="text-xs text-gray-500 underline hover:text-primary-500">Verträge</Link>
            </div>
            <template v-if="contracts.length">
                <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                    <span>{{ confirmed.length }} von {{ contracts.length }} bestätigt</span>
                    <button v-if="!isFuture && confirmed.length < contracts.length" type="button" :disabled="busy !== null"
                        class="cursor-pointer font-medium text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-300" @click="confirmAll">
                        Alle wie geplant bestätigen
                    </button>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                    <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: `${progress}%` }"></div>
                </div>
            </template>
            <p v-else class="mt-2 text-sm text-gray-500">In diesem Monat ist kein Vertrag fällig.</p>
            <p v-if="isFuture && contracts.length" class="mt-2 text-xs text-gray-500">Dieser Monat liegt in der Zukunft – bestätigen kannst du, sobald die Abbuchung da ist.</p>
        </div>

        <div v-for="contract in contracts" :key="contract.id" class="border-t border-gray-100 px-6 py-3 dark:border-white/[0.06]"
            :class="contract.payment && 'bg-primary-500/[0.03]'">
            <div class="flex items-center gap-3">
                <!-- Check -->
                <button type="button" :disabled="busy !== null || (isFuture && !contract.payment)"
                    class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full ring-1 transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="contract.payment
                        ? 'bg-primary-500 text-white ring-primary-500 hover:bg-primary-600'
                        : 'text-transparent ring-gray-300 hover:text-primary-500 hover:ring-primary-500 dark:ring-white/20'"
                    :title="contract.payment ? 'Bestätigung zurücknehmen' : 'Abbuchung am Kontoauszug bestätigen'"
                    @click="contract.payment ? undo(contract) : confirmPayment(contract)">
                    <CheckIcon class="h-3.5 w-3.5" stroke-width="3" />
                </button>

                <div class="min-w-0 flex-1">
                    <Link :href="route('finance.contracts.edit', contract.id)" class="flex items-center gap-1.5 text-sm text-gray-800 hover:text-primary-600 dark:text-gray-200 dark:hover:text-primary-300">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="contract.scope === 'business' ? 'bg-primary-500' : 'bg-accent'"></span>
                        <span class="truncate">{{ contract.name }}</span>
                    </Link>
                    <div class="text-xs text-gray-500">
                        {{ contract.type === 'income' ? 'Eingang' : 'Abbuchung' }}<template v-if="contract.share_percent < 100"> · dein Anteil {{ contract.share_percent }} %</template>
                    </div>
                </div>

                <!-- Amount: shown when confirmed, editable before -->
                <div v-if="contract.payment" class="text-right">
                    <div class="text-sm font-medium tabular-nums" :class="contract.type === 'income' ? 'text-primary-600 dark:text-primary-300' : 'text-gray-900 dark:text-gray-100'">
                        {{ formatMoney(contract.payment.amount) }}
                    </div>
                    <div v-if="Number(contract.payment.amount) !== Number(contract.amount)" class="text-[11px] text-accent">geplant {{ formatMoney(contract.amount) }}</div>
                    <div v-else-if="contract.share_percent < 100" class="text-[11px] text-gray-400">Anteil {{ formatMoney(ownShare(contract, contract.payment.amount)) }}</div>
                </div>
                <div v-else class="relative w-28">
                    <input v-model="amounts[contract.id]" inputmode="decimal" :disabled="isFuture"
                        class="form-input w-full py-1 pr-6 text-right text-sm tabular-nums" :class="differs(contract) && 'border-accent! ring-1 ring-accent/40'"
                        @keydown.enter.prevent="confirmPayment(contract)" />
                    <span class="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-xs text-gray-400">€</span>
                </div>
            </div>

            <label v-if="!contract.payment && differs(contract)" class="mt-2 ml-9 flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                <input type="checkbox" v-model="adopt[contract.id]" class="form-checkbox h-3.5 w-3.5 rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5" />
                Als neuen Preis ab diesem Monat übernehmen (statt {{ formatMoney(contract.amount) }})
            </label>
        </div>
    </section>
</template>
