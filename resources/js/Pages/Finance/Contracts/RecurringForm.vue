<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowPathIcon, CalculatorIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VButton from '@/components/VButton.vue';
import { formatMoney, today } from '@/utils/format';
import { parseAmount, toAmountInput } from '@/utils/amount';

const props = defineProps({
    contract: { type: Object, default: null },
    categories: Array,
    intervals: Array,
    scope: { type: String, default: 'private' },
});

const defaultCategory = (type, scope) => props.categories.find(category => category.type === type && category.scopes.includes(scope))?.value ?? '';

const form = useForm({
    name: props.contract?.name ?? '',
    type: props.contract?.type ?? 'expense',
    scope: props.contract?.scope ?? props.scope,
    category: props.contract?.category ?? defaultCategory('expense', props.scope),
    amount: toAmountInput(props.contract?.amount),
    interval_months: props.contract?.interval_months ?? 1,
    share_percent: props.contract?.share_percent ?? 100,
    start_date: props.contract?.start_date?.substring(0, 10) ?? `${today().substring(0, 7)}-01`,
    end_date: props.contract?.end_date?.substring(0, 10) ?? '',
    notes: props.contract?.notes ?? '',
    change_from: '',
});

const categoryOptions = computed(() => {
    const ofType = props.categories.filter(category => category.type === form.type);
    return [...ofType.filter(category => category.scopes.includes(form.scope)), ...ofType.filter(category => !category.scopes.includes(form.scope))];
});

watch(() => [form.type, form.scope], () => {
    const current = props.categories.find(category => category.value === form.category);
    if (!current || current.type !== form.type) form.category = defaultCategory(form.type, form.scope);
});

const shared = ref(form.share_percent < 100);
watch(shared, (value) => { if (!value) form.share_percent = 100; else if (form.share_percent === 100) form.share_percent = 50; });

// Live calculation
const amount = computed(() => parseAmount(form.amount) ?? 0);
const own = computed(() => Math.round(amount.value * form.share_percent) / 100);
const monthly = computed(() => own.value / form.interval_months);

// Changing the amount of an existing contract: ask from when the new price applies
const amountChanged = computed(() => props.contract && amount.value !== Number(props.contract.amount));

const submit = () => {
    if (props.contract)
        form.put(route('finance.contracts.update', props.contract.id), { preserveScroll: true });
    else
        form.post(route('finance.contracts.store'));
};

const toggleClass = (active, tone) => active
    ? (tone === 'business' ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : tone === 'private' ? 'bg-accent/15 text-accent' : tone === 'income' ? 'bg-primary-500 text-white' : 'bg-gray-900 text-white dark:bg-white dark:text-gray-900')
    : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200';
</script>

