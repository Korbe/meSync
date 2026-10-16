<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Squares2X2Icon, TagIcon, ClockIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VButton from '@/components/VButton.vue';
import { formatMoney } from '@/utils/format';
import { breakdown, formatDuration, priceForMinutes } from '@/utils/timePricing';

const props = defineProps({
    item: { type: Object, default: null },
});

const units = ['Stück', 'Stunde', 'Pauschale', 'Monat', 'Jahr', 'Tag'];
const toFormNumber = (value) => value === null || value === undefined || value === '' ? '' : String(Number(value)).replace('.', ',');
const parseNumber = (value) => Number(String(value ?? '').replace(',', '.')) || 0;

const form = useForm({
    name: props.item?.name ?? '',
    description: props.item?.description ?? '',
    unit: props.item?.unit ?? 'Stück',
    unit_price: toFormNumber(props.item?.unit_price),
    pricing: props.item?.pricing ?? 'fixed',
    base_minutes: String(props.item?.base_minutes ?? 30),
    step_minutes: String(props.item?.step_minutes ?? 15),
    step_price: toFormNumber(props.item?.step_price),
});

const pricingOptions = [
    { value: 'fixed', label: 'Fixpreis', hint: 'z. B. Website Basic, Windows-Installation', icon: TagIcon },
    { value: 'time', label: 'Zeittarif', hint: 'z. B. 50 € erste halbe Stunde, dann 20 € je Viertelstunde', icon: ClockIcon },
];

// Live example calculation for the time rate
const rate = computed(() => ({
    unit_price: parseNumber(form.unit_price),
    base_minutes: parseInt(form.base_minutes) || 0,
    step_minutes: parseInt(form.step_minutes) || 1,
    step_price: parseNumber(form.step_price),
}));
const examples = computed(() => [30, 45, 60, 90, 120].map(minutes => ({
    minutes,
    price: priceForMinutes(rate.value, minutes),
    breakdown: breakdown(rate.value, minutes),
})));

const submit = () => {
    if (props.item)
        form.put(route('catalog.update', props.item.id));
    else
        form.post(route('catalog.store'));
};
</script>

<template>
    <form @submit.prevent="submit" class="mx-auto max-w-3xl space-y-6">
        <section class="card p-6">
            <h2 class="card-title"><Squares2X2Icon class="h-5 w-5 text-primary-500" />Leistung</h2>

            <div class="mt-5 space-y-4">
                <VInput id="name" label="Bezeichnung" placeholder="z. B. PC-Hilfe vor Ort" v-model="form.name" :error="form.errors.name" mandatory />

                <div>
                    <label for="description" class="block text-sm font-medium mb-1">Details (optional)</label>
                    <textarea id="description" v-model="form.description" rows="2" class="form-textarea w-full" placeholder="Erscheint auf der Rechnung klein unter der Bezeichnung"></textarea>
                    <div v-if="form.errors.description" class="text-xs mt-1 text-red-500">{{ form.errors.description }}</div>
                </div>

                <!-- Pricing type -->
                <div>
                    <span class="mb-1 block text-sm font-medium">Preis</span>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label v-for="option in pricingOptions" :key="option.value"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                            :class="form.pricing === option.value
                                ? 'border-primary-500 bg-primary-500/[0.08] shadow-[0_10px_30px_-12px_rgba(31,173,174,0.6)]'
                                : 'border-gray-200 hover:border-gray-300 dark:border-white/10 dark:hover:border-white/20'">
                            <input type="radio" class="sr-only" :value="option.value" v-model="form.pricing" />
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                :class="form.pricing === option.value ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/5'">
                                <component :is="option.icon" class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ option.label }}</span>
                                <span class="block text-xs text-gray-500">{{ option.hint }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Fixed price -->
                <div v-if="form.pricing === 'fixed'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <VInput id="unit" label="Einheit" list="catalog-units" v-model="form.unit" :error="form.errors.unit" />
                    <VInput id="unit_price" label="Preis netto" suffix="€" v-model="form.unit_price" :error="form.errors.unit_price" mandatory />
                </div>

                <!-- Time rate: "50 € for the first 30 min, then 20 € per started 15 min" -->
                <div v-else class="space-y-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex flex-wrap items-end gap-3 text-sm">
                        <VInput id="unit_price" class="w-28" label="Grundpreis" suffix="€" v-model="form.unit_price" :error="form.errors.unit_price" />
                        <span class="pb-2.5 text-gray-500">für die ersten</span>
                        <VInput id="base_minutes" class="w-24" label="Minuten" suffix="Min" v-model="form.base_minutes" :error="form.errors.base_minutes" />
                    </div>
                    <div class="flex flex-wrap items-end gap-3 text-sm">
                        <span class="pb-2.5 text-gray-500">danach</span>
                        <VInput id="step_price" class="w-28" label="Preis" suffix="€" v-model="form.step_price" :error="form.errors.step_price" />
                        <span class="pb-2.5 text-gray-500">je angefangene</span>
                        <VInput id="step_minutes" class="w-24" label="Takt" suffix="Min" v-model="form.step_minutes" :error="form.errors.step_minutes" />
                    </div>

                    <!-- Example calculation -->
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
                        <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Beispiele</div>
                        <table class="mt-2 w-full text-sm">
                            <tr v-for="example in examples" :key="example.minutes">
                                <td class="py-0.5 pr-3 text-gray-700 dark:text-gray-300">{{ formatDuration(example.minutes) }}</td>
                                <td class="py-0.5 text-xs text-gray-500">{{ example.breakdown }}</td>
                                <td class="py-0.5 text-right font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(example.price) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <datalist id="catalog-units">
                <option v-for="unit in units" :key="unit" :value="unit" />
            </datalist>
        </section>

        <div class="flex items-center justify-end gap-3">
            <VButton :href="route('catalog.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">
                {{ item ? 'Speichern' : 'Leistung anlegen' }}
            </VButton>
        </div>
    </form>
</template>
