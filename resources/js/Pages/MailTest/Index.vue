<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { EnvelopeIcon, ServerStackIcon, QueueListIcon, ExclamationTriangleIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';

const props = defineProps({
    to: String,
    demo: Boolean,
    config: Object,   // mailer, host, port, scheme, from, fromName, queue
    queue: Object,    // pending, oldestMinutes, failed
    failedJobs: Array,
});

const form = useForm({ to: props.to, via: 'direct' });
const send = (via) => {
    form.via = via;
    form.post(route('mail-test.send'), { preserveScroll: true });
};

const ways = [
    { value: 'direct', title: 'Direkt senden', text: 'Ohne Queue: Ein Fehler beim Mailserver (Zugangsdaten, Port, Absender) zeigt sich sofort hier.' },
    { value: 'queue', title: 'Über die Queue', text: 'Wie Rechnungen, Angebote und Erinnerungen: kommt nur an, wenn auch der Worker läuft.' },
];

// The log mailer only writes to the log file: nothing leaves the server
const logOnly = computed(() => ['log', 'array'].includes(props.config.mailer));
// Jobs waiting for minutes: the worker isn't picking them up
const queueStuck = computed(() => props.queue.pending > 0 && props.queue.oldestMinutes >= 3);

const reload = () => router.reload({ only: ['queue', 'failedJobs'] });
const formatDateTime = (value) => new Date(value.replace(' ', 'T')).toLocaleString('de-AT', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <Layout title="E-Mail-Test">
        <p class="-mt-4 mb-6 max-w-2xl text-gray-500">Schick dir selbst eine Testmail, um zu prüfen, ob die E-Mails von meSync rausgehen.</p>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <!-- Send -->
            <section class="card relative overflow-hidden p-6 xl:col-span-3">
                <div class="pointer-events-none absolute -top-20 -right-20 h-64 w-64 rounded-full bg-primary-500/15 blur-[90px]" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="card-title"><EnvelopeIcon class="h-5 w-5 text-primary-500" />Testmail senden</h2>

                    <div v-if="demo" class="mt-4 rounded-xl bg-amber-500/10 p-3 text-sm text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-300">Im Demo-Modus werden keine E-Mails verschickt.</div>
                    <div v-else-if="logOnly" class="mt-4 rounded-xl bg-amber-500/10 p-3 text-sm text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-300">
                        Der Mailer ist „{{ config.mailer }}“: Mails landen nur in der Log-Datei, es geht nichts raus.
                    </div>

                    <VInput id="to" class="mt-5" type="email" label="An" v-model="form.to" :error="form.errors.to" />

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div v-for="way in ways" :key="way.value" class="flex flex-col rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ way.title }}</div>
                            <p class="mt-1 flex-1 text-xs text-gray-500">{{ way.text }}</p>
                            <VButton class="mt-4 self-start" :variant="way.value === 'direct' ? 'primary' : 'secondary'" icon="PaperAirplaneIcon"
                                :loading="form.processing && form.via === way.value" :disabled="form.processing || demo" @click="send(way.value)">
                                {{ way.title }}
                            </VButton>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Setup + queue -->
            <div class="space-y-6 xl:col-span-2">
                <section class="card p-6">
                    <h2 class="card-title"><ServerStackIcon class="h-5 w-5 text-primary-500" />Einstellungen</h2>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Mailer</dt><dd class="font-mono text-gray-900 dark:text-gray-100">{{ config.mailer }}</dd></div>
                        <div v-if="config.host" class="flex justify-between gap-4"><dt class="text-gray-500">Server</dt><dd class="truncate font-mono text-gray-900 dark:text-gray-100">{{ config.host }}:{{ config.port }}<template v-if="config.scheme"> ({{ config.scheme }})</template></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Absender</dt><dd class="truncate text-gray-900 dark:text-gray-100">{{ config.fromName }} &lt;{{ config.from }}&gt;</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Queue</dt><dd class="font-mono text-gray-900 dark:text-gray-100">{{ config.queue }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">Geändert wird das in der <span class="font-mono">.env</span> am Server.</p>
                </section>

                <section class="card p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="card-title"><QueueListIcon class="h-5 w-5 text-primary-500" />Queue</h2>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-primary-500/10 hover:text-primary-500" title="Aktualisieren" @click="reload">
                            <ArrowPathIcon class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl p-3" :class="queueStuck ? 'bg-amber-500/10 ring-1 ring-amber-500/30' : 'bg-gray-50 dark:bg-white/[0.03]'">
                            <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Wartend</div>
                            <div class="mt-1 font-display text-xl font-bold text-gray-900 dark:text-white">{{ queue.pending ?? '–' }}</div>
                        </div>
                        <div class="rounded-xl p-3" :class="queue.failed ? 'bg-red-500/10 ring-1 ring-red-500/30' : 'bg-gray-50 dark:bg-white/[0.03]'">
                            <div class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Fehlgeschlagen</div>
                            <div class="mt-1 font-display text-xl font-bold" :class="queue.failed ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ queue.failed }}</div>
                        </div>
                    </div>
                    <p v-if="queueStuck" class="mt-3 text-xs text-amber-700 dark:text-amber-300">Der älteste Job wartet seit {{ queue.oldestMinutes }} Minuten – läuft der Worker (<span class="font-mono">mesync-queue</span>)?</p>
                    <p v-else-if="queue.pending === 0" class="mt-3 text-xs text-gray-500">Nichts in der Warteschlange.</p>
                </section>
            </div>
        </div>

        <!-- Failed -->
        <section v-if="failedJobs.length" class="card mt-6 overflow-hidden">
            <h2 class="card-title p-6 pb-3"><ExclamationTriangleIcon class="h-5 w-5 text-red-500" />Zuletzt fehlgeschlagen</h2>
            <ul class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                <li v-for="job in failedJobs" :key="job.id" class="px-6 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ job.job }}</span>
                        <span class="text-xs text-gray-500">{{ formatDateTime(job.failed_at) }}</span>
                    </div>
                    <p class="mt-1 break-words font-mono text-xs text-red-600 dark:text-red-400">{{ job.error }}</p>
                </li>
            </ul>
        </section>
    </Layout>
</template>
