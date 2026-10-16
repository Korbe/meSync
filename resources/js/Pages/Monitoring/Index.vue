<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowPathIcon, BoltIcon, CheckBadgeIcon, ExclamationTriangleIcon, GlobeAltIcon, LockClosedIcon, ShieldCheckIcon, SignalIcon, SignalSlashIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import SiteStatusDot from '@/Pages/MaintenanceContracts/SiteStatusDot.vue';
import MonitorToggle from '@/Pages/MaintenanceContracts/MonitorToggle.vue';
import { formatDateTime, timeAgo } from '@/utils/format';

const props = defineProps({
    sites: Array,
    unmonitored: Array,
    incidents: Array,
    summary: Object,
    interval: Number,
    failuresBeforeDown: Number,
});

// Refresh the figures every minute while the page is open
const refreshedAt = ref(Date.now());
const now = ref(Date.now());
const refreshing = ref(false);
const refresh = () => {
    refreshing.value = true;
    router.reload({ only: ['sites', 'unmonitored', 'incidents', 'summary'], onFinish: () => { refreshing.value = false; refreshedAt.value = Date.now(); } });
};
let refreshTimer, clockTimer;
onMounted(() => {
    refreshTimer = setInterval(refresh, 60_000);
    clockTimer = setInterval(() => now.value = Date.now(), 1_000);
});
onBeforeUnmount(() => {
    clearInterval(refreshTimer);
    clearInterval(clockTimer);
});
const secondsAgo = computed(() => Math.floor((now.value - refreshedAt.value) / 1000));

// Check one site right now
const checking = ref(null);
const checkForm = useForm({});
const checkNow = (site) => {
    checking.value = site.id;
    checkForm.post(route('contracts.check', site.id), { preserveScroll: true, onFinish: () => checking.value = null });
};

const hero = computed(() => {
    if (props.summary.total === 0) return { tone: 'idle', title: 'Noch keine Website überwacht', text: 'Schalte bei einem Wartungsvertrag mit URL die Überwachung ein.' };
    if (props.summary.down > 0) return { tone: 'down', title: props.summary.down === 1 ? '1 Website ist nicht erreichbar' : `${props.summary.down} Websites sind nicht erreichbar`, text: 'Du hast dazu eine Mail bekommen. Sobald sie wieder läuft, kommt die Entwarnung.' };
    if (props.summary.unknown === props.summary.total) return { tone: 'idle', title: 'Erste Prüfung läuft', text: `Alle ${props.interval} Minuten wird geprüft – gleich gibt es erste Werte.` };
    return { tone: 'up', title: 'Alle Websites laufen', text: `${props.summary.up} von ${props.summary.total} erreichbar, geprüft alle ${props.interval} Minuten.` };
});

const stats = computed(() => [
    { label: 'Überwacht', value: props.summary.total, icon: GlobeAltIcon },
    { label: 'Verfügbarkeit 30 Tage', value: props.summary.uptime_30d !== null ? `${formatPercent(props.summary.uptime_30d)} %` : '–', icon: CheckBadgeIcon },
    { label: 'Ø Antwortzeit 24 h', value: props.summary.avg_response_ms !== null ? `${props.summary.avg_response_ms} ms` : '–', icon: BoltIcon },
    { label: 'SSL läuft bald ab', value: props.summary.ssl_soon, icon: LockClosedIcon, warn: props.summary.ssl_soon > 0 },
]);

