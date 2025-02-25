<template>
    <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-5">
        <form @submit.prevent="submit" class="space-y-5">
            <VSlider v-model="form.score" :min=1 :max=7 label="Score" :error="errors?.score" />

            <VTextarea v-model="form.description" label="Description"></VTextarea>

            <div class="flex justify-end">
                <VButton type="submit">{{ isEdit ? 'Update' : 'Create' }}</VButton>
                <VButton v-if="isEdit" type="button" @click="deleteDigestion" variant="danger" class="ml-3">
                    Delete
                </VButton>
            </div>
        </form>
    </div>
</template>

<script setup>
import VButton from '@/components/VButton.vue';
import VSlider from '@/components/VSlider.vue';
import VTextarea from '@/components/VTextarea.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue'

const props = defineProps({
    errors: Object,
    digestion: Object,
    isEdit: Boolean,
});

const form = useForm({
    score: 0,
    description: '',
});

watch(
    () => props.digestion,
    (newDigestion) => {
        if (newDigestion) {
            form.score = newDigestion.score || '';
            form.description = newDigestion.description || '';
        }
    },
    { immediate: true }
);

const submit = () => {
    if (props.isEdit)
        form.put(route('digestions.update', props.digestion.id));
    else
        form.post(route('digestions.store'));
};

const deleteDigestion = () => {
    form.delete(route('digestions.destroy', props.digestion.id));
};
</script>
