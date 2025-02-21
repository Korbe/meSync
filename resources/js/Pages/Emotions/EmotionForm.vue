<template>
    <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-5">
        <form @submit.prevent="submit" class="space-y-5">
            <VSlider v-model="form.score" min="0" max="100" label="Score"></VSlider>
            
            <VInput label="Something" />

            <!-- Primäre Emotion -->
            <VSelect
                id="primary-emotion"
                label="Primäre Emotion"
                :options="primaryEmotions"
                placeholder="Bitte wählen..."
                v-model="form.primary"
                @change="handlePrimaryEmotionChange"
            />

            <!-- Sekundäre Emotion -->
            <VSelect
                id="secondary-emotion"
                label="Sekundäre Emotion"
                :options="secondaryEmotions"
                placeholder="Bitte wählen..."
                v-model="form.secondary"
                class="mt-4"
            />

            <VTextarea v-model="form.description" label="Description"></VTextarea>

            <div class="flex justify-end">
                <VButton type="submit">{{ isEdit ? 'Update' : 'Create' }}</VButton>
                <VButton v-if="isEdit" type="button" @click="deleteEmotion" variant="danger" class="ml-3">
                    Delete
                </VButton>
            </div>
        </form>
    </div>
</template>

<script setup>
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';
import VSelect from '@/components/VSelect.vue';
import VSlider from '@/components/VSlider.vue';
import VTextarea from '@/components/VTextarea.vue';
import { useEmotions } from '@/services/useEmotions';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    emotion: Object, 
    isEdit: Boolean,
});

const { primaryEmotions, secondaryEmotions, updateSecondaryEmotions } = useEmotions();

const form = useForm({
    score: '',
    primary: '',
    secondary: '',
    description: '',
});

const handlePrimaryEmotionChange = () => {
    updateSecondaryEmotions(form.primary);
};

watch(
    () => props.emotion,
    (newEmotion) => {
        if (newEmotion) {
            form.score = newEmotion.score || '';
            form.primary = newEmotion.primary || '';
            form.secondary = newEmotion.secondary || '';
            form.description = newEmotion.description || '';

            // Nach dem Laden der Emotionen, auch die sekundären Emotionen aktualisieren
            updateSecondaryEmotions(form.primary);
        }
    },
    { immediate: true } // Setzt die Werte direkt beim ersten Laden
);

watch(
    () => primaryEmotions.value,
    () => {
        if (form.primary) {
            updateSecondaryEmotions(form.primary);
        }
    }
);

const submit = () => {
    if (props.isEdit)
        form.put(route('emotions.update', props.emotion.id));
    else
        form.post(route('emotions.store'));
};

const deleteEmotion = () => {
    form.delete(route('emotions.destroy', props.emotion.id));
};
</script>
