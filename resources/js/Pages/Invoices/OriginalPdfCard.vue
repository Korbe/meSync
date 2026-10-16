<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { DocumentArrowUpIcon, DocumentCheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { confirm } from '@/composables/useConfirm';

const props = defineProps({
    invoice: { type: Object, required: true },
    original: { type: Object, default: null },   // { name, size } when attached
});

const form = useForm({ original: null });
const fileInput = ref(null);
const dragging = ref(false);

const upload = (file) => {
    if (!file) return;
    form.original = file;
    form.post(route('invoices.original.store', props.invoice.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = null;
        },
    });
};

const remove = async () => {
    if (!(await confirm({
        title: 'Original-PDF entfernen?',
        message: 'Danach erzeugt meSync das PDF dieser Rechnung wieder selbst aus den eingetragenen Daten.',
        confirmText: 'Entfernen',
        variant: 'danger',
    }))) return;

    router.delete(route('invoices.original.destroy', props.invoice.id), { preserveScroll: true });
};

const formatSize = (bytes) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
</script>

<template>
    <section class="card mb-6 p-5">
        <!-- Attached: this PDF is used instead of the generated one -->
        <div v-if="original" class="flex flex-wrap items-center gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-primary-500 ring-1 ring-primary-500/30">
                <DocumentCheckIcon class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">Original-PDF angehängt</div>
                <div class="truncate text-xs text-gray-500">
                    {{ original.name }} · {{ formatSize(original.size) }} – wird beim Download und im Steuerberater-Export statt des erzeugten PDFs verwendet.
                </div>
            </div>
            <a :href="route('invoices.download', invoice.id)" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-300">Öffnen</a>
            <button type="button" class="cursor-pointer rounded-lg p-1.5 text-gray-400 transition hover:bg-red-500/10 hover:text-red-500" title="Original-PDF entfernen" @click="remove">
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <!-- Not attached: drop zone -->
        <label v-else
            class="flex cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed p-4 transition"
            :class="dragging ? 'border-primary-500 bg-primary-500/[0.06]' : form.errors.original ? 'border-red-400/70' : 'border-gray-200 hover:border-primary-400 dark:border-white/10 dark:hover:border-primary-500/60'"
            @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; upload($event.dataTransfer.files[0])">
            <input ref="fileInput" type="file" accept="application/pdf,.pdf" class="sr-only" @change="upload($event.target.files[0])" />
            <DocumentArrowUpIcon class="h-8 w-8 shrink-0" :class="form.processing ? 'animate-pulse text-primary-500' : 'text-gray-400'" />
            <div class="min-w-0">
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ form.processing ? 'Wird hochgeladen …' : 'Bestehende Rechnung als PDF anhängen' }}</div>
                <div class="text-xs text-gray-500">Hast du die Rechnung schon woanders geschrieben? Dann wird dein PDF statt des erzeugten verwendet. Privat gespeichert, max. 10 MB.</div>
                <div v-if="form.errors.original" class="mt-1 text-xs text-red-500">{{ form.errors.original }}</div>
            </div>
        </label>
    </section>
</template>
