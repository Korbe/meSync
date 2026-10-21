<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon, StopIcon, DocumentTextIcon, TrashIcon, ClockIcon, CheckCircleIcon } from '@heroicons/vue/24/solid';
import Layout from '@/Layouts/Layout.vue';
import VSelect from '@/components/VSelect.vue';
import { confirm } from '@/composables/useConfirm';
import { formatDate, formatMoney } from '@/utils/format';
import { breakdown, formatDuration, priceForMinutes } from '@/utils/timePricing';

const props = defineProps({
    active: { type: Object, default: null },   // running or paused run
    unbilled: Array,
    billed: Array,
    customers: Array,
    rates: Array,                               // catalog items with time pricing
});

// --- Live clock: computed from the server values, ticking in the browser -------------
const now = ref(Date.now());
let ticker;
onMounted(() => ticker = setInterval(() => now.value = Date.now(), 1000));
onBeforeUnmount(() => clearInterval(ticker));

const elapsed = (session) => session.seconds + (session.running_since ? Math.max(0, Math.floor((now.value - new Date(session.running_since).getTime()) / 1000)) : 0);
const clock = (seconds) => [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60].map(part => String(part).padStart(2, '0')).join(':');
const minutesOf = (seconds) => Math.max(1, Math.ceil(seconds / 60));
const rateOf = (id) => props.rates.find(rate => rate.id === id) ?? null;

const activeSeconds = computed(() => props.active ? elapsed(props.active) : 0);
const activeRate = computed(() => props.active ? rateOf(props.active.catalog_item_id) : null);
const activePrice = computed(() => activeRate.value ? priceForMinutes(activeRate.value, minutesOf(activeSeconds.value)) : null);

// --- Start / pause / stop ----------------------------------------------------------------
const startForm = reactive({ customer_id: '', catalog_item_id: props.rates[0]?.id ?? '' });
const busy = ref(false);
const post = (name, id = null, data = {}) => {
    busy.value = true;
    router.post(id ? route(name, id) : route(name), data, { preserveScroll: true, onFinish: () => busy.value = false });
};
const start = () => post('stopwatch.start', null, { customer_id: startForm.customer_id || null, catalog_item_id: startForm.catalog_item_id || null });

// --- Details of the running run, saved right away -----------------------------------------
const details = reactive({ customer_id: '', catalog_item_id: '', notes: '' });
watch(() => props.active, (session) => {
    details.customer_id = session?.customer_id ?? '';
    details.catalog_item_id = session?.catalog_item_id ?? '';
    details.notes = session?.notes ?? '';
}, { immediate: true });

const save = (session, data) => router.patch(route('stopwatch.update', session.id), data, { preserveScroll: true, preserveState: true });

// --- Unbilled runs: correct the duration, bill several at once ------------------------------
const selected = ref([]);
const selectedSessions = computed(() => props.unbilled.filter(session => selected.value.includes(session.id)));
const mixedCustomers = computed(() => new Set(selectedSessions.value.map(session => session.customer_id ?? 0)).size > 1);
const selectedTotal = computed(() => selectedSessions.value.reduce((sum, session) => sum + Number(session.price ?? 0), 0));

const createInvoice = (ids) => router.get(route('invoices.create'), { sessions: ids });

const remove = async (session) => {
    if (!(await confirm({
        title: 'Einsatz löschen?',
        message: `${formatDuration(session.minutes)} vom ${formatDate(session.started_at)} werden gelöscht und nicht verrechnet.`,
        confirmText: 'Löschen',
        variant: 'danger',
    }))) return;

    router.delete(route('stopwatch.destroy', session.id), { preserveScroll: true });
};

const customerName = (session) => session.customer?.display_name ?? 'Ohne Kunde';
</script>

