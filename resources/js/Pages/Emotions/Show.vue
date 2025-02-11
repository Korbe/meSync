<script setup>
import { defineProps } from 'vue';
import Layout from '@/Layouts/Layout.vue';

defineProps({
    emotion: Object
});

</script>
<template>
    <Layout title="Emotion">
        <h1 class="text-2xl font-bold">Show Emotion</h1>
    </Layout>
</template>

