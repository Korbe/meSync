<script setup>
import Layout from '@/Layouts/Layout.vue';
import EmotionForm from './EmotionForm.vue';
</script>

<template>
    <Layout title="Create Emotion" :backTo="route('emotions.index')">
        <EmotionForm :isEdit="false" />
    </Layout>
</template>
