<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FolderIcon, CheckIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VButton from '@/components/VButton.vue';
import VUrlInput from '@/components/VUrlInput.vue';
import QuickCustomerModal from '@/Pages/Invoices/QuickCustomerModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    customers: { type: Array, required: true },
    types: { type: Array, required: true },
});

const isEdit = !!props.project.id;

const form = useForm({
    customer_id: props.project.customer_id ?? '',
    type: props.project.type ?? 'website',
    name: props.project.name ?? '',
    url: props.project.url ?? '',
    notes: props.project.notes ?? '',
});

const creatingCustomer = ref(false);
const customerCreated = (id) => {
    creatingCustomer.value = false;
    if (id) form.customer_id = id;
};

const submit = () => isEdit
    ? form.put(route('projects.update', props.project.id))
    : form.post(route('projects.store'));
</script>

<template>
    <form @submit.prevent="submit" class="mx-auto max-w-3xl space-y-6">
        <section class="card p-6">
            <h2 class="card-title"><FolderIcon class="h-5 w-5 text-primary-500" />Projekt</h2>

            <div class="mt-5 space-y-4">
                <div>
                    <VSelect id="customer_id" label="Kunde" placeholder="Kunde wählen …" :options="customers" v-model="form.customer_id" :error="form.errors.customer_id" />
                    <p class="mt-1 text-xs text-gray-500">
                        Kunde fehlt? <button type="button" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" @click="creatingCustomer = true">Neuen Kunden anlegen</button>
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button v-for="type in types" :key="type.value" type="button"
                        class="relative rounded-xl border p-4 text-left transition"
                        :class="form.type === type.value ? 'border-primary-500 bg-primary-500/[0.08]' : 'border-gray-200 hover:border-gray-300 dark:border-white/10 dark:hover:border-white/20'"
                        @click="form.type = type.value">
                        <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ type.label }}</span>
                        <CheckIcon v-if="form.type === type.value" class="absolute top-3 right-3 h-5 w-5 text-primary-500" />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <VInput id="name" label="Name" placeholder="z. B. Website Musterfirma" v-model="form.name" :error="form.errors.name" />
                    <VUrlInput id="url" label="Domain (optional)" v-model="form.url" :error="form.errors.url" />
                </div>

                <div>
                    <label for="notes" class="mb-1 block text-sm font-medium">Notizen (optional)</label>
                    <textarea id="notes" v-model="form.notes" rows="4" class="form-textarea w-full" placeholder="Wünsche, Ansprechpartner, Zugangsdaten-Hinweise …"></textarea>
                    <div v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</div>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <VButton :href="isEdit ? route('projects.show', project.id) : route('projects.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">{{ isEdit ? 'Speichern' : 'Projekt anlegen' }}</VButton>
        </div>

        <QuickCustomerModal :show="creatingCustomer" :customers="customers" @close="creatingCustomer = false" @created="customerCreated" />
    </form>
</template>
