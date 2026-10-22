<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { DocumentTextIcon, ListBulletIcon, ChatBubbleBottomCenterTextIcon, PlusIcon, TrashIcon, WrenchScrewdriverIcon, CubeIcon, PuzzlePieceIcon, AdjustmentsHorizontalIcon, BanknotesIcon, CheckIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VButton from '@/components/VButton.vue';
import VUrlInput from '@/components/VUrlInput.vue';
import QuickCustomerModal from '@/Pages/Invoices/QuickCustomerModal.vue';
import { vAutosize, continueBullet, addBullet } from '@/composables/useItemText';
import { formatDate, formatMoney } from '@/utils/format';

const props = defineProps({
    offer: { type: Object, required: true },
    customers: { type: Array, required: true },
    catalog: { type: Array, default: () => [] },
    types: { type: Array, required: true },
    packages: { type: Array, required: true },
    features: { type: Array, required: true },
    integrations: { type: Array, required: true },
    contentDelivery: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    hints: { type: Object, required: true },
    isEdit: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const units = ['Pauschale', 'Stück', 'Stunde', 'Monat', 'Jahr', 'Tag'];

const toFormNumber = (value) => value === '' || value === null || value === undefined ? '' : String(Number(value)).replace('.', ',');
const parseNumber = (value) => Number(String(value ?? '').replace(',', '.')) || 0;

const form = useForm({
    customer_id: props.offer.customer_id ?? '',
    type: props.offer.type,
    number: props.offer.number ?? '',
    title: props.offer.title ?? '',
    url: props.offer.url ?? '',
    offer_date: props.offer.offer_date ?? '',
    valid_until: props.offer.valid_until ?? '',
    tax_rate: toFormNumber(props.offer.tax_rate),
    package: props.offer.package ?? null,
    features: [...(props.offer.features ?? [])],
    integrations: [...(props.offer.integrations ?? [])],
    other_function: props.offer.other_function ?? '',
    content_delivery: props.offer.content_delivery ?? null,
    payment_terms: props.offer.payment_terms ?? 'prepaid',
    reference_customer: Boolean(props.offer.reference_customer),
    maintenance_package: props.offer.maintenance_package ?? '',
    maintenance_monthly_price: toFormNumber(props.offer.maintenance_monthly_price),
    intro: props.offer.intro ?? '',
    notes: props.offer.notes ?? '',
    items: props.offer.items.map(item => ({
        description: item.description ?? '',
        quantity: toFormNumber(item.quantity),
        unit: item.unit ?? '',
        unit_price: toFormNumber(item.unit_price),
        list_price: toFormNumber(item.list_price),
    })),
});

const currentType = computed(() => props.types.find(type => type.value === form.type));
const typePackages = computed(() => props.packages.filter(pkg => pkg.type === form.type));
const isWebapp = computed(() => form.type === 'webapp');

// Switching the project type: other packages, and the standard maintenance price unless changed by hand
const chooseType = (value) => {
    if (form.type === value) return;
    const previous = currentType.value;
    form.type = value;

    if (parseNumber(form.maintenance_monthly_price) === previous?.maintenance) {
        form.maintenance_monthly_price = toFormNumber(currentType.value.maintenance);
    }

    const packages = typePackages.value;
    choosePackage(packages.length === 1 ? packages[0] : null);
};

// Choosing a package fills (or replaces) the package item with name, features and "from" price
const packageItem = () => form.items.findIndex(item => props.packages.some(pkg => item.description.startsWith(pkg.name)));
const choosePackage = (pkg) => {
    form.package = pkg?.value ?? null;
    const index = packageItem();

    if (!pkg) {
        if (index >= 0) form.items.splice(index, 1);
        if (form.items.length === 0) addItem();
        return;
    }

    const row = {
        description: [pkg.name, ...(pkg.features ?? []).map(feature => `- ${feature}`)].join('\n'),
        quantity: '1',
        unit: 'Pauschale',
        unit_price: toFormNumber(pkg.price),
        list_price: toFormNumber(pkg.price),
    };

    if (index >= 0) form.items.splice(index, 1, row);
    else if (form.items.length === 1 && !form.items[0].description && !form.items[0].unit_price) form.items.splice(0, 1, row);
    else form.items.unshift(row);
};

const toggle = (list, value) => {
    form[list] = form[list].includes(value) ? form[list].filter(item => item !== value) : [...form[list], value];
};

const addItem = () => form.items.push({ description: '', quantity: '1', unit: 'Pauschale', unit_price: '', list_price: '' });
const removeItem = (index) => form.items.splice(index, 1);

const catalogSelection = ref('');
const insertFromCatalog = () => {
    const entry = props.catalog.find(item => String(item.id) === String(catalogSelection.value));
    catalogSelection.value = '';
    if (!entry) return;

    const row = {
        description: [entry.name, entry.description].filter(Boolean).join('\n'),
        quantity: '1',
        unit: entry.unit ?? '',
        unit_price: toFormNumber(entry.unit_price),
        list_price: '',
    };
    const onlyEmptyRow = form.items.length === 1 && !form.items[0].description && !form.items[0].unit_price;
    if (onlyEmptyRow) form.items.splice(0, 1, row);
    else form.items.push(row);
};

const hasDiscount = (item) => item.list_price !== '' && parseNumber(item.list_price) > parseNumber(item.unit_price);

const lineTotal = (item) => Math.round(parseNumber(item.quantity) * parseNumber(item.unit_price) * 100) / 100;
const projectSubtotal = computed(() => form.items.reduce((sum, item) => sum + lineTotal(item), 0));
const maintenanceAnnual = computed(() => Math.round(parseNumber(form.maintenance_monthly_price) * 1200) / 100);
const subtotal = computed(() => projectSubtotal.value + maintenanceAnnual.value);
const taxAmount = computed(() => Math.round(subtotal.value * parseNumber(form.tax_rate)) / 100);
const total = computed(() => subtotal.value + taxAmount.value);

const creatingCustomer = ref(false);
const customerCreated = (id) => {
    creatingCustomer.value = false;
    if (id) form.customer_id = id;
};
const recipient = computed(() => props.customers.find(c => String(c.value) === String(form.customer_id))?.recipient);

const itemError = (index, field) => form.errors[`items.${index}.${field}`];

const submit = () => {
    if (props.isEdit)
        form.put(route('offers.update', props.offer.id), { preserveScroll: true });
    else
        form.post(route('offers.store'));
};

const chipClass = (active) => active
    ? 'border-primary-500 bg-primary-500/[0.08] text-gray-900 dark:text-gray-100'
    : 'border-gray-200 text-gray-600 hover:border-gray-300 dark:border-white/10 dark:text-gray-400 dark:hover:border-white/20';
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <fieldset :disabled="readonly" class="grid grid-cols-1 gap-6 xl:grid-cols-3" :class="readonly && 'opacity-80'">

            <!-- Kopfdaten -->
            <section class="card p-6 xl:col-span-2">
                <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Angebot</h2>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <VSelect id="customer_id" label="Kunde" placeholder="Kunde wählen …" :options="customers" v-model="form.customer_id" :error="form.errors.customer_id" />
                        <p v-if="recipient" class="mt-2 text-xs text-gray-500">
                            {{ recipient.name }} · {{ [recipient.street, [recipient.zip, recipient.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || 'keine Adresse hinterlegt' }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500">
                            Kunde fehlt? <button type="button" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" @click="creatingCustomer = true">Neuen Kunden anlegen</button>
                        </p>
                    </div>

                    <VInput id="title" class="sm:col-span-3" label="Projekt" placeholder="z. B. Website Musterfirma" v-model="form.title" :error="form.errors.title" />
                    <VUrlInput id="url" class="sm:col-span-3" label="Domain (optional)" v-model="form.url" :error="form.errors.url" />
                    <VInput id="number" class="sm:col-span-2" label="Angebotsnummer" v-model="form.number" :error="form.errors.number" />
                    <VInput id="offer_date" class="sm:col-span-2" type="date" label="Angebotsdatum" v-model="form.offer_date" :error="form.errors.offer_date" />
                    <VInput id="valid_until" class="sm:col-span-2" type="date" label="Gültig bis" v-model="form.valid_until" :error="form.errors.valid_until" />
                    <VInput id="tax_rate" class="sm:col-span-2" label="USt." suffix="%" v-model="form.tax_rate" :error="form.errors.tax_rate" />
                </div>
            </section>

            <!-- Summen -->
            <section class="card relative overflow-hidden p-6">
                <div class="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full bg-primary-500/20 blur-[70px]" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="card-title">Summe</h2>
                    <dl class="mt-5 space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Projekt netto</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(projectSubtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Betreuung 12 Monate</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(maintenanceAnnual) }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2.5 dark:border-white/10"><dt class="text-gray-500">Summe netto</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">USt. {{ form.tax_rate || 0 }} %</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(taxAmount) }}</dd></div>
                    </dl>
                    <div class="mt-5 rounded-xl bg-primary-500/10 p-4 ring-1 ring-primary-500/25">
                        <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">Gesamt brutto</div>
                        <div class="mt-1 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ formatMoney(total) }}</div>
                        <div v-if="form.valid_until" class="mt-1 text-xs text-gray-500">gültig bis {{ formatDate(form.valid_until) }}</div>
                    </div>
                </div>
            </section>

            <!-- Art des Projekts + Paket -->
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><CubeIcon class="h-5 w-5 text-primary-500" />Art des Projekts</h2>
                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <button v-for="type in types" :key="type.value" type="button"
                        class="relative rounded-xl border p-4 text-left transition" :class="chipClass(form.type === type.value)"
                        @click="chooseType(type.value)">
                        <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ type.label }}</span>
                        <span class="mt-1 block text-sm">{{ type.description }}</span>
                        <CheckIcon v-if="form.type === type.value" class="absolute top-3 right-3 h-5 w-5 text-primary-500" />
                    </button>
                </div>
                <div v-if="form.errors.type" class="mt-1 text-xs text-red-500">{{ form.errors.type }}</div>

                <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ isWebapp ? 'Web-App' : 'Paket' }}</h3>
                <p class="mt-0.5 text-xs text-gray-500">Füllt die erste Position mit Leistungsumfang und Richtpreis – den Preis kannst du anpassen, ein niedrigerer wird als Rabatt angezeigt.</p>
                <div class="mt-3 grid grid-cols-1 gap-3" :class="isWebapp ? '' : 'md:grid-cols-2'">
                    <button v-for="pkg in typePackages" :key="pkg.value" type="button"
                        class="relative rounded-xl border p-4 text-left transition" :class="chipClass(form.package === pkg.value)"
                        @click="choosePackage(form.package === pkg.value ? null : pkg)">
                        <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ pkg.name }}</span>
                        <span class="block text-sm font-medium text-primary-600 dark:text-primary-300">ab {{ formatMoney(pkg.price) }}</span>
                        <ul v-if="pkg.features" class="mt-2 space-y-0.5 text-xs">
                            <li v-for="feature in pkg.features" :key="feature">• {{ feature }}</li>
                        </ul>
                        <p v-else class="mt-2 text-xs">{{ pkg.description }}</p>
                        <CheckIcon v-if="form.package === pkg.value" class="absolute top-3 right-3 h-5 w-5 text-primary-500" />
                    </button>
                </div>
                <div v-if="form.errors.package" class="mt-1 text-xs text-red-500">{{ form.errors.package }}</div>
            </section>

            <!-- Zusatzoptionen -->
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><PuzzlePieceIcon class="h-5 w-5 text-primary-500" />Zusatzoptionen</h2>
                <template v-if="isWebapp">
                    <div class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="feature in features" :key="feature.value" class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition" :class="chipClass(form.features.includes(feature.value))">
                            <input type="checkbox" class="form-checkbox rounded text-primary-500" :checked="form.features.includes(feature.value)" @change="toggle('features', feature.value)">
                            {{ feature.label }}
                        </label>
                    </div>
                </template>
                <p v-else class="mt-3 flex gap-2 text-sm text-gray-500"><InformationCircleIcon class="h-5 w-5 shrink-0" />{{ hints.features }}</p>
                <VInput id="other_function" class="mt-4" label="Sonstige Funktion (optional)" v-model="form.other_function" :error="form.errors.other_function" />
            </section>

            <!-- Positionen -->
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><ListBulletIcon class="h-5 w-5 text-primary-500" />Leistungen</h2>
                <p class="mt-1 text-sm text-gray-500">Einmalige Projektkosten. Die laufende Betreuung kommt automatisch dazu (siehe unten).</p>
                <div v-if="form.errors.items" class="mt-2 text-xs text-red-500">{{ form.errors.items }}</div>

                <div class="mt-5 space-y-3">
                    <div class="hidden grid-cols-12 gap-3 px-1 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 lg:grid">
                        <div class="col-span-4">Beschreibung</div>
                        <div class="col-span-1">Menge</div>
                        <div class="col-span-2">Einheit</div>
                        <div class="col-span-2">Preis netto</div>
                        <div class="col-span-1" title="Regulärer Preis – wird durchgestrichen, wenn der Preis darunter liegt">Listenpreis</div>
                        <div class="col-span-2 text-right">Betrag</div>
                    </div>

                    <div v-for="(item, index) in form.items" :key="index"
                        class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 p-3 lg:grid-cols-12 lg:items-start lg:border-0 lg:p-1 dark:border-white/10">
                        <div class="col-span-2 lg:col-span-4">
                            <textarea v-model="item.description" v-autosize rows="2" class="form-textarea w-full resize-none overflow-hidden"
                                placeholder="z. B. Website Relaunch&#10;- Responsive Design&#10;- Kontaktformular"
                                @keydown.enter="continueBullet($event, item)"></textarea>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-[11px] text-gray-400">1. Zeile = Titel · „- “ = Aufzählungspunkt</span>
                                <button v-if="!readonly" type="button" class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-200 transition hover:text-primary-600 hover:ring-primary-400 dark:ring-white/10 dark:hover:text-primary-300"
                                    @click="addBullet($event, item)">• Aufzählung</button>
                            </div>
                            <div v-if="itemError(index, 'description')" class="text-xs mt-1 text-red-500">{{ itemError(index, 'description') }}</div>
                        </div>
                        <VInput class="lg:col-span-1" :id="`qty_${index}`" placeholder="Menge" v-model="item.quantity" :error="itemError(index, 'quantity')" />
                        <VInput class="lg:col-span-2" :id="`unit_${index}`" placeholder="Einheit" list="offer-units" v-model="item.unit" :error="itemError(index, 'unit')" />
                        <VInput class="lg:col-span-2" :id="`price_${index}`" placeholder="Preis" suffix="€" v-model="item.unit_price" :error="itemError(index, 'unit_price')" />
                        <VInput class="lg:col-span-1" :id="`list_${index}`" placeholder="Liste" v-model="item.list_price" :error="itemError(index, 'list_price')" />
                        <div class="flex items-center justify-between gap-2 lg:col-span-2 lg:justify-end lg:pt-2">
                            <span class="text-right">
                                <span v-if="hasDiscount(item)" class="mr-1.5 text-xs text-gray-400 line-through">{{ formatMoney(parseNumber(item.list_price) * parseNumber(item.quantity)) }}</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(lineTotal(item)) }}</span>
                            </span>
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
                        <option v-for="entry in catalog" :key="entry.id" :value="entry.id">{{ entry.name }} · {{ formatMoney(entry.unit_price) }}{{ entry.unit ? ' / ' + entry.unit : '' }}</option>
                    </select>
                </div>
                <datalist id="offer-units">
                    <option v-for="unit in units" :key="unit" :value="unit" />
                </datalist>
            </section>

            <!-- Laufende Betreuung (verpflichtend) -->
            <section class="card p-6 ring-1 ring-primary-500/30 xl:col-span-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="card-title"><WrenchScrewdriverIcon class="h-5 w-5 text-primary-500" />Laufende Betreuung</h2>
                    <span class="rounded-full bg-primary-500/10 px-2 py-0.5 text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-500/30 dark:text-primary-300">verpflichtend</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Teil jedes Angebots: die ersten 12 Monate stehen in der Summe. Nimmt der Kunde an, wird daraus automatisch ein Wartungsvertrag.
                </p>
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <VInput id="maintenance_monthly_price" class="sm:col-span-2" label="Preis pro Monat (netto)" suffix="€" v-model="form.maintenance_monthly_price" :error="form.errors.maintenance_monthly_price" />
                    <VInput id="maintenance_package" class="sm:col-span-2" label="Betreuungspaket (optional)" placeholder="z. B. Small" v-model="form.maintenance_package" :error="form.errors.maintenance_package" />
                    <div class="flex flex-col justify-end sm:col-span-2">
                        <div class="rounded-lg bg-primary-500/[0.06] px-3 py-2 text-sm">
                            <span class="text-gray-500">12 Monate:</span> <strong class="text-gray-900 dark:text-gray-100">{{ formatMoney(maintenanceAnnual) }}</strong> netto
                        </div>
                    </div>
                </div>
                <ul class="mt-4 grid grid-cols-1 gap-1 text-sm text-gray-600 sm:grid-cols-2 dark:text-gray-400">
                    <li v-for="service in currentType?.services" :key="service" class="flex gap-2"><CheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" />{{ service }}</li>
                </ul>
            </section>

            <!-- Projektdetails -->
            <section class="card p-6 xl:col-span-2">
                <h2 class="card-title"><AdjustmentsHorizontalIcon class="h-5 w-5 text-primary-500" />Projektdetails</h2>

                <template v-if="isWebapp">
                <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-gray-100">Anbindung an andere Systeme</h3>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label v-for="integration in integrations" :key="integration.value" class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition" :class="chipClass(form.integrations.includes(integration.value))">
                        <input type="checkbox" class="form-checkbox rounded text-primary-500" :checked="form.integrations.includes(integration.value)" @change="toggle('integrations', integration.value)">
                        {{ integration.label }}
                    </label>
                </div>
                </template>

                <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-gray-100">Content-Lieferung (Texte/Bilder)</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-for="option in contentDelivery" :key="option.value" type="button" class="rounded-lg border px-3 py-2 text-sm transition" :class="chipClass(form.content_delivery === option.value)"
                        @click="form.content_delivery = form.content_delivery === option.value ? null : option.value">{{ option.label }}</button>
                </div>

                <label class="mt-5 flex cursor-pointer items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" v-model="form.reference_customer" class="form-checkbox rounded text-primary-500">
                    Kunde darf als Referenz angegeben werden
                </label>
            </section>

            <!-- Zahlungsbedingungen -->
            <section class="card p-6">
                <h2 class="card-title"><BanknotesIcon class="h-5 w-5 text-primary-500" />Zahlungsbedingungen</h2>
                <div class="mt-5 space-y-2">
                    <label v-for="option in paymentTerms" :key="option.value" class="flex cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2 text-sm transition" :class="chipClass(form.payment_terms === option.value)">
                        <input type="radio" v-model="form.payment_terms" :value="option.value" class="form-radio mt-0.5 text-primary-500">
                        {{ option.label }}
                    </label>
                </div>
                <div v-if="form.errors.payment_terms" class="mt-1 text-xs text-red-500">{{ form.errors.payment_terms }}</div>
            </section>

            <!-- Texte -->
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-primary-500" />Texte</h2>
                <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label for="intro" class="mb-1 block text-sm font-medium">Einleitung (optional)</label>
                        <textarea id="intro" v-model="form.intro" rows="3" class="form-textarea w-full" placeholder="Vielen Dank für das nette Gespräch! Wie besprochen erhalten Sie hier mein Angebot …"></textarea>
                        <div v-if="form.errors.intro" class="text-xs mt-1 text-red-500">{{ form.errors.intro }}</div>
                    </div>
                    <div>
                        <label for="notes" class="mb-1 block text-sm font-medium">Anmerkung (optional)</label>
                        <textarea id="notes" v-model="form.notes" rows="3" class="form-textarea w-full" placeholder="z. B. Umsetzung ab KW 45 möglich."></textarea>
                        <div v-if="form.errors.notes" class="text-xs mt-1 text-red-500">{{ form.errors.notes }}</div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Gültigkeit, AGB-Hinweis und Unterschriftsfelder stehen automatisch im PDF.</p>
            </section>
        </fieldset>

        <!-- Actions -->
        <div v-if="!readonly" class="flex flex-wrap items-center justify-end gap-3">
            <VButton :href="route('offers.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">
                {{ isEdit ? 'Speichern' : 'Angebot erstellen' }}
            </VButton>
        </div>

        <QuickCustomerModal :show="creatingCustomer" :customers="customers" @close="creatingCustomer = false" @created="customerCreated" />
    </form>
</template>
