<script setup>
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';

/*
 * Search field for server side lists: a spinner replaces the magnifier while results
 * load, and a button clears the search.
 */

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Suchen …' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative">
        <svg v-if="loading" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none" aria-label="Lädt">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <MagnifyingGlassIcon v-else class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />

        <input :value="modelValue" type="search" :placeholder="placeholder"
            class="form-input w-full pr-8 pl-9 sm:w-64 [&::-webkit-search-cancel-button]:hidden"
            @input="emit('update:modelValue', $event.target.value)" />

        <button v-if="modelValue" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer rounded p-0.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
            title="Suche leeren" @click="emit('update:modelValue', '')">
            <XMarkIcon class="h-4 w-4" />
        </button>
    </div>
</template>
