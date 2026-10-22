<script setup>
import { useForm } from '@inertiajs/vue3';
import VInput from '@/components/VInput.vue';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';

/*
 * Create a catalog item without leaving the invoice. The page reloads its catalog (form
 * state is kept), and the new item is handed back to be inserted as an item.
 */

const props = defineProps({
    show: { type: Boolean, default: false },
    catalog: { type: Array, required: true },
});

const emit = defineEmits(['close', 'created']);

const form = useForm({
    name: '', description: '', pricing: 'fixed', unit: 'Stück', unit_price: '',
    base_minutes: '30', step_minutes: '15', step_price: '',
    inline: true,
});

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const save = () => {
    const known = new Set(props.catalog.map(item => item.id));

    form.post(route('catalog.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const created = page.props.catalog.find(item => !known.has(item.id));
            form.reset();
            emit('created', created ?? null);
        },
    });
};
</script>

<template>
    <VModal :show="show" max-width="lg" @close="close">
        <template #title>Neue Leistung</template>

        <template #content>
            <!-- Inside the invoice <form>: Enter saves the item, not the invoice -->
            <div class="space-y-4" @keydown.enter.prevent="save">
                <VInput id="quick_catalog_name" label="Name" placeholder="z. B. Router einrichten" v-model="form.name" :error="form.errors.name" />
                <div>
                    <label for="quick_catalog_description" class="mb-1 block text-sm font-medium">Beschreibung (optional)</label>
                    <textarea id="quick_catalog_description" v-model="form.description" rows="2" class="form-textarea w-full" placeholder="Steht in der Position unter dem Namen" @keydown.enter.stop></textarea>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button v-for="option in [{ value: 'fixed', label: 'Festpreis' }, { value: 'time', label: 'Nach Zeit' }]" :key="option.value" type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                        :class="form.pricing === option.value ? 'border-primary-500 bg-primary-500/[0.08] text-gray-900 dark:text-gray-100' : 'border-gray-200 text-gray-600 hover:border-gray-300 dark:border-white/10 dark:text-gray-400'"
                        @click="form.pricing = option.value">{{ option.label }}</button>
                </div>

                <div v-if="form.pricing === 'fixed'" class="grid grid-cols-2 gap-4">
                    <VInput id="quick_catalog_unit" label="Einheit" v-model="form.unit" :error="form.errors.unit" />
                    <VInput id="quick_catalog_price" label="Preis" suffix="€" v-model="form.unit_price" :error="form.errors.unit_price" />
                </div>

                <!-- "50 € for the first 30 min, then 20 € per started 15 min" -->
                <div v-else class="space-y-3 rounded-xl border border-gray-200 p-3.5 text-sm dark:border-white/10">
                    <div class="flex flex-wrap items-end gap-3">
                        <VInput id="quick_catalog_base_price" class="w-28" label="Grundpreis" suffix="€" v-model="form.unit_price" :error="form.errors.unit_price" />
                        <span class="pb-2.5 text-gray-500">für die ersten</span>
                        <VInput id="quick_catalog_base_minutes" class="w-24" label="Minuten" suffix="Min" v-model="form.base_minutes" :error="form.errors.base_minutes" />
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <span class="pb-2.5 text-gray-500">danach</span>
                        <VInput id="quick_catalog_step_price" class="w-28" label="Preis" suffix="€" v-model="form.step_price" :error="form.errors.step_price" />
                        <span class="pb-2.5 text-gray-500">je angefangene</span>
                        <VInput id="quick_catalog_step_minutes" class="w-24" label="Takt" suffix="Min" v-model="form.step_minutes" :error="form.errors.step_minutes" />
                    </div>
                </div>
                <p class="text-xs text-gray-500">Wird im Leistungskatalog gespeichert und gleich als Position eingefügt.</p>
            </div>
        </template>

        <template #footer>
            <VButton variant="secondary" @click="close">Abbrechen</VButton>
            <VButton :loading="form.processing" :disabled="form.processing" @click="save">Leistung anlegen</VButton>
        </template>
    </VModal>
</template>
