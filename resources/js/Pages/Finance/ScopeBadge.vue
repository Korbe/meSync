<script setup>
defineProps({
    scope: { type: String, required: true },
});
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
        :class="scope === 'business'
            ? 'bg-primary-500/10 text-primary-600 ring-primary-500/30 dark:text-primary-300'
            : 'bg-accent/10 text-accent ring-accent/30'">
        {{ scope === 'business' ? 'Firma' : 'Privat' }}
    </span>
</template>
