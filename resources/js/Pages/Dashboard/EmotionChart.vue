<template>
  <div class="flex flex-col col-span-full sm:col-span-6 xl:col-span-4 bg-white dark:bg-gray-800 shadow-xs rounded-xl">
    <div class="px-5 pt-5">
      <header class="flex justify-between items-start mb-2">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Allgemeinzustand</h2>
      </header>
      <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase mb-1">Durchschnittlicher Score aus {{ dataLength }} Einträgen</div>
      <div class="flex items-start">
        <div class="text-3xl font-bold text-gray-800 dark:text-gray-100 mr-2">{{ score }}</div>
      </div>
    </div>

    <!-- Chart built with Chart.js 3 -->
    <div class="grow max-sm:max-h-[128px] xl:max-h-[128px]">
      <LineChart :labels="labels" :data="data" :width="389" :height="200" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import LineChart from '@/components/Charts/LineChart.vue'

const props = defineProps({
  score: {
    type: Number,
    default: 0,
  },
  labels: {
    type: Array,
    default: () => [],
  },
  data: {
    type: Array,
    default: () => [],
  },
})

const dataLength = computed(() => props.data.length)

</script>
