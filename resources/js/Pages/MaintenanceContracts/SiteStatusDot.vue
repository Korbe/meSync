<script setup>
import { computed } from 'vue';

const props = defineProps({
    contract: { type: Object, required: true },
});

// up | down | pending (not checked yet) | off (no URL or monitoring disabled)
const state = computed(() => {
    if (!props.contract.url || !props.contract.monitor_enabled) return 'off';
    return props.contract.monitor_status ?? 'pending';
});

const styles = {
    up: { dot: 'bg-emerald-500', ping: false, title: 'Website erreichbar' },
    down: { dot: 'bg-red-500', ping: true, title: 'Website nicht erreichbar' },
    pending: { dot: 'bg-gray-300 dark:bg-gray-600', ping: false, title: 'Noch nicht geprüft' },
    off: { dot: 'bg-transparent ring-1 ring-gray-300 dark:ring-gray-600', ping: false, title: 'Nicht überwacht' },
};
</script>

<template>
    <span class="relative inline-flex h-2.5 w-2.5 shrink-0" :title="styles[state].title">
        <span v-if="styles[state].ping" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
        <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="styles[state].dot"></span>
    </span>
</template>
