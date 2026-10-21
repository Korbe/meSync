<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { MagnifyingGlassIcon, MapPinIcon } from '@heroicons/vue/24/outline';

/*
 * Type an address, pick a suggestion: emits street, zip, city and country to fill the form.
 */

defineProps({
    id: { type: String, default: 'address_search' },
});

const emit = defineEmits(['select']);

const query = ref('');
const suggestions = ref([]);
const active = ref(-1);
const loading = ref(false);
const open = ref(false);

let timer = null;
let request = 0;

watch(query, (value) => {
    clearTimeout(timer);
    if (value.trim().length < 3) {
        suggestions.value = [];
        open.value = false;
        return;
    }

    timer = setTimeout(async () => {
        const current = ++request;
        loading.value = true;
        try {
            const { data } = await axios.get(route('address.search'), { params: { q: value } });
            if (current !== request) return;
            suggestions.value = data;
            active.value = data.length ? 0 : -1;
            open.value = true;
        } catch {
            suggestions.value = [];
        } finally {
            if (current === request) loading.value = false;
        }
    }, 300);
});

const line = (address) => [address.street, [address.zip, address.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');

const choose = (address) => {
    emit('select', address);
    query.value = '';
    suggestions.value = [];
    open.value = false;
};

// Enter picks the highlighted suggestion instead of submitting the surrounding form
const onEnter = (event) => {
    if (!open.value || active.value < 0) return;
    event.preventDefault();
    event.stopPropagation();
    choose(suggestions.value[active.value]);
};

const move = (step) => {
    if (!suggestions.value.length) return;
    open.value = true;
    active.value = (active.value + step + suggestions.value.length) % suggestions.value.length;
};
</script>

<template>
    <div class="relative">
        <label :for="id" class="mb-1 block text-sm font-medium">Adresse suchen</label>
        <div class="relative">
            <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input :id="id" v-model="query" type="text" autocomplete="off" spellcheck="false"
                class="form-input w-full pl-9" placeholder="z. B. Hauptplatz 1 Villach"
                role="combobox" :aria-expanded="open" :aria-controls="`${id}_list`"
                @keydown.enter="onEnter" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                @keydown.esc="open = false" @focus="open = suggestions.length > 0" @blur="open = false" />
            <span v-if="loading" class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-primary-500 border-t-transparent" />
        </div>

        <ul v-if="open" :id="`${id}_list`" role="listbox"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900">
            <li v-if="suggestions.length === 0" class="px-3 py-2.5 text-sm text-gray-500">Keine Adresse gefunden – einfach unten eintragen.</li>
            <li v-for="(address, index) in suggestions" :key="index" role="option" :aria-selected="index === active"
                class="flex cursor-pointer items-start gap-2 px-3 py-2.5 text-sm"
                :class="index === active ? 'bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/5'"
                @mousedown.prevent="choose(address)" @mouseenter="active = index">
                <MapPinIcon class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" />
                <span class="min-w-0">
                    <span class="block truncate text-gray-900 dark:text-gray-100">{{ line(address) }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ [address.name, address.country].filter(Boolean).join(' · ') }}</span>
                </span>
            </li>
        </ul>
        <p class="mt-1 text-xs text-gray-500">Vorschlag wählen – Straße, PLZ, Ort und Land werden ausgefüllt.</p>
    </div>
</template>
