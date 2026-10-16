<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    contract: { type: Object, required: true },   // needs id, monitor_enabled
    label: { type: Boolean, default: true },
});

const busy = ref(false);

const toggle = () => {
    busy.value = true;
    router.patch(route('contracts.monitor', props.contract.id), { enabled: !props.contract.monitor_enabled }, {
        preserveScroll: true,
        onFinish: () => busy.value = false,
    });
};
</script>

<template>
    <button type="button" role="switch" :aria-checked="contract.monitor_enabled" :disabled="busy"
        class="group inline-flex cursor-pointer items-center gap-2 text-sm disabled:cursor-wait disabled:opacity-60"
        :title="contract.monitor_enabled ? 'Überwachung ausschalten' : 'Überwachung einschalten'"
        @click.stop="toggle">
        <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition"
            :class="contract.monitor_enabled ? 'bg-primary-500 shadow-[0_0_12px_-2px_rgba(31,173,174,0.8)]' : 'bg-gray-300 dark:bg-white/15'">
            <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition" :class="contract.monitor_enabled ? 'translate-x-4.5' : 'translate-x-0.5'"></span>
        </span>
        <span v-if="label" class="font-medium text-gray-700 dark:text-gray-300">{{ contract.monitor_enabled ? 'Überwachung an' : 'Überwachung aus' }}</span>
    </button>
</template>
