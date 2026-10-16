<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArchiveBoxIcon, BanknotesIcon, DocumentArrowUpIcon, DocumentTextIcon, ArrowPathIcon, XMarkIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VButton from '@/components/VButton.vue';
import { formatMoney, today } from '@/utils/format';

const props = defineProps({
    item: { type: Object, default: null },
    receipt: { type: Object, default: null },
    categories: Array,
    gwgLimit: Number,
});

const toFormNumber = (value) => value === null || value === undefined || value === '' ? '' : String(Number(value)).replace('.', ',');
const parseNumber = (value) => Number(String(value ?? '').replace(',', '.')) || 0;

const form = useForm({
    name: props.item?.name ?? '',
    category: props.item?.category ?? 'computer',
    serial_number: props.item?.serial_number ?? '',
    supplier: props.item?.supplier ?? '',
    purchased_at: props.item?.purchased_at?.substring(0, 10) ?? today(),
    price: toFormNumber(props.item?.price),
    tax_rate: toFormNumber(props.item?.tax_rate ?? 20),
    useful_life_years: props.item?.useful_life_years ? String(props.item.useful_life_years) : '',
    status: props.item?.status ?? 'active',
    retired_at: props.item?.retired_at?.substring(0, 10) ?? '',
    sale_price: toFormNumber(props.item?.sale_price),
    notes: props.item?.notes ?? '',
    receipt: null,
    remove_receipt: false,
});

// Price input: net or gross, the other one is calculated
const priceMode = ref('net');
const grossInput = ref(toFormNumber(props.item?.gross_price));
const net = computed(() => parseNumber(form.price));
const gross = computed(() => Math.round(net.value * (1 + parseNumber(form.tax_rate) / 100) * 100) / 100);
const updateFromGross = () => {
    const value = parseNumber(grossInput.value) / (1 + parseNumber(form.tax_rate) / 100);
    form.price = grossInput.value === '' ? '' : toFormNumber(Math.round(value * 100) / 100);
};
watch([grossInput, () => form.tax_rate], () => priceMode.value === 'gross' && updateFromGross());

const isGwg = computed(() => net.value > 0 && net.value <= props.gwgLimit);
const category = computed(() => props.categories.find(option => option.value === form.category));
const years = computed(() => parseInt(form.useful_life_years) || category.value?.years || 1);

// Preview of the depreciation, same rules as the server
const preview = computed(() => {
    if (!net.value || !form.purchased_at) return [];

    const start = new Date(form.purchased_at);
    if (isGwg.value) return [{ year: start.getFullYear(), depreciation: net.value, value: 0 }];

    const yearly = net.value / years.value;
    let amount = start.getMonth() < 6 ? yearly : yearly / 2;
    let value = net.value;
    const rows = [];
    for (let year = start.getFullYear(); value > 0.005 && rows.length < 60; year++) {
        const depreciation = Math.round(Math.min(amount, value) * 100) / 100;
        value = Math.round((value - depreciation) * 100) / 100;
        rows.push({ year, depreciation, value });
        amount = yearly;
    }
    return rows;
});
const secondHalf = computed(() => form.purchased_at && new Date(form.purchased_at).getMonth() >= 6);

const statusOptions = [
    { value: 'active', label: 'In Verwendung' },
    { value: 'sold', label: 'Verkauft' },
    { value: 'disposed', label: 'Ausgeschieden (kaputt, entsorgt, verschenkt)' },
];

// Receipt
const fileInput = ref(null);
const dragging = ref(false);
const selectReceipt = (file) => {
    if (!file) return;
    form.receipt = file;
    form.remove_receipt = false;
    form.clearErrors('receipt');
};
const clearReceipt = () => {
    form.receipt = null;
    if (fileInput.value) fileInput.value.value = null;
};
const hasStoredReceipt = computed(() => props.receipt && !form.remove_receipt && !form.receipt);
const formatSize = (bytes) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

