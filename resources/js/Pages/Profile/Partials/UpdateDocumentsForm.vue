<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { DocumentTextIcon, ScaleIcon, ArrowUpTrayIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { confirm } from '@/composables/useConfirm';
import { formatDate } from '@/utils/format';

/*
 * Terms (AGB) and privacy policy as PDF: every offer mail carries them along.
 */

defineProps({
    documents: { type: Object, default: () => ({}) },   // terms|privacy => { name, size, updated_at } | null
});

const labels = { terms: 'AGB', privacy: 'Datenschutzerklärung' };
const uploading = ref(null);
const errors = ref({});

const upload = (document, file) => {
    if (!file) return;
    uploading.value = document;
    router.post(route('profile.documents.store', document), { file }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errs) => errors.value = { ...errors.value, [document]: errs.file },
        onSuccess: () => errors.value = { ...errors.value, [document]: null },
        onFinish: () => uploading.value = null,
    });
};

const remove = async (document) => {
    if (!(await confirm({
        title: `${labels[document]} entfernen?`,
        message: 'Angebote gehen dann ohne dieses Dokument raus.',
        confirmText: 'Entfernen',
        variant: 'danger',
    }))) return;

    router.delete(route('profile.documents.destroy', document), { preserveScroll: true });
};

const size = (bytes) => bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB` : `${Math.round(bytes / 1024)} KB`;
</script>

<template>
    <section class="card p-6">
        <h2 class="card-title"><ScaleIcon class="h-5 w-5 text-primary-500" />AGB &amp; Datenschutz</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Als PDF hinterlegen – sie hängen dann automatisch an jeder Angebotsmail. Privat gespeichert, max. 10 MB.
        </p>

        <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div v-for="(label, key) in labels" :key="key">
                <div v-if="documents?.[key]" class="flex items-center gap-3 rounded-xl bg-primary-500/[0.06] p-3.5 ring-1 ring-primary-500/25">
                    <DocumentTextIcon class="h-7 w-7 shrink-0 text-primary-500" />
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ label }}</div>
                        <a :href="route('profile.documents.show', key)" target="_blank" class="block truncate text-xs text-gray-500 hover:text-primary-500">
                            {{ documents[key].name }} · {{ size(documents[key].size) }} · {{ formatDate(documents[key].updated_at) }}
                        </a>
                    </div>
                    <label class="cursor-pointer rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600" title="Ersetzen">
                        <input type="file" accept="application/pdf,.pdf" class="hidden" @change="upload(key, $event.target.files[0])">
                        <ArrowUpTrayIcon class="h-4 w-4" />
                    </label>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Entfernen" @click="remove(key)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>

                <label v-else class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-gray-200 p-3.5 text-sm text-gray-600 transition hover:border-primary-400 dark:border-white/10 dark:text-gray-400"
                    @dragover.prevent @drop.prevent="upload(key, $event.dataTransfer.files[0])">
                    <input type="file" accept="application/pdf,.pdf" class="hidden" @change="upload(key, $event.target.files[0])">
                    <ArrowUpTrayIcon class="h-6 w-6 shrink-0 text-gray-400" />
                    <span>
                        <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ label }}</span>
                        {{ uploading === key ? 'Wird hochgeladen …' : 'PDF hierher ziehen oder klicken' }}
                    </span>
                </label>

                <div v-if="errors[key]" class="mt-1 text-xs text-red-500">{{ errors[key] }}</div>
            </div>
        </div>
    </section>
</template>
