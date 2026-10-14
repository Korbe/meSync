<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'" class="group flex flex-col items-center gap-4">
        <img class="size-36 drop-shadow-[0_0_40px_rgba(31,173,174,0.55)] transition-transform duration-300 group-hover:scale-105 sm:size-44" src="/images/logo/logo-512x512-dark.png?v=2" alt="meSync">
        <span class="font-display text-3xl font-bold tracking-tight text-white">me<span class="text-primary-400">Sync</span></span>
    </Link>
</template>
