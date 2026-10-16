<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

/*
 * Pagination bar for a Laravel paginator: "1–25 von 73", page numbers and the choice
 * of entries per page. Keeps the other query parameters (search, filters, year).
 */

const props = defineProps({
    paginator: { type: Object, required: true },   // current_page, last_page, from, to, total, per_page
    options: { type: Array, default: () => [10, 25, 100] },
    label: { type: String, default: 'Einträgen' },
});

const visit = (params) => {
    const query = Object.fromEntries(new URLSearchParams(window.location.search));
    router.get(window.location.pathname, { ...query, ...params }, { preserveState: true, preserveScroll: false, replace: true });
};

const goTo = (page) => {
    if (page < 1 || page > props.paginator.last_page || page === props.paginator.current_page) return;
    visit({ page });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const setPerPage = (perPage) => visit({ per_page: perPage, page: 1 });

// 1 … 4 5 [6] 7 8 … 20
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.paginator;
    const set = new Set([1, last, current, current - 1, current + 1, current - 2, current + 2].filter(page => page >= 1 && page <= last));
    const sorted = [...set].sort((a, b) => a - b);

    return sorted.flatMap((page, index) => index > 0 && page - sorted[index - 1] > 1 ? ['…', page] : [page]);
});
</script>

<template>
    <div v-if="paginator.total > options[0]" class="flex flex-wrap items-center justify-between gap-3 px-1 py-4 text-sm">
        <div class="text-gray-500">
            <span class="font-medium text-gray-900 tabular-nums dark:text-gray-100">{{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }}</span>
            von <span class="font-medium text-gray-900 tabular-nums dark:text-gray-100">{{ paginator.total }}</span> {{ label }}
        </div>

        <nav v-if="paginator.last_page > 1" class="flex items-center gap-1" aria-label="Seiten">
            <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 disabled:cursor-default disabled:opacity-30 dark:hover:bg-white/10 dark:hover:text-white"
                :disabled="paginator.current_page === 1" title="Vorherige Seite" @click="goTo(paginator.current_page - 1)">
                <ChevronLeftIcon class="h-4 w-4" />
            </button>
            <template v-for="(page, index) in pages" :key="`${page}-${index}`">
                <span v-if="page === '…'" class="px-1 text-gray-400">…</span>
                <button v-else type="button"
                    class="min-w-8 cursor-pointer rounded-lg px-2 py-1 font-medium tabular-nums transition"
                    :class="page === paginator.current_page
                        ? 'bg-primary-500 text-white shadow-[0_6px_16px_-6px_rgba(31,173,174,0.8)]'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white'"
                    :aria-current="page === paginator.current_page ? 'page' : undefined"
                    @click="goTo(page)">
                    {{ page }}
                </button>
            </template>
            <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 disabled:cursor-default disabled:opacity-30 dark:hover:bg-white/10 dark:hover:text-white"
                :disabled="paginator.current_page === paginator.last_page" title="Nächste Seite" @click="goTo(paginator.current_page + 1)">
                <ChevronRightIcon class="h-4 w-4" />
            </button>
        </nav>

        <div class="flex items-center gap-2 text-gray-500">
            <span class="hidden sm:inline">Pro Seite</span>
            <div class="flex gap-0.5 rounded-lg p-0.5 ring-1 ring-gray-200 ring-inset dark:ring-white/10">
                <button v-for="option in options" :key="option" type="button"
                    class="cursor-pointer rounded-md px-2 py-0.5 text-xs font-medium tabular-nums transition"
                    :class="paginator.per_page === option ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'hover:text-gray-900 dark:hover:text-white'"
                    @click="setPerPage(option)">
                    {{ option }}
                </button>
            </div>
        </div>
    </div>
</template>
