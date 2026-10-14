<script setup>
import { computed, useSlots } from 'vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <section class="card overflow-hidden">
        <form @submit.prevent="$emit('submitted')">
            <div class="p-6">
                <slot name="header" />

                <div class="grid grid-cols-6 gap-5">
                    <slot name="form" />
                </div>
            </div>

            <div v-if="hasActions" class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50/60 px-6 py-4 dark:border-white/[0.06] dark:bg-white/[0.02]">
                <slot name="actions" />
            </div>
        </form>
    </section>
</template>
