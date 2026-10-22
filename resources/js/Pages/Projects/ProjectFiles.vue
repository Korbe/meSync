<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { PaperClipIcon, ArrowUpTrayIcon, DocumentTextIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { confirm } from '@/composables/useConfirm';
import { formatDate } from '@/utils/format';

/*
 * Files of a project (PDFs, images) as a grid: images with preview, PDFs as cards.
 * A click opens the file in a new tab. Stored privately, served through the app.
 */

const props = defineProps({
    project: { type: Object, required: true },
    files: { type: Array, default: () => [] },
});

const dragging = ref(false);
const uploading = ref(false);
const progress = ref(null);
const error = ref(null);

const upload = (list) => {
    const files = Array.from(list ?? []);
    if (!files.length) return;

    uploading.value = true;
    error.value = null;
    router.post(route('projects.files.store', props.project.id), { files }, {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (event) => progress.value = event?.percentage ?? null,
        onError: (errors) => error.value = Object.values(errors)[0],
        onFinish: () => { uploading.value = false; progress.value = null; },
    });
};

const onDrop = (event) => {
    dragging.value = false;
    upload(event.dataTransfer.files);
};

const remove = async (file) => {
    if (!(await confirm({ title: `„${file.name}“ löschen?`, message: 'Die Datei wird endgültig entfernt.', confirmText: 'Löschen', variant: 'danger' }))) return;
    router.delete(route('projects.files.destroy', [props.project.id, file.id]), { preserveScroll: true });
};

const size = (bytes) => bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const extension = (name) => (name.split('.').pop() ?? '').toUpperCase();
</script>

<template>
    <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="card-title"><PaperClipIcon class="h-5 w-5 text-primary-500" />Dateien</h2>
            <span v-if="files.length" class="text-xs text-gray-500">{{ files.length }} {{ files.length === 1 ? 'Datei' : 'Dateien' }}</span>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
            <!-- Upload tile -->
            <label class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-3 text-center text-xs transition"
                :class="dragging ? 'border-primary-500 bg-primary-500/[0.08]' : 'border-gray-200 text-gray-500 hover:border-primary-400 dark:border-white/10'"
                @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
                <input type="file" multiple accept="application/pdf,image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="upload($event.target.files); $event.target.value = null">
                <ArrowUpTrayIcon class="h-6 w-6" :class="uploading && 'animate-bounce text-primary-500'" />
                <span v-if="uploading" class="font-medium text-primary-600 dark:text-primary-300">Wird hochgeladen{{ progress !== null ? ` … ${progress} %` : ' …' }}</span>
                <span v-else><span class="font-semibold text-primary-600 dark:text-primary-300">Dateien hinzufügen</span><br>PDF oder Bilder, auch mehrere</span>
            </label>

            <!-- Files -->
            <div v-for="file in files" :key="file.id" class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:shadow-[0_12px_30px_-14px_rgba(31,173,174,0.6)] dark:border-white/10 dark:bg-white/[0.03]">
                <a :href="file.url" target="_blank" rel="noopener" class="block" :title="file.name">
                    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-gray-50 dark:bg-white/5">
                        <img v-if="file.image" :src="file.url" :alt="file.name" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        <div v-else class="flex flex-col items-center gap-1 text-red-500/80">
                            <DocumentTextIcon class="h-10 w-10" />
                            <span class="rounded bg-red-500/10 px-1.5 py-0.5 font-mono text-[10px] font-bold">{{ extension(file.name) }}</span>
                        </div>
                    </div>
                    <div class="px-2.5 py-2">
                        <div class="truncate text-xs font-medium text-gray-900 dark:text-gray-100">{{ file.name }}</div>
                        <div class="text-[11px] text-gray-500">{{ size(file.size) }} · {{ formatDate(file.created_at) }}</div>
                    </div>
                </a>
                <button type="button" class="absolute top-1.5 right-1.5 cursor-pointer rounded-lg bg-white/90 p-1.5 text-gray-500 opacity-0 shadow transition group-hover:opacity-100 hover:text-red-500 focus:opacity-100 dark:bg-gray-900/90"
                    title="Löschen" @click="remove(file)">
                    <TrashIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <p v-if="error" class="mt-3 text-xs text-red-500">{{ error }}</p>
        <p class="mt-3 text-xs text-gray-500">Briefing, Entwürfe, Screenshots, Verträge … privat gespeichert, max. 20 MB pro Datei.</p>
    </section>
</template>
