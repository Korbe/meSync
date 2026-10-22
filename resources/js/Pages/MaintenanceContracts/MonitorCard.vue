<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { SignalIcon, LockClosedIcon, BoltIcon } from '@heroicons/vue/24/outline';
import VButton from '@/components/VButton.vue';
import SiteStatusDot from './SiteStatusDot.vue';
import MonitorToggle from './MonitorToggle.vue';
import { formatDateTime, timeAgo, daysUntilTimestamp } from '@/utils/format';

const props = defineProps({
    contract: { type: Object, required: true },   // with its monitor (null without URL)
});

const monitor = computed(() => props.contract.monitor);

const form = useForm({});
const checkNow = () => form.post(route('monitors.check', monitor.value.id), { preserveScroll: true });

const sslDays = computed(() => monitor.value?.ssl_expires_at ? daysUntilTimestamp(monitor.value.ssl_expires_at) : null);

const statusText = computed(() => ({
    up: 'Erreichbar',
    down: 'Nicht erreichbar',
}[monitor.value?.status] ?? 'Noch nicht geprüft'));
</script>

<template>
    <section class="card relative overflow-hidden p-6">
        <div v-if="monitor?.status === 'down'" class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-red-500/20 blur-[80px]" aria-hidden="true"></div>

        <div class="relative">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="card-title"><SignalIcon class="h-5 w-5 text-primary-500" />Monitoring</h2>
                <div class="flex flex-wrap items-center gap-4">
                    <MonitorToggle v-if="monitor" :monitor="monitor" />
                    <VButton v-if="monitor?.enabled" variant="secondary" :loading="form.processing" :disabled="form.processing" @click="checkNow">Jetzt prüfen</VButton>
                    <Link :href="route('monitoring.index')" class="text-xs text-gray-500 underline hover:text-primary-500">Monitoring Center</Link>
                </div>
            </div>

            <p v-if="!monitor" class="mt-4 text-sm text-gray-500">Keine URL hinterlegt. Trag unten im Formular eine URL ein, dann kannst du die Website überwachen.</p>
            <p v-else-if="!monitor.enabled" class="mt-4 text-sm text-gray-500">Die Überwachung ist ausgeschaltet. Schalte sie oben ein, dann wird {{ contract.url }} alle 5 Minuten geprüft und du bekommst bei einem Ausfall eine Mail.</p>

            <template v-else>
                <div class="mt-5 flex items-center gap-3">
                    <SiteStatusDot :site="monitor" />
                    <span class="font-display text-xl font-bold"
                        :class="monitor.status === 'down' ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ statusText }}</span>
                    <span v-if="monitor.status_since" class="text-sm text-gray-500">seit {{ formatDateTime(monitor.status_since) }}</span>
                </div>
                <p v-if="monitor.error" class="mt-2 rounded-lg bg-red-500/10 px-3 py-2 font-mono text-xs text-red-700 ring-1 ring-red-500/25 dark:text-red-300">{{ monitor.error }}</p>

                <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                        <dt class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Zuletzt geprüft</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ monitor.checked_at ? timeAgo(monitor.checked_at) : '–' }}</dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                        <dt class="flex items-center gap-1 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500"><BoltIcon class="h-3.5 w-3.5" />Antwortzeit</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ monitor.response_ms ? `${monitor.response_ms} ms` : '–' }}</dd>
                    </div>
                    <div class="rounded-xl p-3" :class="sslDays !== null && sslDays < 14 ? 'bg-red-500/10 ring-1 ring-red-500/25' : 'bg-gray-50 dark:bg-white/[0.03]'">
                        <dt class="flex items-center gap-1 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500"><LockClosedIcon class="h-3.5 w-3.5" />SSL gültig bis</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                            <template v-if="monitor.ssl_expires_at">{{ formatDateTime(monitor.ssl_expires_at).split(',')[0] }} <span class="text-xs text-gray-500">({{ sslDays }} Tage)</span></template>
                            <template v-else>–</template>
                        </dd>
                    </div>
                </dl>
            </template>
        </div>
    </section>
</template>