<template>
    <Layout title="Stoppuhr">
        <p class="-mt-4 mb-6 max-w-2xl text-gray-500">Für Einsätze vor Ort: starten, wenn du anfängst – die Zeit läuft am Server weiter, auch wenn du die App schließt. Danach mit einem Klick in eine Rechnung übernehmen.</p>

        <!-- Clock -->
        <section class="card relative mb-6 overflow-hidden p-6 sm:p-8">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[32rem] -translate-x-1/2 rounded-full blur-[110px] transition-colors"
                :class="active?.status === 'running' ? 'bg-primary-500/30' : active ? 'bg-accent/25' : 'bg-gray-500/10'" aria-hidden="true"></div>

            <div class="relative flex flex-col items-center text-center">
                <div class="font-mono text-[11px] font-semibold uppercase tracking-[0.22em]"
                    :class="active?.status === 'running' ? 'text-primary-500' : active ? 'text-accent' : 'text-gray-500'">
                    {{ active?.status === 'running' ? 'Läuft' : active ? 'Pausiert' : 'Bereit' }}
                </div>
                <div class="mt-2 font-display text-6xl font-bold tracking-tight tabular-nums text-gray-900 sm:text-8xl dark:text-white"
                    :class="active?.status === 'paused' && 'animate-pulse'">
                    {{ clock(activeSeconds) }}
                </div>
                <div v-if="active" class="mt-2 text-sm text-gray-500">
                    seit {{ new Date(active.started_at).toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' }) }} Uhr
                    <template v-if="activePrice !== null"> · <span class="font-semibold text-gray-900 dark:text-white">{{ formatMoney(activePrice) }}</span>
                        <span class="hidden sm:inline"> ({{ breakdown(activeRate, minutesOf(activeSeconds)) }})</span>
                    </template>
                </div>

                <!-- Controls -->
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <button v-if="!active" type="button" :disabled="busy" @click="start"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-primary-500 px-8 py-4 text-lg font-semibold text-white shadow-[0_12px_32px_-10px_rgba(31,173,174,0.9)] transition hover:bg-primary-600 disabled:opacity-60">
                        <PlayIcon class="h-6 w-6" />Starten
                    </button>
                    <template v-else>
                        <button v-if="active.status === 'running'" type="button" :disabled="busy" @click="post('stopwatch.pause', active.id)"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-accent px-6 py-4 text-lg font-semibold text-gray-950 transition hover:brightness-95 disabled:opacity-60">
                            <PauseIcon class="h-6 w-6" />Pause
                        </button>
                        <button v-else type="button" :disabled="busy" @click="post('stopwatch.resume', active.id)"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-primary-500 px-6 py-4 text-lg font-semibold text-white transition hover:bg-primary-600 disabled:opacity-60">
                            <PlayIcon class="h-6 w-6" />Weiter
                        </button>
                        <button type="button" :disabled="busy" @click="post('stopwatch.stop', active.id)"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-red-500 px-6 py-4 text-lg font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                            <StopIcon class="h-6 w-6" />Stopp
                        </button>
                    </template>
                </div>
            </div>

            <!-- Before start: who and which rate -->
            <div v-if="!active" class="relative mx-auto mt-8 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                <VSelect id="start_customer" label="Kunde (optional)" placeholder="Später zuordnen" :options="customers" v-model="startForm.customer_id" />
                <VSelect id="start_rate" label="Leistung" :options="rates.map(rate => ({ value: rate.id, label: rate.name }))" v-model="startForm.catalog_item_id" />
            </div>

            <!-- While running: details, saved right away -->
            <div v-else class="relative mx-auto mt-8 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                <VSelect id="active_customer" label="Kunde" placeholder="Kunde wählen …" :options="customers" v-model="details.customer_id"
                    @update:modelValue="save(active, { customer_id: $event || null })" />
                <VSelect id="active_rate" label="Leistung" :options="rates.map(rate => ({ value: rate.id, label: rate.name }))" v-model="details.catalog_item_id"
                    @update:modelValue="save(active, { catalog_item_id: $event || null })" />
                <div class="sm:col-span-2">
                    <label for="active_notes" class="mb-1 block text-sm font-medium">Was wurde gemacht?</label>
                    <textarea id="active_notes" v-model="details.notes" rows="3" class="form-textarea w-full"
                        placeholder="Eine Zeile pro Punkt – steht so auf der Rechnung, z. B.&#10;Drucker eingerichtet&#10;Windows-Updates installiert"
                        @blur="details.notes !== (active.notes ?? '') && save(active, { notes: details.notes })"></textarea>
                </div>
            </div>

            <p v-if="!rates.length" class="relative mt-6 text-center text-sm text-accent">
                Im <Link :href="route('catalog.index')" class="underline">Leistungskatalog</Link> gibt es noch keinen Zeittarif – dann wird die Zeit gemessen, aber kein Preis berechnet.
            </p>
        </section>

        <!-- Unbilled runs -->
        <section class="card mb-6 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-3">
                <h2 class="card-title"><ClockIcon class="h-5 w-5 text-primary-500" />Noch zu verrechnen</h2>
                <button v-if="selected.length" type="button" :disabled="mixedCustomers" @click="createInvoice(selected)"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
                    :title="mixedCustomers ? 'Nur Einsätze desselben Kunden gemeinsam verrechnen' : ''">
                    <DocumentTextIcon class="h-5 w-5" />{{ selected.length }} in Rechnung übernehmen · {{ formatMoney(selectedTotal) }}
                </button>
            </div>
            <p v-if="mixedCustomers" class="px-6 pb-2 text-xs text-accent">Die ausgewählten Einsätze gehören zu verschiedenen Kunden – pro Rechnung nur ein Kunde.</p>
            <p v-if="unbilled.length === 0" class="px-6 pb-6 text-sm text-gray-500">Alles verrechnet.</p>

            <div v-for="session in unbilled" :key="session.id" class="flex flex-wrap items-center gap-3 border-t border-gray-100 px-6 py-3 dark:border-white/[0.06]">
                <input type="checkbox" :value="session.id" v-model="selected" class="form-checkbox rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5" />
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ customerName(session) }}
                        <span class="text-sm font-normal text-gray-500">· {{ formatDate(session.started_at) }} · {{ session.catalog_item?.name ?? 'ohne Tarif' }}</span>
                    </div>
                    <div v-if="session.notes" class="truncate text-xs text-gray-500">{{ session.notes.split('\n').join(' · ') }}</div>
                </div>
                <!-- Duration: measured, can be corrected -->
                <div class="flex items-center gap-1.5 text-sm">
                    <input type="number" min="1" max="1440" :value="session.minutes" class="form-input w-20 py-1 text-right tabular-nums"
                        title="Dauer in Minuten – zum Korrigieren ändern"
                        @change="save(session, { billed_minutes: Number($event.target.value) || null })" />
                    <span class="text-gray-500">Min.</span>
                </div>
                <div class="w-24 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ session.price !== null ? formatMoney(session.price) : '–' }}</div>
                <div class="flex gap-1">
                    <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="Rechnung erstellen" @click="createInvoice([session.id])">
                        <DocumentTextIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Löschen" @click="remove(session)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>

        <!-- Recently billed -->
        <section v-if="billed.length" class="card overflow-hidden">
            <div class="p-6 pb-3"><h2 class="card-title"><CheckCircleIcon class="h-5 w-5 text-primary-500" />Zuletzt verrechnet</h2></div>
            <div v-for="session in billed" :key="session.id" class="flex items-center gap-3 border-t border-gray-100 px-6 py-2.5 text-sm dark:border-white/[0.06]">
                <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300">{{ customerName(session) }} · {{ formatDate(session.started_at) }} · {{ formatDuration(session.minutes) }}</span>
                <Link :href="route('invoices.edit', session.invoice_id)" class="font-mono text-primary-600 hover:underline dark:text-primary-300">{{ session.invoice?.number }}</Link>
            </div>
        </section>
    </Layout>
</template>
