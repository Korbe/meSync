<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="cursor-pointer inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-950 disabled:cursor-not-allowed disabled:opacity-50 border border-transparent bg-primary-500 text-white hover:bg-primary-600 active:bg-primary-700 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)] focus:ring-primary-500">
        <slot />
    </button>
</template>
