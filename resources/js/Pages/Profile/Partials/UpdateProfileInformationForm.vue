<script setup>
import { UserCircleIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import VButton from '@/components/VButton.vue';
import VActionMessage from '@/components/VActionMessage.vue';
import VFormSection from '@/components/VFormSection.vue';
import VInput from '@/components/VInput.vue';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <VFormSection @submitted="updateProfileInformation">
        <template #header>
            <h2 class="card-title"><UserCircleIcon class="h-5 w-5 text-primary-500" />Profil</h2>
            <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">Name und E-Mail-Adresse deines Kontos.</p>
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6">
                <input id="photo" ref="photoInput" type="file" class="hidden" @change="updatePhotoPreview">

                <label for="photo" class="block text-sm font-medium">Foto</label>

                <div class="mt-2 flex items-center gap-4">
                    <img v-show="! photoPreview" :src="user.profile_photo_url" :alt="user.name" class="size-16 rounded-full object-cover ring-2 ring-primary-500/40">
                    <span v-show="photoPreview" class="block size-16 rounded-full bg-cover bg-no-repeat bg-center ring-2 ring-primary-500/40" :style="{ backgroundImage: `url(${photoPreview})` }" />

                    <VButton variant="secondary" type="button" @click.prevent="selectNewPhoto">Neues Foto wählen</VButton>
                    <VButton variant="secondary" v-if="user.profile_photo_path" type="button" @click.prevent="deletePhoto">Entfernen</VButton>
                </div>

                <div v-if="form.errors.photo" class="mt-2 text-xs text-red-500">{{ form.errors.photo }}</div>
            </div>

            <div class="col-span-6">
                <VInput id="name" label="Name" v-model="form.name" mandatory :error="form.errors.name" />
            </div>

            <div class="col-span-6">
                <VInput id="email" type="email" label="E-Mail" v-model="form.email" mandatory :error="form.errors.email" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null" class="mt-3 rounded-lg bg-accent/10 px-3 py-2 text-sm text-amber-700 ring-1 ring-accent/30 dark:text-accent">
                    Deine E-Mail-Adresse ist noch nicht bestätigt.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-medium underline hover:no-underline"
                        @click.prevent="sendEmailVerification"
                    >
                        Bestätigungsmail erneut senden
                    </Link>

                    <div v-show="verificationLinkSent" class="mt-1 font-medium text-primary-600 dark:text-primary-300">
                        Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <VActionMessage :on="form.recentlySuccessful">Gespeichert.</VActionMessage>

            <VButton type="submit" :loading="form.processing" :disabled="form.processing">Speichern</VButton>
        </template>
    </VFormSection>
</template>
