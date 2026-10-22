<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EnvelopeIcon, PaperClipIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import VModal from '@/components/VModal.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';
import { formatDateTime } from '@/utils/format';

const props = defineProps({
    show: Boolean,
    document: { type: Object, required: true },   // invoice or offer: number, sent_at, sent_to
    label: { type: String, default: 'Rechnung' },
    action: { type: String, required: true },     // send route
    mail: { type: Object, required: true },       // to, subject, replyTo, demo
});

const emit = defineEmits(['close']);

const form = useForm({
    to: props.mail.to,
    subject: props.mail.subject,
    message: '',
});

// Fresh values each time the dialog opens
watch(() => props.show, (open) => {
    if (open) {
        form.reset();
        form.clearErrors();
        form.to = props.mail.to;
        form.subject = props.mail.subject;
    }
});

const send = () => form.post(props.action, {
    preserveScroll: true,
    onSuccess: () => emit('close'),
});
</script>

<template>
    <VModal :show="show" max-width="2xl" @close="emit('close')">
        <form class="p-6" @submit.prevent="send">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-primary-500/10 text-primary-500 ring-1 ring-primary-500/30">
                    <EnvelopeIcon class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="font-display text-lg font-semibold text-gray-900 dark:text-white">{{ label }} {{ document.number }} per E-Mail senden</h3>
                    <p v-if="document.sent_at" class="text-xs text-gray-500">Zuletzt verschickt am {{ formatDateTime(document.sent_at) }}<template v-if="document.sent_to"> an {{ document.sent_to }}</template></p>
                </div>
            </div>

            <div v-if="mail.demo" class="mt-5 rounded-xl bg-accent/[0.08] px-4 py-3 text-sm text-gray-700 ring-1 ring-accent/30 ring-inset dark:text-gray-300">
                Im Demo-Modus werden keine E-Mails verschickt.
            </div>

            <div class="mt-5 space-y-4">
                <VInput id="mail_to" type="email" label="An" placeholder="kunde@beispiel.at" v-model="form.to" :error="form.errors.to" mandatory />
                <VInput id="mail_subject" label="Betreff" v-model="form.subject" :error="form.errors.subject" mandatory />
                <div>
                    <label for="mail_message" class="mb-1 block text-sm font-medium">Persönliche Nachricht (optional)</label>
                    <textarea id="mail_message" v-model="form.message" rows="3" class="form-textarea w-full" placeholder="z. B. Danke für den angenehmen Termin!"></textarea>
                    <div v-if="form.errors.message" class="mt-1 text-xs text-red-500">{{ form.errors.message }}</div>
                </div>
            </div>

            <ul class="mt-5 space-y-1.5 rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-400">
                <li class="flex gap-2"><PaperClipIcon class="mt-0.5 h-4 w-4 shrink-0" />{{ label === 'Rechnung' ? 'Die Rechnung' : 'Das ' + label }} hängt als PDF an – genau wie beim Download.</li>
                <li class="flex gap-2"><InformationCircleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Die Mail sagt dem Kunden, dass sie automatisch erstellt wurde. Antworten gehen an <strong class="text-gray-900 dark:text-gray-100">{{ mail.replyTo || '– (im Profil hinterlegen)' }}</strong>.</span>
                </li>
            </ul>

            <div class="mt-6 flex justify-end gap-3">
                <VButton variant="secondary" @click="emit('close')">Abbrechen</VButton>
                <VButton type="submit" icon="PaperAirplaneIcon" :loading="form.processing" :disabled="form.processing || mail.demo">Jetzt senden</VButton>
            </div>
        </form>
    </VModal>
</template>
