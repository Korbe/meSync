<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';
import VInput from '@/components/VInput.vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const deleteUser = () => {
    form.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <section class="card relative overflow-hidden border-red-500/30 p-6 dark:border-red-500/25">
        <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-red-500/15 blur-[80px]" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="card-title"><ExclamationTriangleIcon class="h-5 w-5 text-red-500" />Konto löschen</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    Dein Konto und alle Daten (Kunden, Wartungsverträge, Rechnungsverlauf) werden endgültig gelöscht. Das kann nicht rückgängig gemacht werden.
                </p>
            </div>

            <VButton variant="danger" class="shrink-0" @click="confirmUserDeletion">Konto löschen</VButton>
        </div>

        <VModal :show="confirmingUserDeletion" @close="closeModal">
            <template #title>Konto wirklich löschen?</template>

            <template #content>
                Alle Daten deines Kontos werden endgültig gelöscht. Bitte gib zur Bestätigung dein Passwort ein.

                <div class="mt-4">
                    <VInput class="mt-1 sm:w-3/4" ref="passwordInput" v-model="form.password" type="password" placeholder="Passwort" :error="form.errors.password" />
                </div>
            </template>

            <template #footer>
                <VButton variant="secondary" @click="closeModal">Abbrechen</VButton>

                <VButton variant="danger" :disabled="form.processing" @click="deleteUser">Endgültig löschen</VButton>
            </template>
        </VModal>
    </section>
</template>
