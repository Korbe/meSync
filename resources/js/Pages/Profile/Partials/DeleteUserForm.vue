<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionSection from '@/JetstreamComponents/ActionSection.vue';
import DangerButton from '@/JetstreamComponents/DangerButton.vue';
import DialogModal from '@/JetstreamComponents/DialogModal.vue';
import InputError from '@/JetstreamComponents/InputError.vue';
import SecondaryButton from '@/JetstreamComponents/SecondaryButton.vue';
import TextInput from '@/JetstreamComponents/TextInput.vue';
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
    <ActionSection>

        <template #header>
            <h1 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete Account</h1>
            <p class="mt-1 mb-5 text-sm text-gray-600 dark:text-gray-300">Permanently delete your account.</p>
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-gray-600 dark:text-gray-300">
                Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
            </div>

            <div class="mt-5">
                <DangerButton @click="confirmUserDeletion">
                    Delete Account
                </DangerButton>
            </div>

            <!-- Delete Account Confirmation Modal -->
            <DialogModal :show="confirmingUserDeletion" @close="closeModal">
                <template #title>
                    Delete Account
                </template>

                <template #content>
                    Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.

                    <div class="mt-4">
                        <VInput class="mt-1 w-3/4" ref="passwordInput" v-model="form.password" type="password" :error="form.errors.password"/>
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Delete Account
                    </DangerButton>
                </template>
            </DialogModal>
        </template>
    </ActionSection>
</template>
