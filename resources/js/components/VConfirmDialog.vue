<script setup>
import { ExclamationTriangleIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import VModal from './VModal.vue';
import VButton from './VButton.vue';
import { confirmState, settleConfirm } from '@/composables/useConfirm';
</script>

<template>
    <!-- Single app-wide confirm dialog, opened via confirm() from @/composables/useConfirm -->
    <VModal :show="confirmState.show" max-width="md" @close="settleConfirm(false)">
        <div class="px-6 pt-6 pb-5">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full ring-1"
                    :class="confirmState.variant === 'danger'
                        ? 'bg-red-500/10 text-red-500 ring-red-500/30'
                        : 'bg-primary-500/10 text-primary-500 ring-primary-500/30'">
                    <ExclamationTriangleIcon v-if="confirmState.variant === 'danger'" class="h-6 w-6" />
                    <QuestionMarkCircleIcon v-else class="h-6 w-6" />
                </div>
                <div class="min-w-0 pt-1">
                    <h3 class="font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white">{{ confirmState.title }}</h3>
                    <p v-if="confirmState.message" class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ confirmState.message }}</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50/80 px-6 py-4 dark:border-white/[0.06] dark:bg-white/[0.02]">
            <VButton variant="secondary" @click="settleConfirm(false)">{{ confirmState.cancelText }}</VButton>
            <VButton :variant="confirmState.variant" @click="settleConfirm(true)">{{ confirmState.confirmText }}</VButton>
        </div>
    </VModal>
</template>
