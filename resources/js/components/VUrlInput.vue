<script setup>
import { computed, ref, watch } from 'vue';

/*
 * Web address field with a fixed "https://" prefix: only the domain is typed in.
 * Clicking the prefix switches to "http://" for the rare sites without SSL. A pasted
 * full address is split into prefix and domain. v-model is the complete URL.
 */

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: 'url' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'musterfirma.at' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const split = (value) => {
    const match = String(value ?? '').trim().match(/^(https?):\/\/(.*)$/i);
    return match ? { scheme: match[1].toLowerCase(), host: match[2] } : { scheme: 'https', host: String(value ?? '').trim() };
};

const scheme = ref(split(props.modelValue).scheme);
const host = ref(split(props.modelValue).host.replace(/\/+$/, ''));

// Never a trailing slash: "musterfirma.at/" is "musterfirma.at"
const clean = (value) => value.trim().replace(/\/+$/, '');

const full = computed(() => clean(host.value) ? `${scheme.value}://${clean(host.value)}` : '');

watch(full, (value) => emit('update:modelValue', value));

// Value changed from outside (e.g. form reset)
watch(() => props.modelValue, (value) => {
    if (value === full.value) return;
    ({ scheme: scheme.value, host: host.value } = split(value));
    host.value = clean(host.value);
});

// While typing a path ("musterfirma.at/shop") the slash must stay, so the field is tidied on blur
const onBlur = (event) => {
    host.value = clean(host.value);
    event.target.value = host.value;
};

const onInput = (event) => {
    const value = event.target.value.trim();
    if (/^https?:\/\//i.test(value)) {
        ({ scheme: scheme.value, host: host.value } = split(value));
        event.target.value = host.value;
    } else {
        host.value = value;
    }
};
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1 block text-sm font-medium">{{ label }}</label>
        <div class="flex rounded-lg shadow-xs" :class="error && 'ring-1 ring-red-300'">
            <button type="button"
                class="flex shrink-0 cursor-pointer items-center rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 px-3 font-mono text-sm transition hover:text-primary-600 dark:border-gray-700/60 dark:bg-white/5 dark:hover:text-primary-300"
                :class="scheme === 'https' ? 'text-gray-500 dark:text-gray-400' : 'text-accent'"
                :title="scheme === 'https' ? 'Klicken für http:// (ohne SSL)' : 'Klicken für https://'"
                @click="scheme = scheme === 'https' ? 'http' : 'https'">
                {{ scheme }}://
            </button>
            <input :id="id" type="text" inputmode="url" autocomplete="url" spellcheck="false"
                class="form-input w-full min-w-0 rounded-l-none" :placeholder="placeholder"
                :value="host" @input="onInput" @blur="onBlur" />
        </div>
        <div v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</div>
        <div v-else-if="scheme === 'http'" class="mt-1 text-xs text-accent">Ohne SSL – das Zertifikat wird nicht geprüft.</div>
    </div>
</template>
