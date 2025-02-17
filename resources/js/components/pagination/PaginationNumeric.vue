<template>
  <div class="flex justify-center py-5">
    <nav class="flex" role="navigation" aria-label="Pagination">
      <!-- Previous Button -->
      <div class="mr-2">
        <Link
          v-if="props.pagination.prev_page_url"
          :href="props.pagination.prev_page_url"
          class="inline-flex items-center justify-center rounded-lg leading-5 px-2.5 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-700/60 text-primary-500 shadow-xs"
        >
          <span class="sr-only">Previous</span>
          <ChevronLeftIcon class="w-5 h-5 text-gray-600" />
        </Link>
      </div>

      <!-- Pagination Links -->
      <ul class="inline-flex text-sm font-medium -space-x-px rounded-lg shadow-xs">
        <li>
          <Link v-if="props.pagination.current_page !== 1" :href="props.pagination.first_page_url" class="inline-flex items-center justify-center leading-5 px-3.5 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-700/60 rounded-l-md">
            1
          </Link>
        </li>

        <li>
          <span class="inline-flex items-center justify-center leading-5 px-3.5 py-2 bg-primary-500 text-white border border-gray-200 dark:border-gray-700/60">
            {{ props.pagination.current_page }}
          </span>
        </li>

        <li v-if="props.pagination.current_page < props.pagination.last_page - 1">
          <span class="inline-flex items-center justify-center leading-5 px-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 text-gray-400 dark:text-gray-500">...</span>
        </li>

        <li>
          <Link v-if="props.pagination.current_page !== props.pagination.last_page" :href="props.pagination.last_page_url" class="inline-flex items-center justify-center leading-5 px-3.5 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-700/60 rounded-r-md">
            {{ props.pagination.last_page }}
          </Link>
        </li>
      </ul>

      <!-- Next Button -->
      <div class="ml-2">
        <Link
          v-if="props.pagination.next_page_url"
          :href="props.pagination.next_page_url"
          class="inline-flex items-center justify-center rounded-lg leading-5 px-2.5 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-700/60 text-primary-500 shadow-xs"
        >
          <span class="sr-only">Next</span>
          <ChevronRightIcon class="w-5 h-5 text-gray-600" />
        </Link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ChevronLeftIcon } from '@heroicons/vue/24/solid';
import { ChevronRightIcon } from '@heroicons/vue/24/solid';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  pagination: Object,
});
</script>
