<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const badges = {
    paid: { label: 'Bezahlt', class: 'bg-primary-500/10 text-primary-700 ring-primary-500/30 dark:text-primary-300', dot: 'bg-primary-500' },
    partial: { label: 'Teilweise bezahlt', class: 'bg-accent/10 text-amber-700 ring-accent/40 dark:text-accent', dot: 'bg-accent' },
    open: { label: 'Offen', class: 'bg-gray-500/10 text-gray-700 ring-gray-500/25 dark:text-gray-300', dot: 'bg-gray-400' },
    overdue: { label: 'Überfällig', class: 'bg-red-500/10 text-red-600 ring-red-500/30 dark:text-red-400', dot: 'bg-red-500' },
    cancelled: { label: 'Storniert', class: 'bg-gray-500/10 text-gray-500 ring-gray-500/20', dot: 'bg-gray-300' },
};

const badge = computed(() => badges[props.status] ?? badges.open);
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="badge.class">
        <span class="relative flex h-1.5 w-1.5">
            <span v-if="status === 'overdue'" class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" :class="badge.dot"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="badge.dot"></span>
        </span>
        {{ badge.label }}
    </span>
</template>
