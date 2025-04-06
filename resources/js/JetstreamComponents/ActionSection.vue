<script setup>
</script>

<template>
    <div class="mt-5 md:mt-0">

        <div class="px-4 py-5 sm:p-6 bg-white dark:bg-gray-800 shadow rounded-lg">

            <slot name="header" />

            <slot name="content" />
        </div>
    </div>
</template>
