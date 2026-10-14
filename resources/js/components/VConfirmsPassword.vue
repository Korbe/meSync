<script setup>
import { ref, reactive, nextTick } from 'vue';
import VModal from './VModal.vue';
import VButton from './VButton.vue';
import VInput from './VInput.vue';

const emit = defineEmits(['confirmed']);

defineProps({
    title: {
        type: String,
        default: 'Passwort bestätigen',
    },
    content: {
        type: String,
        default: 'Bitte bestätige zur Sicherheit dein Passwort.',
    },
    button: {
        type: String,
        default: 'Bestätigen',
    },
});

const confirmingPassword = ref(false);

const form = reactive({
    password: '',
    error: '',
    processing: false,
});

const passwordInput = ref(null);

const startConfirmingPassword = () => {
    axios.get(route('password.confirmation')).then(response => {
        if (response.data.confirmed) {
            emit('confirmed');
        } else {
            confirmingPassword.value = true;

            setTimeout(() => passwordInput.value.focus(), 250);
        }
    });
};

const confirmPassword = () => {
    form.processing = true;

    axios.post(route('password.confirm'), {
        password: form.password,
    }).then(() => {
        form.processing = false;

        closeModal();
        nextTick().then(() => emit('confirmed'));

    }).catch(error => {
        form.processing = false;
        form.error = error.response.data.errors.password[0];
        passwordInput.value.focus();
    });
};

const closeModal = () => {
    confirmingPassword.value = false;
    form.password = '';
    form.error = '';
};
</script>

<template>
    <span>
        <span @click="startConfirmingPassword">
            <slot />
        </span>

        <VModal :show="confirmingPassword" @close="closeModal">
            <template #title>
                {{ title }}
            </template>

            <template #content>
                {{ content }}

                <div class="mt-4">
                    <VInput class="mt-1 sm:w-3/4" ref="passwordInput" label="Passwort" v-model="form.password" type="password" :error="form.error" />
                </div>
            </template>

            <template #footer>
                <VButton variant="secondary" @click="closeModal">Abbrechen</VButton>

                <VButton :loading="form.processing" :disabled="form.processing" @click="confirmPassword">{{ button }}</VButton>
            </template>
        </VModal>
    </span>
</template>
