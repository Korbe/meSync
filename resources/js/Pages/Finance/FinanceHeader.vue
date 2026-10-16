<script setup>
import { router } from '@inertiajs/vue3';
import { BuildingOffice2Icon, HomeIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    active: { type: String, required: true },   // overview | entries | contracts
    scope: { type: String, default: 'all' },
    // Extra query parameters to keep when switching the scope, e.g. { year: 2026 }
    query: { type: Object, default: () => ({}) },
});

const tabs = [
    { key: 'overview', label: 'Übersicht', route: 'finance.index' },
    { key: 'entries', label: 'Buchungen', route: 'finance.entries.index' },
    { key: 'contracts', label: 'Verträge & Fixkosten', route: 'finance.contracts.index' },
];

const scopes = [
    { value: 'all', label: 'Alle', icon: Squares2X2Icon },
    { value: 'private', label: 'Privat', icon: HomeIcon },
    { value: 'business', label: 'Firma', icon: BuildingOffice2Icon },
];

const tabQuery = () => props.scope === 'all' ? {} : { scope: props.scope };

const setScope = (scope) => {
    const tab = tabs.find(tab => tab.key === props.active);
    router.get(route(tab.route), { ...props.query, ...(scope === 'all' ? {} : { scope }) }, { preserveScroll: true, preserveState: tab.key === 'contracts' });
};
</script>

<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex gap-1 rounded-xl bg-gray-100 p-1 text-sm dark:bg-white/5">
            <Link v-for="tab in tabs" :key="tab.key" :href="route(tab.route, tabQuery())"
                class="rounded-lg px-3.5 py-1.5 font-medium transition"
                :class="active === tab.key ? 'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'">
                {{ tab.label }}
            </Link>
        </nav>

        <div class="flex gap-1 rounded-xl p-1 ring-1 ring-gray-200 ring-inset dark:ring-white/10">
            <button v-for="option in scopes" :key="option.value" type="button"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="scope === option.value
                    ? (option.value === 'private' ? 'bg-accent/15 text-accent' : option.value === 'business' ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : 'bg-gray-900 text-white dark:bg-white dark:text-gray-900')
                    : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'"
                @click="setScope(option.value)">
                <component :is="option.icon" class="h-4 w-4" />{{ option.label }}
            </button>
        </div>
    </div>
</template>
