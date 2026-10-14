<script setup>
import { KeyIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/JetstreamComponents/ActionMessage.vue';
import FormSection from '@/JetstreamComponents/FormSection.vue';
import InputError from '@/JetstreamComponents/InputError.vue';
import InputLabel from '@/JetstreamComponents/InputLabel.vue';
import PrimaryButton from '@/JetstreamComponents/PrimaryButton.vue';
import TextInput from '@/JetstreamComponents/TextInput.vue';
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
    <FormSection @submitted="updatePassword">
        <template #header>
            <h2 class="card-title"><KeyIcon class="h-5 w-5 text-primary-500" />Passwort</h2>
            <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">Verwende ein langes, zufälliges Passwort, damit dein Konto sicher bleibt.</p>
        </template>

        <template #form>
            <div class="col-span-6">
                <VInput id="current_password" label="Aktuelles Passwort" type="password" v-model="form.current_password" :error="form.errors.current_password" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <VInput id="password" label="Neues Passwort" type="password" v-model="form.password" :error="form.errors.password" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <VInput id="password_confirmation" label="Passwort bestätigen" type="password" v-model="form.password_confirmation" :error="form.errors.password_confirmation" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful">Gespeichert.</ActionMessage>

            <PrimaryButton :disabled="form.processing">Speichern</PrimaryButton>
        </template>
    </FormSection>
</template>
