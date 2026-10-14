<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="cursor-pointer inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-950 disabled:cursor-not-allowed disabled:opacity-50 border border-transparent bg-red-600 text-white hover:bg-red-500 active:bg-red-700 hover:shadow-[0_8px_24px_-8px_rgba(239,68,68,0.7)] focus:ring-red-500">
        <slot />
    </button>
</template>
