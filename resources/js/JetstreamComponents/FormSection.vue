<script setup>
import { computed, useSlots } from 'vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="md:grid md:grid-cols-2 md:gap-6">

        <div class="mt-5 md:mt-0 md:col-span-2 rounded-lg bg-white dark:bg-gray-800">
            <form @submit.prevent="$emit('submitted')" class="">
                <div
                    class="px-4 py-5 sm:p-6"
                >
                    <slot name="header" />

                    <div class="grid grid-cols-6 gap-6">
                        <slot name="form" />
                    </div>
                </div>

                <div v-if="hasActions" class="rounded-lg flex items-center px-4 py-3 shadow">
                    <slot name="actions" />
                </div>
            </form>
        </div>
    </div>
</template>
