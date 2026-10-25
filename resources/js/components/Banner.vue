<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
const style = ref('success');
const message = ref('');
let timeout = null;

// Success messages go away by themselves, errors stay a little longer to be read
const DURATION = { success: 4000, danger: 8000 };

const hide = () => {
    clearTimeout(timeout);
    show.value = false;
};

// Every answer brings a new flash object: also the same message twice ("Buchung wurde
// gespeichert.") shows again, and the timer starts anew on the same page (no remount there)
watch(() => page.props.flash, (flash) => {
    if (!flash?.success && !flash?.error) return;

    style.value = flash.success ? 'success' : 'danger';
    message.value = flash.success ?? flash.error;
    show.value = true;

    clearTimeout(timeout);
    timeout = setTimeout(hide, DURATION[style.value]);
}, { immediate: true });

onUnmounted(() => clearTimeout(timeout));
</script>

<template>
    <div>
        <transition leave-active-class="transition-opacity duration-300" leave-to-class="opacity-0">
        <div v-if="show && message" :class="{ 'bg-primary-500': style == 'success', 'bg-red-700': style == 'danger' }">
            <div class="max-w-screen-xl mx-auto py-2 px-3 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap">
                    <div class="w-0 flex-1 flex items-center min-w-0">
                        <span class="flex p-2 rounded-lg"
                            :class="{ 'bg-primary-500': style == 'success', 'bg-red-600': style == 'danger' }">
                            <svg v-if="style == 'success'" class="size-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>

                            <svg v-if="style == 'danger'" class="size-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </span>

                        <p class="ms-3 font-medium text-sm text-white truncate">
                            {{ message }}
                        </p>
                    </div>

                    <div class="shrink-0 sm:ms-3">
                        <button type="button" class="-me-1 flex p-2 rounded-md focus:outline-none sm:-me-2 transition"
                            :class="{ 'hover:bg-primary-500 focus:bg-primary-500': style == 'success', 'hover:bg-red-600 focus:bg-red-600': style == 'danger' }"
                            aria-label="Schließen" @click.prevent="hide">
                            <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </transition>
    </div>
</template>
