<script setup>
import { useForm } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';

const props = defineProps({
    customer: Object,
});

const form = useForm({});

const destroy = () => {
    form.delete(route('customers.destroy', props.customer.id));
};
</script>

<template>
    <Layout title="Kunde löschen" :backTo="route('customers.show', customer.id)">
        <div class="mx-auto max-w-xl">
            <div class="card relative overflow-hidden border-red-500/30 p-8 text-center dark:border-red-500/30">
                <!-- Red glow -->
                <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-80 -translate-x-1/2 rounded-full bg-red-500/20 blur-[80px]" aria-hidden="true"></div>

                <div class="relative">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/30">
                        <ExclamationTriangleIcon class="h-7 w-7 text-red-500" />
                    </div>

                    <h2 class="mt-5 font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ customer.display_name }} löschen?
                    </h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Der Kunde wird endgültig gelöscht. Das kann nicht rückgängig gemacht werden.
                    </p>
                    <p v-if="customer.maintenance_contracts_count" class="mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm font-medium text-red-600 ring-1 ring-red-500/30 dark:text-red-400">
                        Achtung: {{ customer.maintenance_contracts_count }} {{ customer.maintenance_contracts_count === 1 ? 'Wartungsvertrag wird' : 'Wartungsverträge werden' }} mitgelöscht.
                    </p>

                    <!-- Summary -->
                    <dl class="mt-6 space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 text-left text-sm dark:border-white/10 dark:bg-white/[0.03]">
                        <div v-if="customer.full_name" class="flex justify-between gap-4">
                            <dt class="text-gray-500">Name</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ customer.full_name }}</dd>
                        </div>
                        <div v-if="customer.company" class="flex justify-between gap-4">
                            <dt class="text-gray-500">Firma</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ customer.company }}</dd>
                        </div>
                        <div v-if="customer.email" class="flex justify-between gap-4">
                            <dt class="text-gray-500">E-Mail</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-gray-100">{{ customer.email }}</dd>
                        </div>
                        <div v-if="customer.uid_number" class="flex justify-between gap-4">
                            <dt class="text-gray-500">UID</dt>
                            <dd class="text-right font-mono text-gray-900 dark:text-gray-100">{{ customer.uid_number }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex justify-center gap-3">
                        <VButton :href="route('customers.show', customer.id)" variant="secondary">Abbrechen</VButton>
                        <VButton variant="danger" icon="TrashIcon" :loading="form.processing" :disabled="form.processing" @click="destroy">
                            Endgültig löschen
                        </VButton>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
