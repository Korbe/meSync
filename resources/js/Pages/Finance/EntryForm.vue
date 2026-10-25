<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import VButton from '@/components/VButton.vue';
import { formatMoney, today } from '@/utils/format';
import { isFormula, parseAmount } from '@/utils/amount';

const props = defineProps({
    entry: { type: Object, default: null },
    categories: Array,
    suggestions: { type: Array, default: () => [] },
    // Defaults for new entries
    scope: { type: String, default: 'private' },
    month: { type: String, default: null },   // YYYY-MM: default date lies in this month
    // Unique field ids: the edit dialog sits on the same page as the entry form
    idPrefix: { type: String, default: 'entry' },
});

const emit = defineEmits(['saved', 'cancel']);

const defaultCategory = (type, scope) => props.categories.find(category => category.type === type && category.scopes.includes(scope))?.value ?? '';

// New entries default to today, or the first of the shown month when looking at another month
const defaultDate = () => !props.month || today().startsWith(props.month) ? today() : `${props.month}-01`;

const form = useForm({
    date: props.entry?.date?.substring(0, 10) ?? defaultDate(),
    description: props.entry?.description ?? '',
    type: props.entry?.type ?? 'expense',
    scope: props.entry?.scope ?? (props.scope === 'business' ? 'business' : 'private'),
    category: props.entry?.category ?? defaultCategory('expense', props.scope === 'business' ? 'business' : 'private'),
    amount: props.entry ? (props.entry.amount_input ?? String(Number(props.entry.amount)).replace('.', ',')) : '',
    notes: props.entry?.notes ?? '',
});

// Categories of the chosen type, the typical ones for the scope first
const categoryOptions = computed(() => {
    const ofType = props.categories.filter(category => category.type === form.type);
    return [
        { label: form.scope === 'business' ? 'Firma' : 'Privat', options: ofType.filter(category => category.scopes.includes(form.scope)) },
        { label: 'Weitere', options: ofType.filter(category => !category.scopes.includes(form.scope)) },
    ].filter(group => group.options.length);
});

watch(() => [form.type, form.scope], () => {
    const current = props.categories.find(category => category.value === form.category);
    if (!current || current.type !== form.type) form.category = defaultCategory(form.type, form.scope);
});

// Picking a known description fills in its last category, scope and type
const suggestionList = computed(() => props.suggestions.filter(suggestion => !form.type || suggestion.type === form.type));
watch(() => form.description, (description) => {
    if (props.entry) return;
    const match = props.suggestions.find(suggestion => suggestion.description.toLowerCase() === description.trim().toLowerCase());
    if (match) Object.assign(form, { type: match.type, scope: match.scope, category: match.category });
});

const parsed = computed(() => parseAmount(form.amount));
const showSum = computed(() => isFormula(form.amount) && parsed.value !== null);

const descriptionInput = ref(null);

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            if (!props.entry) {
                form.reset('description', 'amount', 'notes');
                nextTick(() => descriptionInput.value?.focus());
            }
        },
    };

    props.entry ? form.put(route('finance.entries.update', props.entry.id), options) : form.post(route('finance.entries.store'), options);
};

const toggleClass = (active, tone) => active
    ? (tone === 'business' ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : tone === 'private' ? 'bg-accent/15 text-accent' : tone === 'income' ? 'bg-primary-500 text-white' : 'bg-gray-900 text-white dark:bg-white dark:text-gray-900')
    : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200';
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <div class="flex gap-1 rounded-xl p-1 text-sm ring-1 ring-gray-200 ring-inset dark:ring-white/10">
                <button v-for="option in [{ value: 'expense', label: 'Ausgabe' }, { value: 'income', label: 'Einnahme' }]" :key="option.value" type="button"
                    class="cursor-pointer rounded-lg px-3 py-1 font-medium transition" :class="toggleClass(form.type === option.value, option.value)"
                    @click="form.type = option.value">{{ option.label }}</button>
            </div>
            <div class="flex gap-1 rounded-xl p-1 text-sm ring-1 ring-gray-200 ring-inset dark:ring-white/10">
                <button v-for="option in [{ value: 'private', label: 'Privat' }, { value: 'business', label: 'Firma' }]" :key="option.value" type="button"
                    class="cursor-pointer rounded-lg px-3 py-1 font-medium transition" :class="toggleClass(form.scope === option.value, option.value)"
                    @click="form.scope = option.value">{{ option.label }}</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-12">
            <div class="col-span-2 md:col-span-4">
                <label :for="`${idPrefix}-description`" class="mb-1 block text-sm font-medium">Wofür</label>
                <input ref="descriptionInput" :id="`${idPrefix}-description`" v-model="form.description" :list="`${idPrefix}-suggestions`" class="form-input w-full" placeholder="z. B. Hofer, Tanken, Adobe" autocomplete="off" required />
                <datalist :id="`${idPrefix}-suggestions`">
                    <option v-for="suggestion in suggestionList" :key="suggestion.description" :value="suggestion.description" />
                </datalist>
                <div v-if="form.errors.description" class="mt-1 text-xs text-red-500">{{ form.errors.description }}</div>
            </div>
            <div class="col-span-1 md:col-span-2">
                <label :for="`${idPrefix}-amount`" class="mb-1 block text-sm font-medium">Betrag (€)</label>
                <input :id="`${idPrefix}-amount`" v-model="form.amount" inputmode="decimal" class="form-input w-full" placeholder="12,50" autocomplete="off" required />
                <div v-if="form.errors.amount" class="mt-1 text-xs text-red-500">{{ form.errors.amount }}</div>
                <div v-else-if="showSum" class="mt-1 text-xs font-medium text-primary-600 dark:text-primary-300">= {{ formatMoney(parsed) }}</div>
            </div>
            <div class="col-span-1 md:col-span-2">
                <label :for="`${idPrefix}-date`" class="mb-1 block text-sm font-medium">Datum</label>
                <input :id="`${idPrefix}-date`" v-model="form.date" type="date" class="form-input w-full" required />
                <div v-if="form.errors.date" class="mt-1 text-xs text-red-500">{{ form.errors.date }}</div>
            </div>
            <div class="col-span-2 md:col-span-4">
                <label :for="`${idPrefix}-category`" class="mb-1 block text-sm font-medium">Kategorie</label>
                <select :id="`${idPrefix}-category`" v-model="form.category" class="form-select w-full">
                    <optgroup v-for="group in categoryOptions" :key="group.label" :label="group.label">
                        <option v-for="category in group.options" :key="category.value" :value="category.value">{{ category.label }}</option>
                    </optgroup>
                </select>
                <div v-if="form.errors.category" class="mt-1 text-xs text-red-500">{{ form.errors.category }}</div>
            </div>
        </div>

        <div v-if="entry">
            <label :for="`${idPrefix}-notes`" class="mb-1 block text-sm font-medium">Notiz (optional)</label>
            <textarea :id="`${idPrefix}-notes`" v-model="form.notes" rows="2" class="form-textarea w-full"></textarea>
        </div>

        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-gray-500">Mehrere Beträge? Einfach <span class="font-mono">5,92+6,50+24</span> eingeben.</p>
            <div class="flex gap-2">
                <VButton v-if="entry" variant="secondary" @click="emit('cancel')">Abbrechen</VButton>
                <VButton type="submit" :icon="entry ? '' : 'PlusIcon'" :loading="form.processing" :disabled="form.processing">{{ entry ? 'Speichern' : 'Buchen' }}</VButton>
            </div>
        </div>
    </form>
</template>
