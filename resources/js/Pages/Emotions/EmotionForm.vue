<template>
    <form @submit.prevent="submit">
        <input v-model="form.score" type="number" min="0" max="10" placeholder="Score" />
        <input v-model="form.primary" type="text" placeholder="Primary Emotion" />
        <input v-model="form.secondary" type="text" placeholder="Secondary Emotion" />
        <textarea v-model="form.description" placeholder="Description"></textarea>

        <button type="submit">{{ isEdit ? 'Update' : 'Create' }}</button>

        <!-- Show delete button only when editing -->
        <button v-if="isEdit" type="button" @click="deleteEmotion" class="bg-red-500 text-white px-4 py-2">
            Delete
        </button>
    </form>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';

const props = defineProps({
    emotion: Object, // If editing, we receive an emotion object
    isEdit: Boolean, // Determines whether to send a POST or PUT request
});

const form = useForm({
    score: props.emotion?.score || '',
    primary: props.emotion?.primary || '',
    secondary: props.emotion?.secondary || '',
    description: props.emotion?.description || '',
});

const submit = () => {
    if (props.isEdit)
        form.put(route('emotions.update', props.emotion.id));
    else
        form.post(route('emotions.store'));
};

const deleteEmotion = () => {
    if (confirm('Are you sure you want to delete this emotion?')) {
        router.delete(route('emotions.destroy', props.emotion.id));
    }
};
</script>