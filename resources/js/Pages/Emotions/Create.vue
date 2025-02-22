<script setup>
import Layout from '@/Layouts/Layout.vue';
import EmotionForm from './EmotionForm.vue';

const props = defineProps({
    errors: Object, // Fehler vom Backend
})
</script>

<template>
    <Layout title="Create Emotion" :backTo="route('emotions.index')">
        <EmotionForm :errors=errors :isEdit="false" />
    </Layout>
</template>
