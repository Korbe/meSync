<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/*
 * Running (or paused) stopwatch, visible on every page; a click opens the stopwatch.
 */

const page = usePage();
const timer = computed(() => page.props.activeTimer);

// Server time of the last page load + the seconds since then
const loadedAt = ref(Date.now());
const now = ref(Date.now());
let ticker;
onMounted(() => ticker = setInterval(() => now.value = Date.now(), 1000));
onBeforeUnmount(() => clearInterval(ticker));

const seconds = computed(() => {
    if (!timer.value) return 0;
    const extra = timer.value.status === 'running' ? Math.floor((now.value - loadedAt.value) / 1000) : 0;
    return timer.value.elapsed_seconds + extra;
});

// New data from the server (page visit, pause, resume): count on from there
watch(timer, () => loadedAt.value = Date.now(), { deep: true });
const label = computed(() => {
    const s = seconds.value;
    const time = [Math.floor(s / 3600), Math.floor(s / 60) % 60, s % 60].map(part => String(part).padStart(2, '0'));
    return s >= 3600 ? time.join(':') : `${time[1]}:${time[2]}`;
});
</script>

<template>
    <Link v-if="timer" :href="route('stopwatch.index')"
        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 font-mono text-sm font-semibold tabular-nums ring-1 ring-inset transition"
        :class="timer.status === 'running'
            ? 'bg-primary-500/10 text-primary-600 ring-primary-500/40 hover:bg-primary-500/15 dark:text-primary-300'
            : 'bg-accent/10 text-accent ring-accent/40 hover:bg-accent/15'"
        :title="timer.customer?.display_name ? `Stoppuhr · ${timer.customer.display_name}` : 'Stoppuhr'">
        <span class="relative flex h-2 w-2">
            <span v-if="timer.status === 'running'" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary-500 opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full" :class="timer.status === 'running' ? 'bg-primary-500' : 'bg-accent'"></span>
        </span>
        {{ label }}
    </Link>
</template>