<template>
    <form @submit.prevent="submit" class="mx-auto grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="card space-y-5 p-6 lg:col-span-2">
            <h2 class="card-title"><ArrowPathIcon class="h-5 w-5 text-primary-500" />Vertrag</h2>

            <div class="flex flex-wrap gap-2">
                <div class="flex gap-1 rounded-xl p-1 text-sm ring-1 ring-gray-200 ring-inset dark:ring-white/10">
                    <button v-for="option in [{ value: 'expense', label: 'Ausgabe' }, { value: 'income', label: 'Einnahme' }]" :key="option.value" type="button"
                        class="cursor-pointer rounded-lg px-3 py-1 font-medium transition" :class="toggleClass(form.type === option.value, option.value)"
                        @click="form.type = option.value">{{ option.label }}</button>
                </div>
                <div class="flex gap-1 rounded-xl p-1 text-sm ring-1 ring-gray-200 ring-inset dark:ring-white/10">
                    <button v-for="option in [{ value: 'private', label: 'Privat' }, { value: 'business', label: 'Firma' }]" :key="option.value" type="button"
                        class="cursor-pointer rounded-lg px-3 py-1 font-medium transition" :class="toggleClass(form.scope === option.value, option.value)"
                        @click="form.scope = option.value">{{ option.label }}</button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <VInput id="name" label="Bezeichnung" :placeholder="form.type === 'income' ? 'z. B. Nettogehalt' : 'z. B. Handy (Magenta), Miete, Hosting'" v-model="form.name" :error="form.errors.name" class="sm:col-span-2" mandatory />
                <VSelect id="category" label="Kategorie" v-model="form.category" :options="categoryOptions" :error="form.errors.category" />
                <VSelect id="interval_months" label="Zahlung" v-model.number="form.interval_months" :options="intervals" :error="form.errors.interval_months" />
                <VInput id="amount" label="Betrag pro Zahlung (€)" inputmode="decimal" placeholder="0,00" v-model="form.amount" :error="form.errors.amount" mandatory />
                <VInput id="start_date" type="date" label="Erste Zahlung" v-model="form.start_date" :error="form.errors.start_date" tooltip="Legt fest, in welchen Monaten gezahlt wird – bei jährlichen Abos der Monat der Abbuchung." mandatory />

                <div v-if="amountChanged" class="rounded-xl bg-accent/[0.08] p-3 ring-1 ring-accent/30 ring-inset sm:col-span-2">
                    <VInput id="change_from" type="month" label="Neuer Betrag gilt ab (optional)" :modelValue="form.change_from.substring(0, 7)" @update:modelValue="form.change_from = $event ? `${$event}-01` : ''" :error="form.errors.change_from" class="max-w-60" />
                    <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400">Preis geändert? Mit Datum bleiben die Monate davor beim alten Betrag. Ohne Datum gilt der neue Betrag rückwirkend für alle Monate.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                        <input type="checkbox" v-model="shared" class="form-checkbox rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5" />
                        Geteilter Vertrag – ich zahle nur einen Anteil
                    </label>
                    <div v-if="shared" class="mt-2 flex items-center gap-3">
                        <input type="range" min="5" max="95" step="5" v-model.number="form.share_percent" class="w-48 accent-primary-500" />
                        <span class="text-sm font-semibold text-gray-900 tabular-nums dark:text-white">{{ form.share_percent }} %</span>
                        <button v-for="preset in [50, 33]" :key="preset" type="button" class="cursor-pointer rounded-md px-2 py-0.5 text-xs text-gray-500 ring-1 ring-gray-200 hover:text-primary-500 dark:ring-white/10" @click="form.share_percent = preset">{{ preset === 33 ? '⅓' : '½' }}</button>
                    </div>
                </div>

                <VInput id="end_date" type="date" label="Endet am (optional)" v-model="form.end_date" :error="form.errors.end_date" />
            </div>

            <div>
                <label for="notes" class="mb-1 block text-sm font-medium">Notizen (optional)</label>
                <textarea id="notes" v-model="form.notes" rows="2" class="form-textarea w-full" placeholder="z. B. Vertragsnummer, Kündigungsfrist, Bindung bis"></textarea>
            </div>
        </section>

        <!-- Calculation -->
        <aside class="space-y-6">
            <section class="card relative overflow-hidden p-6 lg:sticky lg:top-24">
                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-primary-500/20 blur-[70px]" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="card-title"><CalculatorIcon class="h-5 w-5 text-primary-500" />Berechnung</h2>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Pro Zahlung</dt><dd class="tabular-nums">{{ formatMoney(amount) }}</dd></div>
                        <div v-if="form.share_percent < 100" class="flex justify-between"><dt class="text-gray-500">Dein Anteil ({{ form.share_percent }} %)</dt><dd class="tabular-nums">{{ formatMoney(own) }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 dark:border-white/10">
                            <dt class="font-semibold text-gray-900 dark:text-white">Pro Monat</dt>
                            <dd class="font-display text-xl font-semibold text-primary-600 tabular-nums dark:text-primary-300">{{ formatMoney(monthly) }}</dd>
                        </div>
                        <div class="flex justify-between"><dt class="text-gray-500">Pro Jahr</dt><dd class="font-medium tabular-nums">{{ formatMoney(monthly * 12) }}</dd></div>
                    </dl>
                    <p v-if="form.interval_months > 1" class="mt-3 text-xs text-gray-500">
                        Gezahlt wird {{ intervals.find(i => i.value === form.interval_months)?.label.toLowerCase() }}. In der Übersicht steht der Betrag im Monat der Zahlung, hier siehst du ihn auf die Monate verteilt.
                    </p>
                </div>
            </section>
        </aside>

        <div class="flex items-center justify-end gap-3 lg:col-span-3">
            <VButton :href="route('finance.contracts.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">{{ contract ? 'Speichern' : 'Vertrag anlegen' }}</VButton>
        </div>
    </form>
</template>
