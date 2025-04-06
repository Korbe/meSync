<script setup>
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
            <h1 class="text-lg font-medium text-gray-900 dark:text-gray-100">Update Password</h1>
            <p class="mt-1 mb-5 text-sm text-gray-600 dark:text-gray-300">Ensure your account is using a long, random password to stay secure.</p>
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <VInput id="current_password" label="Current Password" type="password" v-model="form.current_password" :error="form.errors.current_password" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <VInput id="password" label="New Password" type="password" v-model="form.password" :error="form.errors.password" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <VInput id="password_confirmation" label="Confirm Password" type="password" v-model="form.password_confirmation" :error="form.errors.password_confirmation" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
