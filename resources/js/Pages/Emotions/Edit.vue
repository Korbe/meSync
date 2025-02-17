<script setup>
import Layout from '@/Layouts/Layout.vue';
import EmotionForm from './EmotionForm.vue';

defineProps({
    emotion: Object
});
</script>
<template>
    <Layout title="Edit Emotion" :backTo="route('emotions.show', emotion.id)">
        <EmotionForm :emotion="emotion" :isEdit="true" />
    </Layout>
</template>

