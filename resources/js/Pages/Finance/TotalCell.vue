<script setup>
import { formatMoney } from '@/utils/format';

defineProps({
    actual: { type: Number, default: 0 },   // really booked
    total: { type: Number, default: 0 },    // incl. open and coming months
    strong: { type: Boolean, default: false },
});

const plain = (value) => value ? formatMoney(value).replace(/\s?€/, '') : '';
</script>

<template>
    <span class="tabular-nums" :class="strong ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-800 dark:text-gray-100'">{{ plain(actual) || (total ? '0,00' : '') }}</span>
    <span v-if="Math.abs(total - actual) >= 0.005" class="block text-[10px] font-normal text-gray-400" title="Inklusive noch nicht bestätigter und kommender Monate">
        inkl. Plan {{ plain(total) }}
    </span>
</template>
