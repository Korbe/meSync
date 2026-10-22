<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';
import VUrlInput from '@/components/VUrlInput.vue';
import { confirm } from '@/composables/useConfirm';

/*
 * A website without maintenance contract (e.g. an own app): add, edit or remove it.
 */

const props = defineProps({
    show: { type: Boolean, default: false },
    monitor: { type: Object, default: null },    // null = new
    customers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const blank = () => ({
    name: props.monitor?.name ?? '',
    url: props.monitor?.url ?? '',
    customer_id: props.monitor?.customer_id ?? '',
    auth: props.monitor?.auth ?? 'none',
    auth_user: props.monitor?.auth_user ?? '',
    auth_password: '',   // never sent to the browser; empty = keep the stored one
});

const form = useForm(blank());
const protectedSite = ref(false);

// Fresh values each time the dialog opens
watch(() => props.show, (open) => {
    if (!open) return;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    protectedSite.value = form.auth !== 'none';
});
watch(protectedSite, (value) => form.auth = value ? (form.auth === 'none' ? 'basic' : form.auth) : 'none');

const hasStoredPassword = computed(() => !!props.monitor?.has_password && props.monitor?.auth === 'basic');

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.monitor) form.put(route('monitors.update', props.monitor.id), options);
    else form.post(route('monitors.store'), options);
};

const remove = async () => {
    if (!(await confirm({
        title: `„${props.monitor.name}“ nicht mehr überwachen?`,
        message: 'Die Website und ihre Prüf-Historie werden gelöscht.',
        confirmText: 'Entfernen',
        variant: 'danger',
    }))) return;

    router.delete(route('monitors.destroy', props.monitor.id), { preserveScroll: true, onSuccess: () => emit('close') });
};
</script>

<template>
    <VModal :show="show" max-width="lg" @close="emit('close')">
        <template #title>{{ monitor ? 'Website bearbeiten' : 'Website überwachen' }}</template>

        <template #content>
            <p class="text-sm text-gray-500">Ohne Wartungsvertrag, z. B. für eine eigene App. Geprüft wird alle 5 Minuten, bei einem Ausfall kommt eine Mail.</p>

            <div class="mt-5 space-y-4" @keydown.enter.prevent="save">
                <VInput id="monitor_name" label="Name" placeholder="z. B. Meine App" v-model="form.name" :error="form.errors.name" />
                <VUrlInput id="monitor_url" label="Adresse" placeholder="app.korbitsch.at" v-model="form.url" :error="form.errors.url" />
                <VSelect v-if="customers.length" id="monitor_customer" label="Kunde (optional)" :options="[{ value: '', label: 'Kein Kunde' }, ...customers]" :modelValue="String(form.customer_id ?? '')" @update:modelValue="form.customer_id = $event" :error="form.errors.customer_id" />

                <!-- htpasswd / HTTP Basic Auth -->
                <div class="rounded-xl border border-gray-200 p-3.5 dark:border-white/10">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" v-model="protectedSite" class="form-checkbox mt-0.5 rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5">
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Passwortgeschützt (htpasswd)</span>
                            <span class="block text-xs text-gray-500">Die Seite fragt im Browser nach Benutzer und Passwort.</span>
                        </span>
                    </label>
                    <div v-if="protectedSite" class="mt-3 space-y-3 pl-7">
                        <label v-for="option in [{ value: 'basic', label: 'Mit Zugangsdaten prüfen' }, { value: 'accept_401', label: 'Passwortabfrage zählt als erreichbar' }]" :key="option.value" class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" :value="option.value" v-model="form.auth" class="form-radio text-primary-500">
                            {{ option.label }}
                        </label>
                        <div v-if="form.auth === 'basic'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <VInput id="monitor_auth_user" label="Benutzer" autocomplete="off" v-model="form.auth_user" :error="form.errors.auth_user" />
                            <VInput id="monitor_auth_password" type="password" label="Passwort" autocomplete="new-password"
                                :placeholder="hasStoredPassword ? '•••••••• (gespeichert)' : ''" v-model="form.auth_password" :error="form.errors.auth_password" />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <VButton v-if="monitor" variant="secondary" icon="TrashIcon" class="mr-auto" @click="remove">Entfernen</VButton>
            <VButton variant="secondary" @click="emit('close')">Abbrechen</VButton>
            <VButton :loading="form.processing" :disabled="form.processing" @click="save">{{ monitor ? 'Speichern' : 'Überwachen' }}</VButton>
        </template>
    </VModal>
</template>
