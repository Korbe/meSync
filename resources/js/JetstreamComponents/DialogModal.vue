<script setup>
import Modal from './Modal.vue';

const emit = defineEmits(['close']);

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        @close="close"
    >
        <div class="px-6 pt-6 pb-4">
            <div class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
                <slot name="title" />
            </div>

            <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                <slot name="content" />
            </div>
        </div>

        <div class="flex flex-row justify-end gap-3 border-t border-gray-200 bg-gray-50/80 px-6 py-4 text-end dark:border-white/[0.06] dark:bg-white/[0.02]">
            <slot name="footer" />
        </div>
    </Modal>
</template>
