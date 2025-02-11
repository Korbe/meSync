<script setup>
import { defineProps } from 'vue';
import Layout from '@/Layouts/Layout.vue';
import EmotionForm from './EmotionForm.vue';

defineProps({
    emotion: Object
});
</script>
<template>
    <Layout title="Edit Emotion">
        <h1 class="text-2xl font-bold">Edit Emotion</h1>
        <EmotionForm :emotion="emotion" :isEdit="true" />
    </Layout>
</template>

