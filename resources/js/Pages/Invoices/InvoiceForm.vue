<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { DocumentTextIcon, ListBulletIcon, ChatBubbleBottomCenterTextIcon, PlusIcon, TrashIcon, ExclamationTriangleIcon, WrenchScrewdriverIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';
import { formatDate, formatMoney, toDate, toIsoDate } from '@/utils/format';
import { breakdown, formatDuration, minutesForPrice, parseDuration, priceForMinutes, rateLabel } from '@/utils/timePricing';

const props = defineProps({
    invoice: { type: Object, required: true },
    customers: { type: Array, required: true },
    paymentTermsDays: { type: Number, default: 14 },
    profileComplete: { type: Boolean, default: true },
    contract: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    catalog: { type: Array, default: () => [] },
});

const units = ['Stück', 'Stunde', 'Pauschale', 'Monat', 'Jahr', 'Tag'];

const toFormNumber = (value) => value === '' || value === null || value === undefined ? '' : String(Number(value)).replace('.', ',');
const parseNumber = (value) => Number(String(value ?? '').replace(',', '.')) || 0;

const form = useForm({
    customer_id: props.invoice.customer_id ?? '',
    maintenance_contract_id: props.invoice.maintenance_contract_id ?? null,
    number: props.invoice.number ?? '',
    invoice_date: props.invoice.invoice_date ?? '',
    due_date: props.invoice.due_date ?? '',
    tax_rate: toFormNumber(props.invoice.tax_rate),
    paid_amount: toFormNumber(props.invoice.paid_amount),
    notes: props.invoice.notes ?? '',
    paid_in_cash: false,
    items: props.invoice.items.map(item => ({
        description: item.description ?? '',
        quantity: toFormNumber(item.quantity),
        unit: item.unit ?? '',
        unit_price: toFormNumber(item.unit_price),
    })),
});

// Due date follows the invoice date + payment terms until it is changed by hand
const dueDateTouched = ref(props.isEdit);
watch(() => form.invoice_date, (date) => {
    if (dueDateTouched.value || !date) return;
    const due = toDate(date);
    due.setDate(due.getDate() + props.paymentTermsDays);
    form.due_date = toIsoDate(due);
});

const addItem = () => form.items.push({ description: '', quantity: '1', unit: 'Stück', unit_price: '' });

// Bullet points in item descriptions ("- " at the start of a line, rendered as • in the PDF)
const bulletPattern = /^\s*[-•*]\s/;

// Grow textareas with their content (incl. wrapped lines), so no scrollbar appears
const autosize = (el) => {
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight + 2}px`;
};
const vAutosize = {
    mounted: (el) => nextTick(() => autosize(el)),
    updated: (el) => autosize(el),
};

const setCursor = (textarea, position) => nextTick(() => {
    textarea.focus();
    textarea.setSelectionRange(position, position);
});

// Enter inside a bullet line starts the next bullet; Enter on an empty bullet ends the list
const continueBullet = (event, item) => {
    if (event.shiftKey || event.isComposing) return;

    const textarea = event.target;
    const text = item.description;
    const cursor = textarea.selectionStart;
    const lineStart = text.lastIndexOf('\n', cursor - 1) + 1;
    const line = text.slice(lineStart, cursor);

    if (!bulletPattern.test(line)) return;
    event.preventDefault();

    if (line.replace(bulletPattern, '').trim() === '') {
        item.description = text.slice(0, lineStart) + text.slice(cursor);
        setCursor(textarea, lineStart);
        return;
    }

    item.description = text.slice(0, cursor) + '\n- ' + text.slice(cursor);
    setCursor(textarea, cursor + 3);
};

const addBullet = (event, item) => {
    const textarea = event.currentTarget.closest('div').parentElement.querySelector('textarea');
    const text = item.description.replace(/\s+$/, '');

    item.description = text ? `${text}\n- ` : '- ';
    setCursor(textarea, item.description.length);
};
const removeItem = (index) => form.items.splice(index, 1);

// Insert a catalog item; an untouched empty first row is replaced instead of kept
const catalogSelection = ref('');
const insertFromCatalog = () => {
    const entry = props.catalog.find(item => String(item.id) === String(catalogSelection.value));
    catalogSelection.value = '';
    if (!entry) return;

    // Time rates need the duration first
    if (entry.pricing === 'time') {
        timeEntry.value = entry;
        durationInput.value = '';
        amountInput.value = '';
        return;
    }

    addRow({
        description: [entry.name, entry.description].filter(Boolean).join('\n'),
        quantity: '1',
        unit: entry.unit ?? '',
        unit_price: toFormNumber(entry.unit_price),
    });
};

const addRow = (row) => {
    const onlyEmptyRow = form.items.length === 1 && !form.items[0].description && !form.items[0].unit_price;
    if (onlyEmptyRow) form.items.splice(0, 1, row);
    else form.items.push(row);
};

// Duration dialog for time rates: enter the duration, or the amount and get the duration
const timeEntry = ref(null);
const timeMode = ref('duration');   // duration | amount
const durationInput = ref('');
const amountInput = ref('');

const amountResult = computed(() => timeEntry.value ? minutesForPrice(timeEntry.value, parseNumber(amountInput.value)) : null);

const durationMinutes = computed(() => timeMode.value === 'duration'
    ? parseDuration(durationInput.value)
    : (amountResult.value?.exact ? amountResult.value.minutes : null));

const durationPrice = computed(() => timeEntry.value && durationMinutes.value !== null ? priceForMinutes(timeEntry.value, durationMinutes.value) : null);

const addDuration = (minutes) => {
    durationInput.value = String((durationMinutes.value ?? 0) + minutes);
};

const insertTimeEntry = () => {
    const entry = timeEntry.value;
    const minutes = durationMinutes.value;
    if (!entry || minutes === null || minutes <= 0) return;

    addRow({
        // Second line is printed small on the invoice and explains the price
        description: [entry.name, `Dauer ${formatDuration(minutes)} · ${breakdown(entry, minutes)}`, entry.description].filter(Boolean).join('\n'),
        quantity: '1',
        unit: 'Pauschale',
        unit_price: toFormNumber(priceForMinutes(entry, minutes)),
    });

    timeEntry.value = null;
};

const lineTotal = (item) => Math.round(parseNumber(item.quantity) * parseNumber(item.unit_price) * 100) / 100;
const subtotal = computed(() => form.items.reduce((sum, item) => sum + lineTotal(item), 0));
const taxAmount = computed(() => Math.round(subtotal.value * parseNumber(form.tax_rate)) / 100);
const total = computed(() => subtotal.value + taxAmount.value);
const amountDue = computed(() => total.value - parseNumber(form.paid_amount));

const recipient = computed(() => props.customers.find(c => String(c.value) === String(form.customer_id))?.recipient);

const itemError = (index, field) => form.errors[`items.${index}.${field}`];

// New invoice from an existing PDF (written elsewhere): the PDF is kept instead of generating one
const original = ref(null);
const originalInput = ref(null);
const selectOriginal = (file) => {
    if (!file) return;
    original.value = file;
    form.clearErrors('original');
};
const clearOriginal = () => {
    original.value = null;
    if (originalInput.value) originalInput.value.value = null;
};

const submit = () => {
    if (props.isEdit)
        form.put(route('invoices.update', props.invoice.id), { preserveScroll: true });
    else if (original.value)
        form.transform(data => ({ ...data, original: original.value })).post(route('invoices.store'), { forceFormData: true });
    else
        form.post(route('invoices.store'));
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">

        <!-- Hints -->
        <div v-if="!profileComplete && !readonly" class="flex items-start gap-3 rounded-xl bg-accent/10 px-4 py-3 text-sm text-amber-800 ring-1 ring-accent/40 dark:text-accent">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <span>
                Deine Rechnungsdaten sind unvollständig (Name, Adresse oder IBAN fehlen).
                <Link :href="route('profile.show')" class="font-semibold underline">Im Profil ergänzen</Link>
            </span>
        </div>

        <div v-if="contract && !isEdit" class="flex items-start gap-3 rounded-xl bg-primary-500/10 px-4 py-3 text-sm text-primary-800 ring-1 ring-primary-500/30 dark:text-primary-200">
            <WrenchScrewdriverIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <span>
                Rechnung für den Wartungsvertrag <strong>{{ contract.name }}</strong>.
                Beim Speichern wird der Vertrag automatisch als verrechnet markiert und springt auf die nächste Fälligkeit.
            </span>
        </div>

        <fieldset :disabled="readonly" class="grid grid-cols-1 gap-6 xl:grid-cols-3" :class="readonly && 'opacity-80'">

            <!-- Kopfdaten -->
            <section class="card p-6 xl:col-span-2">
                <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Rechnung</h2>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <VSelect id="customer_id" label="Kunde" placeholder="Kunde wählen …" :options="customers" v-model="form.customer_id" :error="form.errors.customer_id" />
                        <p v-if="recipient" class="mt-2 text-xs text-gray-500">
                            {{ recipient.name }} · {{ [recipient.street, [recipient.zip, recipient.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || 'keine Adresse hinterlegt' }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500">
                            Kunde fehlt? <Link :href="route('customers.create')" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">Neuen Kunden anlegen</Link>
                        </p>
                    </div>

                    <VInput id="number" class="sm:col-span-2" label="Rechnungsnummer" v-model="form.number" :error="form.errors.number" />
                    <VInput id="invoice_date" class="sm:col-span-2" type="date" label="Rechnungsdatum" v-model="form.invoice_date" :error="form.errors.invoice_date" />
                    <VInput id="due_date" class="sm:col-span-2" type="date" label="Fällig am" v-model="form.due_date" @input="dueDateTouched = true" :error="form.errors.due_date" />
                    <VInput id="tax_rate" class="sm:col-span-2" label="USt." suffix="%" v-model="form.tax_rate" :error="form.errors.tax_rate" />
                    <VInput id="paid_amount" class="sm:col-span-2" label="Bereits bezahlt" suffix="€" v-model="form.paid_amount" :error="form.errors.paid_amount" />
                </div>
            </section>

            <!-- Summen -->
            <section class="card relative overflow-hidden p-6">
                <div class="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full bg-primary-500/20 blur-[70px]" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="card-title">Summe</h2>
                    <dl class="mt-5 space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Zwischensumme netto</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">USt. {{ form.tax_rate || 0 }} %</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(taxAmount) }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2.5 dark:border-white/10"><dt class="font-semibold text-gray-900 dark:text-gray-100">Gesamt</dt><dd class="font-semibold text-gray-900 dark:text-gray-100">{{ formatMoney(total) }}</dd></div>
                        <div v-if="parseNumber(form.paid_amount)" class="flex justify-between"><dt class="text-gray-500">Bereits bezahlt</dt><dd class="text-gray-700 dark:text-gray-300">− {{ formatMoney(parseNumber(form.paid_amount)) }}</dd></div>
                    </dl>
                    <div class="mt-5 rounded-xl bg-primary-500/10 p-4 ring-1 ring-primary-500/25">
                        <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">Zu zahlen</div>
                        <div class="mt-1 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ formatMoney(amountDue) }}</div>
                        <div v-if="form.due_date" class="mt-1 text-xs text-gray-500">bis {{ formatDate(form.due_date) }}</div>
                    </div>
                </div>
            </section>

            <!-- Positionen -->
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><ListBulletIcon class="h-5 w-5 text-primary-500" />Positionen</h2>
                <div v-if="form.errors.items" class="mt-2 text-xs text-red-500">{{ form.errors.items }}</div>

                <div class="mt-5 space-y-3">
                    <!-- Column labels (desktop) -->
                    <div class="hidden grid-cols-12 gap-3 px-1 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 lg:grid">
                        <div class="col-span-5">Beschreibung</div>
                        <div class="col-span-1">Menge</div>
                        <div class="col-span-2">Einheit</div>
                        <div class="col-span-2">Preis netto</div>
                        <div class="col-span-2 text-right">Betrag</div>
                    </div>

                    <div v-for="(item, index) in form.items" :key="index"
                        class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 p-3 lg:grid-cols-12 lg:items-start lg:border-0 lg:p-1 dark:border-white/10">
                        <div class="col-span-2 lg:col-span-5">
                            <textarea v-model="item.description" v-autosize rows="2" class="form-textarea w-full resize-none overflow-hidden"
                                placeholder="z. B. Website Relaunch&#10;- Responsive Design&#10;- Kontaktformular"
                                @keydown.enter="continueBullet($event, item)"></textarea>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-[11px] text-gray-400">1. Zeile = Titel (fett auf der Rechnung) · weitere Zeilen = Details darunter · „- “ am Zeilenanfang = Aufzählungspunkt</span>
                                <button v-if="!readonly" type="button" class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-200 transition hover:text-primary-600 hover:ring-primary-400 dark:ring-white/10 dark:hover:text-primary-300"
                                    @click="addBullet($event, item)">• Aufzählung</button>
                            </div>
                            <div v-if="itemError(index, 'description')" class="text-xs mt-1 text-red-500">{{ itemError(index, 'description') }}</div>
                        </div>
                        <VInput class="lg:col-span-1" :id="`qty_${index}`" placeholder="Menge" v-model="item.quantity" :error="itemError(index, 'quantity')" />
                        <VInput class="lg:col-span-2" :id="`unit_${index}`" placeholder="Einheit" list="invoice-units" v-model="item.unit" :error="itemError(index, 'unit')" />
                        <VInput class="lg:col-span-2" :id="`price_${index}`" placeholder="Preis" suffix="€" v-model="item.unit_price" :error="itemError(index, 'unit_price')" />
                        <div class="flex items-center justify-between gap-2 lg:col-span-2 lg:justify-end lg:pt-2">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(lineTotal(item)) }}</span>
                            <button type="button" class="rounded-lg p-2 text-gray-400 transition hover:bg-red-500/10 hover:text-red-500 disabled:opacity-30" :disabled="form.items.length === 1" title="Position entfernen" @click="removeItem(index)">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!readonly" class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-primary-500 hover:text-primary-600 dark:border-white/15 dark:text-gray-400 dark:hover:text-primary-300" @click="addItem">
                    <PlusIcon class="h-4 w-4" />Position hinzufügen
                </button>

                <select v-if="catalog.length" v-model="catalogSelection" class="form-select w-auto text-sm" @change="insertFromCatalog">
                    <option value="" disabled>Aus Katalog einfügen …</option>
                    <option v-for="entry in catalog" :key="entry.id" :value="entry.id">
                        {{ entry.name }} · {{ entry.pricing === 'time' ? rateLabel(entry) : formatMoney(entry.unit_price) + (entry.unit ? ' / ' + entry.unit : '') }}
                    </option>
                </select>
                <Link v-else :href="route('catalog.create')" class="text-sm text-gray-500 hover:text-primary-500">Leistungskatalog anlegen</Link>
                </div>
                <datalist id="invoice-units">
                    <option v-for="unit in units" :key="unit" :value="unit" />
                </datalist>
            </section>

            <!-- Paid in cash on the spot (only when creating) -->
            <label v-if="!isEdit" class="card flex cursor-pointer items-start gap-3 p-5 xl:col-span-3"
                :class="form.paid_in_cash && 'ring-2 ring-primary-500/60'">
                <input type="checkbox" v-model="form.paid_in_cash" class="form-checkbox mt-0.5 h-5 w-5 rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5">
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-gray-100">Bar bezahlt</span>
                    <span class="block text-sm text-gray-500">
                        Der Kunde hat den Betrag bar bezahlt. Die Zahlung wird mit dem Rechnungsdatum erfasst, und die Rechnung gilt sofort als bezahlt.
                        Auf der PDF steht dann „Bar erhalten am …“ statt QR-Code, sie dient so gleichzeitig als Quittung.
                    </span>
                </span>
            </label>

            <!-- Anmerkung -->
            <!-- Existing invoice as PDF (create only; on the edit page there is its own card) -->
            <section v-if="!isEdit" class="card p-6 xl:col-span-3">
                <h2 class="card-title"><DocumentArrowUpIcon class="h-5 w-5 text-primary-500" />Bestehende Rechnung (optional)</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Schon woanders geschrieben? Häng das PDF an – meSync verwendet dann dein Original statt ein eigenes PDF zu erzeugen.
                    Trag Nummer, Datum und Beträge trotzdem ein, damit Zahlungen, Export und Finanzen stimmen.
                </p>
                <div v-if="original" class="mt-4 flex items-center gap-3 rounded-xl bg-primary-500/[0.06] p-3 ring-1 ring-primary-500/30">
                    <DocumentArrowUpIcon class="h-6 w-6 shrink-0 text-primary-500" />
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ original.name }}</span>
                    <button type="button" class="cursor-pointer text-xs text-gray-500 underline hover:text-red-500" @click="clearOriginal">Entfernen</button>
                </div>
                <label v-else class="mt-4 flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-gray-200 p-4 text-sm text-gray-600 transition hover:border-primary-400 dark:border-white/10 dark:text-gray-400"
                    @dragover.prevent @drop.prevent="selectOriginal($event.dataTransfer.files[0])">
                    <input ref="originalInput" type="file" accept="application/pdf,.pdf" class="sr-only" @change="selectOriginal($event.target.files[0])" />
                    <DocumentArrowUpIcon class="h-6 w-6 shrink-0 text-gray-400" />
                    PDF hierher ziehen oder klicken · privat gespeichert, max. 10 MB
                </label>
                <div v-if="form.errors.original" class="mt-1 text-xs text-red-500">{{ form.errors.original }}</div>
            </section>

            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-primary-500" />Anmerkung</h2>
                <p class="mt-1 text-sm text-gray-500">Wird unter den Summen auf der Rechnung gedruckt, z. B. Zahlungshinweis oder Steuerbefreiung.</p>
                <textarea v-model="form.notes" rows="3" class="form-textarea mt-4 w-full" placeholder="Vielen Dank für Ihren Auftrag!"></textarea>
                <div v-if="form.errors.notes" class="text-xs mt-1 text-red-500">{{ form.errors.notes }}</div>
            </section>
        </fieldset>

        <!-- Actions -->
        <div v-if="!readonly" class="flex flex-wrap items-center justify-end gap-3">
            <VButton :href="route('invoices.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">
                {{ isEdit ? 'Speichern' : 'Rechnung erstellen' }}
            </VButton>
        </div>

        <!-- Duration for time-based catalog items -->
        <VModal :show="timeEntry !== null" max-width="md" @close="timeEntry = null">
            <template #title>{{ timeEntry?.name }}</template>

            <template #content>
                <p>{{ timeEntry && rateLabel(timeEntry) }}</p>

                <!-- Mode switch -->
                <div class="mt-4 inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-white/10">
                    <button v-for="mode in [['duration', 'Dauer eingeben'], ['amount', 'Betrag eingeben']]" :key="mode[0]" type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-medium transition"
                        :class="timeMode === mode[0] ? 'bg-primary-500 text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        @click="timeMode = mode[0]">{{ mode[1] }}</button>
                </div>

                <div v-if="timeMode === 'duration'" class="mt-4">
                    <VInput id="duration" label="Dauer" placeholder="z. B. 80 oder 1:20" v-model="durationInput" autofocus @keydown.enter.prevent="insertTimeEntry" />
                    <p class="mt-1 text-xs text-gray-500">In Minuten (80) oder Stunden:Minuten (1:20).</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-for="minutes in [15, 30, 60]" :key="minutes" type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-200 transition hover:text-primary-600 hover:ring-primary-400 dark:text-gray-400 dark:ring-white/10"
                            @click="addDuration(minutes)">+ {{ minutes }} Min.</button>
                    </div>
                </div>

                <div v-else class="mt-4">
                    <VInput id="amount" label="Betrag netto" suffix="€" placeholder="z. B. 130" v-model="amountInput" autofocus @keydown.enter.prevent="insertTimeEntry" />
                    <p class="mt-1 text-xs text-gray-500">Die Dauer wird aus dem Tarif zurückgerechnet.</p>

                    <!-- Amount doesn't fit the rate: offer the nearest valid amounts -->
                    <div v-if="amountInput && amountResult && !amountResult.exact" class="mt-3 text-xs text-amber-700 dark:text-accent">
                        Dieser Betrag passt nicht zum Tarif. Möglich:
                        <button v-for="suggestion in [amountResult.lower, amountResult.upper].filter(Boolean)" :key="suggestion" type="button"
                            class="ml-1 rounded-full px-2 py-0.5 font-medium ring-1 ring-inset ring-accent/40 hover:bg-accent/10"
                            @click="amountInput = String(suggestion).replace('.', ',')">{{ formatMoney(suggestion) }}</button>
                    </div>
                </div>

                <div v-if="durationPrice !== null && durationMinutes > 0" class="mt-5 rounded-xl bg-primary-500/10 p-4 ring-1 ring-primary-500/25">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDuration(durationMinutes) }}</span>
                        <span class="font-display text-2xl font-bold text-gray-900 dark:text-white">{{ formatMoney(durationPrice) }}</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">{{ breakdown(timeEntry, durationMinutes) }}</div>
                </div>
                <p v-else-if="timeMode === 'duration' && durationInput" class="mt-3 text-xs text-red-500">Bitte gib eine gültige Dauer an.</p>
            </template>

            <template #footer>
                <VButton variant="secondary" @click="timeEntry = null">Abbrechen</VButton>
                <VButton :disabled="durationPrice === null || durationMinutes <= 0" @click="insertTimeEntry">Position einfügen</VButton>
            </template>
        </VModal>
    </form>
</template>