const formatPercent = (value) => value === null ? '–' : (value === 100 ? '100' : value.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
const uptimeClass = (value) => value === null ? 'text-gray-400' : value >= 99.9 ? 'text-primary-600 dark:text-primary-300' : value >= 98 ? 'text-accent' : 'text-red-500';

// 24h status bar
const segmentClass = (segment) => segment.up === null ? 'bg-gray-200 dark:bg-white/10'
    : segment.up >= 1 ? 'bg-primary-500'
    : segment.up > 0 ? 'bg-accent'
    : 'bg-red-500';
const segmentTitle = (segment) => {
    const time = new Date(segment.from).toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' });
    if (segment.up === null) return `${time}: keine Prüfung`;
    return `${time}: ${Math.round(segment.up * 100)} % erreichbar${segment.ms ? `, ${segment.ms} ms` : ''}`;
};

// Response time sparkline (SVG path)
const sparkline = (timeline) => {
    const points = timeline.map((segment, index) => ({ index, ms: segment.ms })).filter(point => point.ms !== null);
    if (points.length < 2) return null;
    const max = Math.max(...points.map(point => point.ms)) * 1.15;
    const coords = points.map(point => [point.index / (timeline.length - 1) * 100, 28 - point.ms / max * 26]);
    const line = coords.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
    return { line, area: `${line} L${coords.at(-1)[0].toFixed(1)},30 L${coords[0][0].toFixed(1)},30 Z` };
};

const sslClass = (days) => days === null ? 'text-gray-400' : days <= 14 ? 'text-red-500' : days <= 30 ? 'text-accent' : 'text-gray-700 dark:text-gray-300';

const duration = (minutes) => {
    if (minutes < 60) return `${Math.max(1, minutes)} Min.`;
    const hours = Math.floor(minutes / 60);
    if (hours < 48) return `${hours} Std. ${minutes % 60} Min.`;
    return `${Math.floor(hours / 24)} Tage`;
};
</script>

<template>
    <Layout title="Monitoring Center">
        <template v-slot:actions>
            <span class="hidden self-center text-xs text-gray-500 sm:inline">aktualisiert vor {{ secondsAgo }} s</span>
            <button type="button" class="glass-button" title="Jetzt aktualisieren" :disabled="refreshing" @click="refresh">
                <ArrowPathIcon class="h-5 w-5" :class="refreshing && 'animate-spin'" />
            </button>
        </template>

        <!-- Hero status -->
        <section class="card relative mb-6 overflow-hidden p-6">
            <div class="pointer-events-none absolute -top-24 -left-10 h-64 w-96 rounded-full blur-[100px]" aria-hidden="true"
                :class="hero.tone === 'down' ? 'bg-red-500/25' : hero.tone === 'up' ? 'bg-primary-500/25' : 'bg-gray-500/15'"></div>
            <div class="relative flex flex-wrap items-center gap-5">
                <div class="relative flex h-14 w-14 items-center justify-center rounded-full ring-1"
                    :class="hero.tone === 'down' ? 'bg-red-500/10 text-red-500 ring-red-500/30' : hero.tone === 'up' ? 'bg-primary-500/10 text-primary-500 ring-primary-500/30' : 'bg-gray-500/10 text-gray-400 ring-gray-500/20'">
                    <span v-if="hero.tone !== 'idle'" class="absolute inset-0 animate-ping rounded-full opacity-20" :class="hero.tone === 'down' ? 'bg-red-500' : 'bg-primary-500'"></span>
                    <ExclamationTriangleIcon v-if="hero.tone === 'down'" class="relative h-7 w-7" />
                    <ShieldCheckIcon v-else class="relative h-7 w-7" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-2xl font-bold tracking-tight" :class="hero.tone === 'down' ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ hero.title }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ hero.text }}</p>
                </div>
            </div>
        </section>

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ stat.label }}</span>
                    <component :is="stat.icon" class="h-5 w-5" :class="stat.warn ? 'text-red-500' : 'text-gray-400'" />
                </div>
                <div class="mt-1.5 font-display text-2xl font-semibold tracking-tight" :class="stat.warn ? 'text-red-500' : 'text-gray-900 dark:text-white'">{{ stat.value }}</div>
            </div>
        </div>

        <!-- Sites -->
        <div class="mb-6 grid grid-cols-1 gap-6 2xl:grid-cols-2">
            <section v-for="site in sites" :key="site.id" class="card relative overflow-hidden p-5"
                :class="site.status === 'down' && 'border-red-500/40! dark:border-red-500/40!'">
                <div v-if="site.status === 'down'" class="pointer-events-none absolute -top-16 -right-16 h-44 w-44 rounded-full bg-red-500/20 blur-[70px]" aria-hidden="true"></div>

                <div class="relative">
                    <div class="flex items-start gap-3">
                        <SiteStatusDot :contract="{ url: site.url, monitor_enabled: true, monitor_status: site.status }" class="mt-1.5" />
                        <div class="min-w-0 flex-1">
                            <Link :href="route('contracts.edit', site.id)" class="block truncate font-display text-lg font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-300">{{ site.name }}</Link>
                            <div class="flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                                <a :href="site.url" target="_blank" rel="noopener" class="truncate hover:text-primary-500">{{ site.url.replace(/^https?:\/\//, '') }}</a>
                                <span v-if="site.customer">· {{ site.customer }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 transition hover:bg-primary-500/10 hover:text-primary-600 disabled:opacity-50 dark:hover:text-primary-300"
                                title="Jetzt prüfen" :disabled="checking !== null" @click="checkNow(site)">
                                <ArrowPathIcon class="h-4 w-4" :class="checking === site.id && 'animate-spin'" />
                            </button>
                            <MonitorToggle :contract="site" :label="false" />
                        </div>
                    </div>

                    <!-- Current state -->
                    <div class="mt-3 text-sm">
                        <span v-if="site.status === 'up'" class="font-medium text-primary-600 dark:text-primary-300">Erreichbar</span>
                        <span v-else-if="site.status === 'down'" class="font-semibold text-red-500">Nicht erreichbar</span>
                        <span v-else class="font-medium text-gray-500">Noch nicht geprüft</span>
                        <span v-if="site.status_since" class="text-gray-500"> seit {{ formatDateTime(site.status_since) }}</span>
                        <span v-if="site.status !== 'down' && site.failures > 0" class="ml-2 text-xs text-accent">({{ site.failures }} Fehlversuch – Alarm ab {{ failuresBeforeDown }})</span>
                    </div>
                    <p v-if="site.error" class="mt-2 truncate rounded-lg bg-red-500/10 px-3 py-1.5 font-mono text-xs text-red-700 ring-1 ring-red-500/25 dark:text-red-300" :title="site.error">{{ site.error }}</p>

                    <!-- 24h bar -->
                    <div class="mt-4">
                        <div class="flex h-7 gap-[2px]">
                            <div v-for="(segment, index) in site.timeline" :key="index" class="flex-1 rounded-[2px] transition hover:opacity-70" :class="segmentClass(segment)" :title="segmentTitle(segment)"></div>
                        </div>
                        <div class="mt-1 flex justify-between font-mono text-[10px] text-gray-400">
                            <span>vor 24 Std.</span><span>jetzt</span>
                        </div>
                    </div>

                    <!-- Figures -->
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-white/[0.03]">
                            <div class="font-mono text-[10px] uppercase tracking-[0.15em] text-gray-500">Verfügbarkeit</div>
                            <div class="mt-1 flex gap-2 text-xs tabular-nums">
                                <span v-for="(value, period) in site.uptime" :key="period" :title="`Letzte ${period}`">
                                    <span class="text-gray-400">{{ period }}</span>
                                    <span class="ml-0.5 font-semibold" :class="uptimeClass(value)">{{ formatPercent(value) }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="relative overflow-hidden rounded-xl bg-gray-50 p-2.5 dark:bg-white/[0.03]">
                            <div class="font-mono text-[10px] uppercase tracking-[0.15em] text-gray-500">Antwortzeit</div>
                            <div class="relative z-10 mt-1 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                {{ site.response_ms ? `${site.response_ms} ms` : '–' }}
                                <span v-if="site.avg_response_ms" class="text-[10px] font-normal text-gray-500">Ø {{ site.avg_response_ms }}</span>
                            </div>
                            <svg v-if="sparkline(site.timeline)" viewBox="0 0 100 30" preserveAspectRatio="none" class="absolute inset-x-0 bottom-0 h-8 w-full opacity-70">
                                <path :d="sparkline(site.timeline).area" class="fill-primary-500/15" />
                                <path :d="sparkline(site.timeline).line" class="fill-none stroke-primary-500" stroke-width="1.2" vector-effect="non-scaling-stroke" />
                            </svg>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-white/[0.03]">
                            <div class="font-mono text-[10px] uppercase tracking-[0.15em] text-gray-500">SSL</div>
                            <div class="mt-1 text-sm font-semibold tabular-nums" :class="sslClass(site.ssl_days)">
                                {{ site.ssl_days === null ? (site.url.startsWith('https') ? '–' : 'kein HTTPS') : `${site.ssl_days} Tage` }}
                            </div>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-white/[0.03]">
                            <div class="font-mono text-[10px] uppercase tracking-[0.15em] text-gray-500">Geprüft</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ site.checked_at ? timeAgo(site.checked_at) : '–' }}</div>
                        </div>
                    </div>
                    <p v-if="site.incidents_30d" class="mt-3 text-xs text-gray-500">{{ site.incidents_30d }} {{ site.incidents_30d === 1 ? 'Ausfall' : 'Ausfälle' }} in den letzten 30 Tagen</p>
                </div>
            </section>
        </div>

        <!-- Outages -->
        <section class="card mb-6 overflow-hidden">
            <div class="p-6 pb-3">
                <h2 class="card-title"><SignalSlashIcon class="h-5 w-5 text-primary-500" />Ausfälle der letzten 30 Tage</h2>
                <p class="mt-1 text-sm text-gray-500">Ab {{ failuresBeforeDown }} fehlgeschlagenen Prüfungen in Folge gilt eine Website als ausgefallen.</p>
            </div>
            <p v-if="incidents.length === 0" class="flex items-center gap-2 px-6 pb-6 text-sm text-primary-600 dark:text-primary-300">
                <CheckBadgeIcon class="h-5 w-5" />Keine Ausfälle – alles stabil.
            </p>
            <table v-else class="w-full text-left text-sm">
                <thead class="border-y border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                    <tr>
                        <th class="px-6 py-2.5 font-medium">Website</th>
                        <th class="px-6 py-2.5 font-medium">Beginn</th>
                        <th class="hidden px-6 py-2.5 font-medium sm:table-cell">Ende</th>
                        <th class="px-6 py-2.5 text-right font-medium">Dauer</th>
                        <th class="hidden px-6 py-2.5 font-medium lg:table-cell">Fehler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <tr v-for="incident in incidents" :key="`${incident.contract_id}-${incident.started_at}`">
                        <td class="px-6 py-2.5">
                            <Link :href="route('contracts.edit', incident.contract_id)" class="font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-300">{{ incident.name }}</Link>
                        </td>
                        <td class="px-6 py-2.5 text-gray-600 dark:text-gray-400">{{ formatDateTime(incident.started_at) }}</td>
                        <td class="hidden px-6 py-2.5 sm:table-cell">
                            <span v-if="incident.ended_at" class="text-gray-600 dark:text-gray-400">{{ formatDateTime(incident.ended_at) }}</span>
                            <span v-else class="inline-flex items-center gap-1.5 font-semibold text-red-500"><span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span>läuft noch</span>
                        </td>
                        <td class="px-6 py-2.5 text-right font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ duration(incident.minutes) }}</td>
                        <td class="hidden max-w-xs truncate px-6 py-2.5 font-mono text-xs text-gray-500 lg:table-cell" :title="incident.error">{{ incident.error ?? '–' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Not monitored -->
        <section v-if="unmonitored.length" class="card overflow-hidden">
            <div class="p-6 pb-3">
                <h2 class="card-title"><SignalIcon class="h-5 w-5 text-gray-400" />Nicht überwacht</h2>
                <p class="mt-1 text-sm text-gray-500">Wartungsverträge mit URL, bei denen die Überwachung aus ist.</p>
            </div>
            <div v-for="site in unmonitored" :key="site.id" class="flex items-center gap-3 border-t border-gray-100 px-6 py-3 dark:border-white/[0.06]">
                <div class="min-w-0 flex-1">
                    <Link :href="route('contracts.edit', site.id)" class="font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-300">{{ site.name }}</Link>
                    <div class="truncate text-xs text-gray-500">{{ site.url }}<template v-if="site.customer"> · {{ site.customer }}</template></div>
                </div>
                <span v-if="site.ended" class="text-xs text-gray-400">Vertrag beendet</span>
                <MonitorToggle v-else :contract="site" />
            </div>
        </section>
    </Layout>
</template>
