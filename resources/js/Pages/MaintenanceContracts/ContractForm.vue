<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { GlobeAltIcon, CommandLineIcon, CalendarDaysIcon, ChatBubbleBottomCenterTextIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VTextarea from '@/components/VTextarea.vue';
import VButton from '@/components/VButton.vue';
import { formatMoney, toDate, toIsoDate, today } from '@/utils/format';

const props = defineProps({
    contract: { type: Object, default: null },
    customers: { type: Array, required: true },
    types: { type: Array, required: true },
    customerId: { type: Number, default: null },
});

const typeIcons = { website: GlobeAltIcon, webapp: CommandLineIcon };

const defaultPrice = (type) => String(props.types.find(t => t.value === type)?.price ?? '');

// First anniversary of the start date that is today or later (for contracts entered after the fact)
const nextAnniversary = (start) => {
    if (!start) return '';
    const date = toDate(start);
    const now = toDate(today());
    while (date < now) date.setFullYear(date.getFullYear() + 1);
    return toIsoDate(date);
};

const form = useForm({
    customer_id: props.contract?.customer_id ?? props.customerId ?? '',
    type: props.contract?.type ?? 'website',
    name: props.contract?.name ?? '',
    url: props.contract?.url ?? '',
    monthly_price: props.contract ? String(Number(props.contract.monthly_price)) : defaultPrice('website'),
    start_date: props.contract?.start_date ?? today(),
    next_invoice_date: props.contract?.next_invoice_date ?? today(),
    end_date: props.contract?.end_date ?? '',
    notes: props.contract?.notes ?? '',
});

// Follow the type's standard price / the start date until the user changes the field by hand
const priceTouched = ref(!!props.contract);
const nextInvoiceTouched = ref(!!props.contract);

watch(() => form.type, (type) => {
    if (!priceTouched.value) form.monthly_price = defaultPrice(type);
});

watch(() => form.start_date, (start) => {
    if (!nextInvoiceTouched.value) form.next_invoice_date = nextAnniversary(start);
});

// Marking an invoice as sent moves the due date on the server; keep the form in sync
watch(() => props.contract?.next_invoice_date, (date) => {
    if (date) form.next_invoice_date = date;
});

const annualPrice = computed(() => Number(String(form.monthly_price).replace(',', '.')) * 12 || 0);

const submit = () => {
    form.transform(data => ({ ...data, monthly_price: String(data.monthly_price).replace(',', '.') }));

    if (props.contract)
        form.put(route('contracts.update', props.contract.id));
    else
        form.post(route('contracts.store'));
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

            <!-- Vertrag -->
            <section class="card p-6">
                <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Vertrag</h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <VSelect id="customer_id" label="Kunde" placeholder="Kunde wählen …" :options="customers" v-model="form.customer_id" :error="form.errors.customer_id" />
                        <p class="mt-1 text-xs text-gray-500">
                            Kunde fehlt?
                            <Link :href="route('customers.create')" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">Neuen Kunden anlegen</Link>
                        </p>
                    </div>

                    <!-- Type -->
                    <div>
                        <span class="mb-1 block text-sm font-medium">Art</span>
                        <div class="grid grid-cols-2 gap-3">
                            <label v-for="type in types" :key="type.value"
                                class="relative flex cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition"
                                :class="form.type === type.value
                                    ? 'border-primary-500 bg-primary-500/[0.08] shadow-[0_10px_30px_-12px_rgba(31,173,174,0.6)]'
                                    : 'border-gray-200 hover:border-gray-300 dark:border-white/10 dark:hover:border-white/20'">
                                <input type="radio" class="sr-only" :value="type.value" v-model="form.type" />
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                    :class="form.type === type.value ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/5'">
                                    <component :is="typeIcons[type.value]" class="h-5 w-5" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ type.label }}</span>
                                    <span class="block text-xs text-gray-500">{{ formatMoney(type.price) }} / Monat</span>
                                </span>
                            </label>
                        </div>
                        <div v-if="form.errors.type" class="text-xs mt-1 text-red-500">{{ form.errors.type }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <VInput id="name" label="Projekt" placeholder="z. B. Musterfirma Website" v-model="form.name" :error="form.errors.name" />
                        <VInput id="url" type="url" label="URL" placeholder="https://musterfirma.at" v-model="form.url" :error="form.errors.url" />
                    </div>

                    <div>
                        <VInput id="monthly_price" label="Preis pro Monat" suffix="€"
                            v-model="form.monthly_price" @input="priceTouched = true" :error="form.errors.monthly_price" />
                        <p class="mt-1 text-xs text-gray-500">
                            Jährlich verrechnet: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatMoney(annualPrice) }}</span>
                        </p>
                    </div>
                </div>
            </section>

            <!-- Laufzeit -->
            <section class="card p-6">
                <h2 class="card-title"><CalendarDaysIcon class="h-5 w-5 text-primary-500" />Laufzeit &amp; Verrechnung</h2>

                <div class="mt-5 space-y-4">
                    <VInput id="start_date" type="date" label="Vertragsbeginn" v-model="form.start_date" :error="form.errors.start_date" />

                    <div>
                        <VInput id="next_invoice_date" type="date" label="Nächste Rechnung fällig am"
                            v-model="form.next_invoice_date" @input="nextInvoiceTouched = true" :error="form.errors.next_invoice_date" />
                        <p class="mt-1 text-xs text-gray-500">
                            Verrechnet wird jährlich im Voraus ab Vertragsbeginn.
                            <template v-if="!contract">Bei älteren Verträgen wird automatisch der nächste Jahrestag vorgeschlagen.</template>
                        </p>
                    </div>

                    <div>
                        <VInput id="end_date" type="date" label="Gekündigt zum (optional)" v-model="form.end_date" :error="form.errors.end_date" />
                        <p class="mt-1 text-xs text-gray-500">Nach diesem Datum werden keine Rechnungen mehr fällig.</p>
                    </div>
                </div>
            </section>

            <!-- Bemerkung -->
            <section class="card p-6 xl:col-span-2">
                <h2 class="card-title"><ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-primary-500" />Bemerkung</h2>
                <div class="mt-5">
                    <VTextarea id="notes" :rows="3" placeholder="z. B. Leistungsumfang, Hosting, Besonderheiten …" v-model="form.notes" />
                    <div v-if="form.errors.notes" class="text-xs mt-1 text-red-500">{{ form.errors.notes }}</div>
                </div>
            </section>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <VButton :href="route('contracts.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">
                {{ contract ? 'Speichern' : 'Vertrag anlegen' }}
            </VButton>
        </div>
    </form>
</template>
