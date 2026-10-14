<script setup>
import { KeyIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import VButton from '@/components/VButton.vue';
import VActionMessage from '@/components/VActionMessage.vue';
import VFormSection from '@/components/VFormSection.vue';
import VInput from '@/components/VInput.vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <VFormSection @submitted="updatePassword">
        <template #header>
            <h2 class="card-title"><KeyIcon class="h-5 w-5 text-primary-500" />Passwort</h2>
            <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">Verwende ein langes, zufälliges Passwort, damit dein Konto sicher bleibt.</p>
        </template>

        <template #form>
            <div class="col-span-6">
                <VInput id="current_password" ref="currentPasswordInput" label="Aktuelles Passwort" type="password" v-model="form.current_password" :error="form.errors.current_password" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <VInput id="password" ref="passwordInput" label="Neues Passwort" type="password" v-model="form.password" :error="form.errors.password" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <VInput id="password_confirmation" label="Passwort bestätigen" type="password" v-model="form.password_confirmation" :error="form.errors.password_confirmation" />
            </div>
        </template>

        <template #actions>
            <VActionMessage :on="form.recentlySuccessful">Gespeichert.</VActionMessage>

            <VButton type="submit" :loading="form.processing" :disabled="form.processing">Speichern</VButton>
        </template>
    </VFormSection>
</template>
