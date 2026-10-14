<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
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

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);

watch(() => props.show, () => {
    if (props.show) {
        document.body.style.overflow = 'hidden';
        showSlot.value = true;
        dialog.value?.showModal();
    } else {
        document.body.style.overflow = null;
        setTimeout(() => {
            dialog.value?.close();
            showSlot.value = false;
        }, 200);
    }
});

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();

        if (props.show) {
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    return {
        'sm': 'sm:max-w-sm',
        'md': 'sm:max-w-md',
        'lg': 'sm:max-w-lg',
        'xl': 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <dialog class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent" ref="dialog">
        <div class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" scroll-region>
            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-show="show" class="fixed inset-0 transform transition-all" @click="close">
                    <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm" />
                </div>
            </transition>

            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div v-show="show" class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl transform transition-all sm:w-full sm:mx-auto dark:border-white/10 dark:bg-gray-900 dark:shadow-[0_30px_80px_-20px_rgba(0,0,0,0.8),0_0_60px_-20px_rgba(31,173,174,0.35)]" :class="maxWidthClass">
                    <template v-if="showSlot">
                        <!-- Either free content via the default slot, or the title / content / footer layout -->
                        <slot>
                            <div v-if="$slots.title || $slots.content" class="px-6 pt-6 pb-4">
                                <div v-if="$slots.title" class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
                                    <slot name="title" />
                                </div>

                                <div v-if="$slots.content" class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                                    <slot name="content" />
                                </div>
                            </div>

                            <div v-if="$slots.footer" class="flex flex-row justify-end gap-3 border-t border-gray-200 bg-gray-50/80 px-6 py-4 text-end dark:border-white/[0.06] dark:bg-white/[0.02]">
                                <slot name="footer" />
                            </div>
                        </slot>
                    </template>
                </div>
            </transition>
        </div>
    </dialog>
</template>
