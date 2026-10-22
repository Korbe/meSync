<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const badges = {
    overdue: { label: 'Überfällig', class: 'bg-red-500/10 text-red-600 ring-red-500/30 dark:text-red-400', dot: 'bg-red-500' },
    due_today: { label: 'Heute fällig', class: 'bg-accent text-gray-950 ring-accent font-semibold shadow-[0_0_14px_-2px_rgba(245,165,36,0.8)]', dot: 'bg-gray-950' },
    due_soon: { label: 'Bald fällig', class: 'bg-accent/10 text-amber-700 ring-accent/40 dark:text-accent', dot: 'bg-accent' },
    active: { label: 'Aktiv', class: 'bg-primary-500/10 text-primary-700 ring-primary-500/30 dark:text-primary-300', dot: 'bg-primary-500' },
    pending: { label: 'Wartet auf Go-Live', class: 'bg-accent/10 text-amber-700 ring-accent/40 dark:text-accent', dot: 'bg-accent' },
    ended: { label: 'Beendet', class: 'bg-gray-500/10 text-gray-600 ring-gray-500/25 dark:text-gray-400', dot: 'bg-gray-400' },
};

const badge = computed(() => badges[props.status] ?? badges.active);
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="badge.class">
        <span class="relative flex h-1.5 w-1.5">
            <span v-if="['overdue', 'due_today'].includes(status)" class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" :class="badge.dot"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="badge.dot"></span>
        </span>
        {{ badge.label }}
    </span>
</template>
