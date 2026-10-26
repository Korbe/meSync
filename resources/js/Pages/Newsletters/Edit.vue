<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { EnvelopeIcon, UsersIcon, CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon, ClockIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';
import VModal from '@/components/VModal.vue';
import { confirm } from '@/composables/useConfirm';
import { formatDate } from '@/utils/format';

const props = defineProps({
    newsletter: Object,
    customers: Array,    // id, name, email, interests, opted_out
    interests: Array,
    audiences: Array,
    recipients: Array,   // after sending: who got it
    testTo: String,
    demo: Boolean,
});

const sent = computed(() => !!props.newsletter.sent_at);
const page = usePage();
const sendError = computed(() => page.props.errors?.send);

const form = useForm({
    subject: props.newsletter.subject ?? '',
    body: props.newsletter.body ?? '',
    audience: props.newsletter.audience ?? 'all',
    interests: [...(props.newsletter.interests ?? [])],
    customer_ids: [...(props.newsletter.customer_ids ?? [])],
});

const toggle = (list, value) => {
    form[list] = form[list].includes(value) ? form[list].filter(item => item !== value) : [...form[list], value];
};

// Same rules as on the server: with email, not unsubscribed, in the chosen audience
const reachable = (customer) => !!customer.email && !customer.opted_out;
const chosen = computed(() => props.customers.filter(customer => {
    if (form.audience === 'interests') return form.interests.some(interest => customer.interests.includes(interest));
    if (form.audience === 'selected') return form.customer_ids.includes(customer.id);
    return true;
}));
const recipientsNow = computed(() => chosen.value.filter(reachable));
const withoutEmail = computed(() => chosen.value.filter(customer => !customer.email).length);
const optedOut = computed(() => chosen.value.filter(customer => customer.email && customer.opted_out).length);

// Picking single customers
const search = ref('');
const searchable = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.customers.filter(customer => !term || `${customer.name} ${customer.email ?? ''}`.toLowerCase().includes(term));
});

const save = (options = {}) => props.newsletter.id
    ? form.put(route('newsletters.update', props.newsletter.id), { preserveScroll: true, ...options })
    : form.post(route('newsletters.store'), options);

// Test mail to yourself (saves the draft first)
const testing = ref(false);
const testForm = useForm({ to: props.testTo ?? '' });
const sendTest = () => save({
    onSuccess: () => testForm.post(route('newsletters.test', props.newsletter.id), { preserveScroll: true, onSuccess: () => testing.value = false }),
});

const send = async () => {
    const count = recipientsNow.value.length;
    if (!(await confirm({
        title: `Benachrichtigung an ${count} Kunden senden?`,
        message: `„${form.subject}“ geht jetzt raus – jeder Kunde bekommt eine eigene E-Mail mit persönlicher Anrede. Danach lässt sich die Benachrichtigung nicht mehr ändern.`,
        confirmText: 'Jetzt senden',
    }))) return;

    save({ onSuccess: () => router.post(route('newsletters.send', props.newsletter.id), {}, { preserveScroll: true }) });
};

const remove = async () => {
    if (await confirm({ title: `„${props.newsletter.subject}“ löschen?`, confirmText: 'Löschen', variant: 'danger' })) {
        router.delete(route('newsletters.destroy', props.newsletter.id));
    }
};

const recipientStatus = (recipient) => recipient.sent_at ? 'sent' : recipient.failed_at ? 'failed' : 'pending';
const sentCount = computed(() => props.recipients.filter(recipient => recipient.sent_at).length);
const failedCount = computed(() => props.recipients.filter(recipient => !recipient.sent_at && recipient.failed_at).length);
</script>