const submit = () => {
    form.transform(data => ({
        ...data,
        remove_receipt: data.remove_receipt ? 1 : 0,
        ...(props.item ? { _method: 'put' } : {}),
    })).post(props.item ? route('inventory.update', props.item.id) : route('inventory.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => clearReceipt(),
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="mx-auto max-w-5xl">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Item -->
                <section class="card p-6">
                    <h2 class="card-title"><ArchiveBoxIcon class="h-5 w-5 text-primary-500" />Gegenstand</h2>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <VInput id="name" label="Bezeichnung" placeholder="z. B. MacBook Pro 14″ M4" v-model="form.name" :error="form.errors.name" class="sm:col-span-2" mandatory />
                        <VSelect id="category" label="Kategorie" v-model="form.category" :options="categories" :error="form.errors.category" />
                        <VInput id="serial_number" label="Seriennummer (optional)" v-model="form.serial_number" :error="form.errors.serial_number" />
                        <VInput id="supplier" label="Gekauft bei (optional)" placeholder="z. B. Amazon, MediaMarkt" v-model="form.supplier" :error="form.errors.supplier" />
                        <VInput id="purchased_at" type="date" label="Kaufdatum" v-model="form.purchased_at" :error="form.errors.purchased_at" mandatory />
                    </div>
                </section>

                <!-- Value -->
                <section class="card p-6">
                    <h2 class="card-title"><BanknotesIcon class="h-5 w-5 text-primary-500" />Wert</h2>

                    <div class="mt-5 inline-flex rounded-xl bg-gray-100 p-1 text-sm dark:bg-white/5">
                        <button v-for="mode in [{ value: 'net', label: 'Netto eingeben' }, { value: 'gross', label: 'Brutto eingeben' }]" :key="mode.value" type="button"
                            class="cursor-pointer rounded-lg px-3 py-1.5 font-medium transition"
                            :class="priceMode === mode.value ? 'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                            @click="priceMode = mode.value; grossInput = toFormNumber(gross || '')">
                            {{ mode.label }}
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <VInput v-if="priceMode === 'net'" id="price" label="Kaufpreis netto (€)" inputmode="decimal" placeholder="0,00" v-model="form.price" :error="form.errors.price" mandatory />
                        <VInput v-else id="gross" label="Kaufpreis brutto (€)" inputmode="decimal" placeholder="0,00" v-model="grossInput" :error="form.errors.price" mandatory />
                        <VInput id="tax_rate" label="USt. (%)" inputmode="decimal" v-model="form.tax_rate" :error="form.errors.tax_rate" />
                        <div class="rounded-xl bg-gray-50 px-4 py-2.5 text-sm dark:bg-white/[0.03]">
                            <div class="text-xs text-gray-500">{{ priceMode === 'net' ? 'Brutto' : 'Netto' }}</div>
                            <div class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">{{ formatMoney(priceMode === 'net' ? gross : net) }}</div>
                        </div>
                    </div>

                    <!-- Depreciation -->
                    <div v-if="net > 0" class="mt-5 rounded-2xl p-4 ring-1 ring-inset"
                        :class="isGwg ? 'bg-accent/[0.06] ring-accent/30' : 'bg-primary-500/[0.05] ring-primary-500/25'">
                        <div v-if="isGwg" class="flex gap-3">
                            <SparklesIcon class="h-5 w-5 shrink-0 text-accent" />
                            <div class="text-sm">
                                <div class="font-semibold text-gray-900 dark:text-white">Geringwertiges Wirtschaftsgut (GWG)</div>
                                <p class="mt-0.5 text-gray-600 dark:text-gray-400">Bis {{ formatMoney(gwgLimit) }} netto kannst du den vollen Betrag im Kaufjahr {{ preview[0]?.year }} absetzen.</p>
                            </div>
                        </div>
                        <template v-else>
                            <div class="flex flex-wrap items-end justify-between gap-3">
                                <div class="text-sm">
                                    <div class="font-semibold text-gray-900 dark:text-white">Abschreibung über {{ years }} Jahre</div>
                                    <p class="mt-0.5 text-gray-600 dark:text-gray-400">
                                        Über {{ formatMoney(gwgLimit) }} netto wird linear abgeschrieben.
                                        <template v-if="secondHalf">Gekauft im 2. Halbjahr: im ersten Jahr nur die halbe AfA.</template>
                                    </p>
                                </div>
                                <VInput id="useful_life_years" type="number" min="1" max="50" label="Nutzungsdauer (Jahre)" :placeholder="String(category?.years ?? '')" v-model="form.useful_life_years" :error="form.errors.useful_life_years" class="w-44" />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <div v-for="row in preview" :key="row.year" class="rounded-lg bg-white/70 px-2.5 py-1.5 text-xs ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                    <span class="font-mono text-gray-500">{{ row.year }}</span>
                                    <span class="ml-1.5 font-medium text-gray-900 dark:text-gray-100">− {{ formatMoney(row.depreciation) }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>

                <!-- Status -->
                <section class="card p-6">
                    <h2 class="card-title"><ArrowPathIcon class="h-5 w-5 text-primary-500" />Status</h2>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <VSelect id="status" label="Status" v-model="form.status" :options="statusOptions" :error="form.errors.status" :class="form.status === 'active' ? 'sm:col-span-3' : ''" />
                        <template v-if="form.status !== 'active'">
                            <VInput id="retired_at" type="date" :label="form.status === 'sold' ? 'Verkauft am' : 'Ausgeschieden am'" v-model="form.retired_at" :error="form.errors.retired_at" mandatory />
                            <VInput v-if="form.status === 'sold'" id="sale_price" label="Verkaufserlös netto (€)" inputmode="decimal" placeholder="0,00" v-model="form.sale_price" :error="form.errors.sale_price" />
                        </template>
                    </div>

                    <div class="mt-4">
                        <label for="notes" class="mb-1 block text-sm font-medium">Notizen (optional)</label>
                        <textarea id="notes" v-model="form.notes" rows="3" class="form-textarea w-full" placeholder="z. B. Garantie bis 2028, Zubehör, Standort"></textarea>
                        <div v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</div>
                    </div>
                </section>
            </div>

            <!-- Receipt -->
            <div class="space-y-6">
                <section class="card p-6 lg:sticky lg:top-24">
                    <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Beleg</h2>
                    <p class="mt-1 text-sm text-gray-500">Rechnung als PDF oder Foto. Wird privat gespeichert.</p>

                    <div v-if="hasStoredReceipt" class="mt-4 flex items-center gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                        <DocumentTextIcon class="h-8 w-8 shrink-0 text-primary-500" />
                        <a :href="route('inventory.receipt', item.id)" target="_blank" class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-300">{{ receipt.name }}</div>
                            <div class="text-xs text-gray-500">{{ formatSize(receipt.size) }} · öffnen</div>
                        </a>
                        <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-400 hover:bg-red-500/10 hover:text-red-500" title="Beleg entfernen" @click="form.remove_receipt = true">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <label v-else
                        class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-8 text-center transition"
                        :class="dragging ? 'border-primary-500 bg-primary-500/[0.06]' : form.errors.receipt ? 'border-red-400/70' : 'border-gray-200 hover:border-primary-400 dark:border-white/10 dark:hover:border-primary-500/60'"
                        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; selectReceipt($event.dataTransfer.files[0])">
                        <input ref="fileInput" type="file" accept=".pdf,image/png,image/jpeg,image/webp" class="sr-only" @change="selectReceipt($event.target.files[0])" />
                        <DocumentArrowUpIcon class="h-8 w-8" :class="form.receipt ? 'text-primary-500' : 'text-gray-400'" />
                        <template v-if="form.receipt">
                            <span class="mt-2 max-w-full truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ form.receipt.name }}</span>
                            <span class="text-xs text-gray-500">{{ formatSize(form.receipt.size) }} · zum Ändern klicken</span>
                        </template>
                        <template v-else>
                            <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Beleg hierher ziehen</span>
                            <span class="text-xs text-gray-500">oder klicken · PDF, JPG, PNG · max. 10 MB</span>
                        </template>
                    </label>
                    <p v-if="form.errors.receipt" class="mt-1 text-xs text-red-500">{{ form.errors.receipt }}</p>
                    <button v-if="form.remove_receipt" type="button" class="mt-2 cursor-pointer text-xs text-gray-500 underline hover:text-primary-500" @click="form.remove_receipt = false">
                        Entfernen rückgängig
                    </button>
                </section>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <VButton :href="route('inventory.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">
                {{ item ? 'Speichern' : 'Ins Inventar aufnehmen' }}
            </VButton>
        </div>
    </form>
</template>
