<template>
  <div class="flex justify-center mt-4">
    <nav class="flex" role="navigation" aria-label="Pagination">
      <!-- Previous Button -->
      <div class="mr-2">
        <Link
          v-if="props.pagination.prev_page_url"
          :href="props.pagination.prev_page_url"
          class="inline-flex items-center justify-center rounded-lg leading-5 px-4 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-700/60 text-primary-500 shadow-xs"
        >
          <span class="sr-only">Previous</span>
          <ChevronLeftIcon class="w-5 h-5 text-gray-600" />
        </Link>
      </div>

      <!-- Next Button -->
      <div class="ml-2">
        <Link
          v-if="props.pagination.next_page_url"
          :href="props.pagination.next_page_url"
          class="inline-flex items-center justify-center rounded-lg leading-5 px-4 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-700/60 text-primary-500 shadow-xs"
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