<template>
    <Layout :title="newsletter.id ? (newsletter.subject || 'Benachrichtigung') : 'Neue Benachrichtigung'" :backTo="route('newsletters.index')">
        <template v-if="newsletter.id && !sent" v-slot:actions>
            <VButton variant="secondary" icon="TrashIcon" @click="remove">Löschen</VButton>
        </template>

        <!-- Sent: as it went out, with the delivery per customer -->
        <div v-if="sent" class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><EnvelopeIcon class="h-5 w-5 text-primary-500" />{{ newsletter.subject }}</h2>
                <p class="mt-1 text-xs text-gray-500">Verschickt am {{ formatDate(newsletter.sent_at) }}</p>
                <div class="mt-4 whitespace-pre-line rounded-xl bg-gray-50 p-4 text-sm text-gray-700 dark:bg-white/[0.03] dark:text-gray-300">{{ newsletter.body }}</div>
            </section>

            <section class="card overflow-hidden xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2 p-6 pb-3">
                    <h2 class="card-title"><UsersIcon class="h-5 w-5 text-primary-500" />Empfänger</h2>
                    <span class="text-xs text-gray-500">{{ sentCount }} von {{ recipients.length }} zugestellt<template v-if="failedCount"> · <span class="text-red-500">{{ failedCount }} Fehler</span></template></span>
                </div>
                <ul class="max-h-[32rem] divide-y divide-gray-100 overflow-y-auto dark:divide-white/[0.06]">
                    <li v-for="recipient in recipients" :key="recipient.id" class="flex items-center gap-3 px-6 py-2.5">
                        <CheckCircleIcon v-if="recipientStatus(recipient) === 'sent'" class="h-5 w-5 shrink-0 text-primary-500" />
                        <ExclamationTriangleIcon v-else-if="recipientStatus(recipient) === 'failed'" class="h-5 w-5 shrink-0 text-red-500" />
                        <ClockIcon v-else class="h-5 w-5 shrink-0 text-gray-400" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ recipient.name }}</div>
                            <div class="truncate text-xs text-gray-500">{{ recipient.email }}</div>
                            <div v-if="recipientStatus(recipient) === 'failed'" class="truncate text-xs text-red-500" :title="recipient.error">{{ recipient.error }}</div>
                        </div>
                    </li>
                </ul>
                <p v-if="recipients.some(recipient => !recipient.sent_at && !recipient.failed_at)" class="border-t border-gray-100 px-6 py-3 text-xs text-gray-500 dark:border-white/[0.06]">
                    Die Mails gehen über die Queue raus – Seite neu laden für den aktuellen Stand.
                </p>
            </section>
        </div>

        <!-- Draft -->
        <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><EnvelopeIcon class="h-5 w-5 text-primary-500" />Nachricht</h2>
                <VInput id="subject" class="mt-5" label="Betreff" placeholder="z. B. Neu: PC-Wartung zum Fixpreis" v-model="form.subject" :error="form.errors.subject" mandatory />

                <div class="mt-4">
                    <label for="body" class="mb-1 block text-sm font-medium">Text <span class="text-red-500">*</span></label>
                    <textarea id="body" v-model="form.body" rows="14" class="form-textarea w-full" placeholder="Ich möchte Sie heute auf … aufmerksam machen."></textarea>
                    <div v-if="form.errors.body" class="mt-1 text-xs text-red-500">{{ form.errors.body }}</div>
                    <p class="mt-1.5 text-xs text-gray-500">
                        Anrede („Guten Tag Herr Muster,“), Gruß mit deinem Namen, Logo und Abmeldelink kommen automatisch dazu.
                        Leerzeile = neuer Absatz, <span class="font-mono">**fett**</span> für Hervorhebungen, <span class="font-mono">[Text](https://…)</span> für Links.
                    </p>
                </div>
            </section>

            <div class="space-y-6 xl:col-span-2">
                <section class="card p-6">
                    <h2 class="card-title"><UsersIcon class="h-5 w-5 text-primary-500" />Empfänger</h2>

                    <div class="mt-4 flex flex-wrap gap-1 rounded-xl p-1 text-sm ring-1 ring-gray-200 ring-inset dark:ring-white/10">
                        <button v-for="audience in audiences" :key="audience.value" type="button"
                            class="flex-1 cursor-pointer whitespace-nowrap rounded-lg px-3 py-1.5 font-medium transition"
                            :class="form.audience === audience.value ? 'bg-primary-500 text-white' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'"
                            @click="form.audience = audience.value">{{ audience.label }}</button>
                    </div>

                    <!-- By interest -->
                    <div v-if="form.audience === 'interests'" class="mt-4 flex flex-wrap gap-2">
                        <button v-for="interest in interests" :key="interest.value" type="button"
                            class="cursor-pointer rounded-full px-3 py-1 text-sm font-medium ring-1 transition"
                            :class="form.interests.includes(interest.value) ? 'bg-primary-500/15 text-primary-700 ring-primary-500 dark:text-primary-300' : 'text-gray-600 ring-gray-200 hover:ring-gray-300 dark:text-gray-400 dark:ring-white/10'"
                            @click="toggle('interests', interest.value)">{{ interest.label }}</button>
                        <p class="w-full text-xs text-gray-500">Kunden mit mindestens einem der gewählten Interessen.</p>
                    </div>

                    <!-- Single customers -->
                    <div v-if="form.audience === 'selected'" class="mt-4">
                        <div class="relative">
                            <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input v-model="search" type="search" class="form-input w-full pl-9" placeholder="Kunde suchen …" />
                        </div>
                        <ul class="mt-2 max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 dark:divide-white/[0.06] dark:border-white/10">
                            <li v-for="customer in searchable" :key="customer.id">
                                <label class="flex items-center gap-3 px-3 py-2" :class="reachable(customer) ? 'cursor-pointer hover:bg-primary-500/[0.04]' : 'opacity-50'">
                                    <input type="checkbox" class="form-checkbox rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
                                        :checked="form.customer_ids.includes(customer.id)" :disabled="!reachable(customer)" @change="toggle('customer_ids', customer.id)" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-gray-900 dark:text-gray-100">{{ customer.name }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ customer.email || 'keine E-Mail-Adresse' }}<template v-if="customer.email && customer.opted_out"> · abgemeldet</template></span>
                                    </span>
                                </label>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-5 rounded-xl bg-primary-500/10 p-3 ring-1 ring-primary-500/25">
                        <div class="font-display text-2xl font-bold text-gray-900 dark:text-white">{{ recipientsNow.length }}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            Empfänger
                            <template v-if="withoutEmail"> · {{ withoutEmail }} ohne E-Mail</template>
                            <template v-if="optedOut"> · {{ optedOut }} abgemeldet</template>
                        </div>
                    </div>

                    <!-- Who exactly (single customers: the list above already shows it) -->
                    <ul v-if="form.audience !== 'selected' && chosen.length" class="mt-3 max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 dark:divide-white/[0.06] dark:border-white/10">
                        <li v-for="customer in chosen" :key="customer.id" class="flex items-center gap-3 px-3 py-2" :class="!reachable(customer) && 'opacity-50'">
                            <CheckCircleIcon v-if="reachable(customer)" class="h-4 w-4 shrink-0 text-primary-500" />
                            <XCircleIcon v-else class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-gray-900 dark:text-gray-100">{{ customer.name }}</span>
                                <span class="block truncate text-xs text-gray-500">{{ customer.email || 'keine E-Mail-Adresse' }}<template v-if="customer.email && customer.opted_out"> · abgemeldet</template></span>
                            </span>
                        </li>
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">Werbung per E-Mail nur an Kunden, die eingewilligt haben, oder an Bestandskunden zu ähnlichen eigenen Leistungen. Jede Mail enthält einen Abmeldelink.</p>
                </section>

                <section class="card p-6">
                    <div v-if="sendError" class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-600 ring-1 ring-red-500/30 dark:text-red-400">{{ sendError }}</div>
                    <div v-if="demo" class="mb-4 rounded-xl bg-amber-500/10 p-3 text-sm text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-300">Im Demo-Modus werden keine E-Mails verschickt.</div>
                    <div class="flex flex-wrap gap-3">
                        <VButton variant="secondary" :loading="form.processing" :disabled="form.processing" @click="save()">Entwurf speichern</VButton>
                        <VButton v-if="newsletter.id" variant="secondary" icon="BeakerIcon" :disabled="form.processing" @click="testing = true">Testmail</VButton>
                        <VButton v-if="newsletter.id" icon="PaperAirplaneIcon" :disabled="form.processing || recipientsNow.length === 0 || demo" @click="send">
                            An {{ recipientsNow.length }} senden
                        </VButton>
                    </div>
                    <p v-if="!newsletter.id" class="mt-3 text-xs text-gray-500">Nach dem ersten Speichern kannst du dir eine Testmail schicken und den Newsletter senden.</p>
                </section>
            </div>
        </div>

        <VModal :show="testing" max-width="md" @close="testing = false">
            <template #title>Testmail senden</template>
            <template #content>
                <p>Du bekommst die Benachrichtigung so, wie deine Kunden sie sehen – mit allgemeiner Anrede statt dem Namen.</p>
                <VInput id="test_to" class="mt-4" type="email" label="An" v-model="testForm.to" :error="testForm.errors.to" />
            </template>
            <template #footer>
                <VButton variant="secondary" @click="testing = false">Abbrechen</VButton>
                <VButton icon="PaperAirplaneIcon" :loading="testForm.processing || form.processing" :disabled="testForm.processing || form.processing" @click="sendTest">Senden</VButton>
            </template>
        </VModal>
    </Layout>
</template>
