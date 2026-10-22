<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { GlobeAltIcon, CommandLineIcon, CalendarDaysIcon, ChatBubbleBottomCenterTextIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import VUrlInput from '@/components/VUrlInput.vue';
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
    prefill: { type: Object, default: null },   // from a project: customer, type, name, url, start
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
    customer_id: props.contract?.customer_id ?? props.prefill?.customer_id ?? props.customerId ?? '',
    project_id: props.contract?.project_id ?? props.prefill?.project_id ?? null,
    type: props.contract?.type ?? props.prefill?.type ?? 'website',
    name: props.contract?.name ?? props.prefill?.name ?? '',
    url: props.contract?.url ?? props.prefill?.url ?? '',
    monthly_price: props.contract ? String(Number(props.contract.monthly_price)) : defaultPrice(props.prefill?.type ?? 'website'),
    start_date: props.contract?.start_date ?? props.prefill?.start_date ?? today(),
    next_invoice_date: props.contract?.next_invoice_date ?? props.prefill?.start_date ?? today(),
    end_date: props.contract?.end_date ?? '',
    notes: props.contract?.notes ?? '',
    // Monitoring of the website lives in its own monitor
    monitor_enabled: props.contract ? !!props.contract.monitor?.enabled : true,
    monitor_auth: props.contract?.monitor?.auth ?? 'none',
    monitor_auth_user: props.contract?.monitor?.auth_user ?? '',
    monitor_auth_password: '',   // never sent to the browser; empty = keep the stored one
});

// From an offer, waiting for the go-live: dates and monitoring are set when it is started
const pending = computed(() => props.contract?.status === 'pending');

// Password protected website (htpasswd)
const protectedSite = ref(form.monitor_auth !== 'none');
watch(protectedSite, (value) => form.monitor_auth = value ? (form.monitor_auth === 'none' ? 'basic' : form.monitor_auth) : 'none');
const hasStoredPassword = computed(() => !!props.contract?.monitor?.has_password && props.contract?.monitor?.auth === 'basic');
const plainHttp = computed(() => String(form.url).startsWith('http://'));

// Switching the type switches the standard price, unless a custom price was entered
const parsePrice = (value) => Number(String(value ?? '').replace(',', '.'));

watch(() => form.type, (type, previous) => {
    const price = String(form.monthly_price).trim();
    if (price === '' || parsePrice(price) === parsePrice(defaultPrice(previous))) {
        form.monthly_price = defaultPrice(type);
    }
});

// Follow the start date until the user changes the next invoice date by hand
const nextInvoiceTouched = ref(!!props.contract);

watch(() => form.start_date, (start) => {
    if (!nextInvoiceTouched.value) form.next_invoice_date = nextAnniversary(start);
});

// Switched on/off with the toggle above the form: don't turn it back when saving
watch(() => props.contract?.monitor?.enabled, (enabled) => {
    if (enabled !== undefined) form.monitor_enabled = enabled;
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
                        <VUrlInput id="url" label="Website" placeholder="musterfirma.at" v-model="form.url" :error="form.errors.url" />
                    </div>

                    <p v-if="pending" class="rounded-xl border border-dashed border-gray-200 p-3.5 text-xs text-gray-500 dark:border-white/10">
                        Die Überwachung schaltest du beim Start des Vertrags ein, sobald die Seite live ist.
                    </p>
                    <label v-else class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3.5 dark:border-white/10">
                        <input type="checkbox" v-model="form.monitor_enabled" class="form-checkbox mt-0.5 rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5">
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Website überwachen</span>
                            <span class="block text-xs text-gray-500">Alle 5 Minuten prüfen, ob die URL erreichbar ist. Bei einem Ausfall bekommst du eine Mail.</span>
                        </span>
                    </label>

                    <!-- htpasswd / HTTP Basic Auth -->
                    <div v-if="form.monitor_enabled && form.url && !pending" class="rounded-xl border border-gray-200 p-3.5 dark:border-white/10">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" v-model="protectedSite" class="form-checkbox mt-0.5 rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5">
                            <span>
                                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Seite ist passwortgeschützt (htpasswd)</span>
                                <span class="block text-xs text-gray-500">Der Browser fragt beim Öffnen nach Benutzer und Passwort.</span>
                            </span>
                        </label>

                        <div v-if="protectedSite" class="mt-3 space-y-3 pl-7">
                            <label v-for="option in [
                                { value: 'basic', title: 'Zugangsdaten mitsenden', text: 'Der Monitor meldet sich an und prüft die echte Seite. Am zuverlässigsten.' },
                                { value: 'accept_401', title: 'Ohne Zugangsdaten prüfen', text: 'Die Passwortabfrage zählt als erreichbar. Nichts wird gespeichert, aber ein Fehler hinter dem Schutz fällt nicht auf.' },
                            ]" :key="option.value" class="flex cursor-pointer items-start gap-2.5">
                                <input type="radio" :value="option.value" v-model="form.monitor_auth" class="form-radio mt-0.5 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5">
                                <span>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">{{ option.title }}</span>
                                    <span class="block text-xs text-gray-500">{{ option.text }}</span>
                                </span>
                            </label>

                            <div v-if="form.monitor_auth === 'basic'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <VInput id="monitor_auth_user" label="Benutzer" autocomplete="off" v-model="form.monitor_auth_user" :error="form.errors.monitor_auth_user" />
                                <VInput id="monitor_auth_password" type="password" label="Passwort" autocomplete="new-password"
                                    :placeholder="hasStoredPassword ? '•••••••• (gespeichert)' : ''" v-model="form.monitor_auth_password" :error="form.errors.monitor_auth_password" />
                                <p class="text-xs text-gray-500 sm:col-span-2">
                                    Das Passwort wird verschlüsselt gespeichert und nie wieder angezeigt.<template v-if="hasStoredPassword"> Leer lassen, um das gespeicherte zu behalten.</template>
                                </p>
                                <p v-if="plainHttp" class="text-xs font-medium text-red-500 sm:col-span-2">Achtung: Über http:// werden die Zugangsdaten unverschlüsselt übertragen.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <VInput id="monthly_price" label="Preis pro Monat" suffix="€"
                            v-model="form.monthly_price" :error="form.errors.monthly_price" />
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
                    <p v-if="pending" class="rounded-xl bg-accent/[0.08] px-3.5 py-3 text-sm text-gray-700 ring-1 ring-accent/30 ring-inset dark:text-gray-300">
                        Beginnt mit dem Go-Live. Beim Start wird gleich die Rechnung für die ersten 12 Monate fällig.
                    </p>
                    <VInput v-if="!pending" id="start_date" type="date" label="Vertragsbeginn" v-model="form.start_date" :error="form.errors.start_date" />

                    <div v-if="!pending">
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
