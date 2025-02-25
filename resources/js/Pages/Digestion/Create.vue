<script setup>
import Layout from '@/Layouts/Layout.vue';
import DigestionForm from './DigestionForm.vue';

const props = defineProps({
    errors: Object,
})
</script>

<template>
    <Layout title="Create Digestion" :backTo="route('digestions.index')">
        <DigestionForm :errors=errors :isEdit="false" />
    </Layout>
</template>
