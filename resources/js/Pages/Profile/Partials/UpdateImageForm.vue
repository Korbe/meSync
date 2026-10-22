<script setup>
import { confirm } from '@/composables/useConfirm';
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { PhotoIcon, PencilIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';
import VButton from '@/components/VButton.vue';

/*
 * Upload card for one image of the profile: company logo or signature.
 */

const props = defineProps({
    field: { type: String, required: true },          // logo | signature (also the route name part)
    url: { type: String, default: null },
    title: { type: String, required: true },
    hint: { type: String, required: true },
    noun: { type: String, required: true },           // "Logo", "Unterschrift"
    removeMessage: { type: String, required: true },
});

const icons = { logo: PhotoIcon, signature: PencilIcon };

const form = useForm({
    [props.field]: null,
});

const fileInput = ref(null);
const preview = ref(null);
const dragging = ref(false);

const shown = computed(() => preview.value ?? props.url);
const selected = computed(() => form[props.field]);

const select = (file) => {
    if (!file) return;

    form[props.field] = file;
    form.clearErrors();
    preview.value = URL.createObjectURL(file);
};

const onDrop = (event) => {
    dragging.value = false;
    select(event.dataTransfer.files[0]);
};

const reset = () => {
    form.reset();
    preview.value = null;
    if (fileInput.value) fileInput.value.value = null;
};

const upload = () => {
    form.post(route(`profile.${props.field}.store`), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => reset(),
    });
};

const remove = async () => {
    if (!(await confirm({
        title: `${props.noun} entfernen?`,
        message: props.removeMessage,
        confirmText: `${props.noun} entfernen`,
        variant: 'danger',
    }))) return;

    router.delete(route(`profile.${props.field}.destroy`), { preserveScroll: true });
};
</script>

<template>
    <section class="card overflow-hidden">
        <div class="p-6">
            <h2 class="card-title"><component :is="icons[field]" class="h-5 w-5 text-primary-500" />{{ title }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ hint }}</p>

            <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <!-- Drop zone -->
                <label
                    class="flex min-h-44 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-8 text-center transition"
                    :class="dragging
                        ? 'border-primary-500 bg-primary-500/[0.08] shadow-[0_0_30px_-8px_rgba(31,173,174,0.6)]'
                        : 'border-gray-300 hover:border-primary-400 dark:border-white/15 dark:hover:border-primary-500/60'"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <input ref="fileInput" type="file" class="hidden" accept="image/png,image/jpeg,image/webp" @change="select($event.target.files[0])">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-primary-500/10 text-primary-500 ring-1 ring-primary-500/30">
                        <ArrowUpTrayIcon class="h-5 w-5" />
                    </span>
                    <span class="text-sm">
                        <span class="font-semibold text-primary-600 dark:text-primary-300">Datei auswählen</span>
                        <span class="text-gray-500"> oder hierher ziehen</span>
                    </span>
                    <span v-if="selected" class="max-w-full truncate font-mono text-xs text-gray-500">{{ selected.name }}</span>
                </label>

                <!-- Preview on white, like in the PDF -->
                <div class="flex min-h-44 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10">
                    <img v-if="shown" :src="shown" :alt="`${noun}-Vorschau`" class="max-h-28 max-w-full object-contain">
                    <span v-else class="text-xs text-gray-400">Kein{{ field === 'logo' ? '' : 'e' }} {{ noun }}</span>
                </div>
            </div>

            <div v-if="form.errors[field]" class="mt-3 text-xs text-red-500">{{ form.errors[field] }}</div>

            <div v-if="form.progress" class="mt-4 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: `${form.progress.percentage}%` }"></div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50/60 px-6 py-4 dark:border-white/[0.06] dark:bg-white/[0.02]">
            <VButton v-if="url && !selected" variant="secondary" icon="TrashIcon" @click="remove">Entfernen</VButton>
            <VButton v-if="selected" variant="secondary" @click="reset">Verwerfen</VButton>
            <VButton :disabled="!selected || form.processing" :loading="form.processing" @click="upload">{{ noun }} speichern</VButton>
        </div>
    </section>
</template>
