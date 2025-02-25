<script setup>
import Layout from '@/Layouts/Layout.vue';
import DigestionForm from './DigestionForm.vue';

defineProps({
    digestion: Object
});
</script>
<template>
    <Layout title="Edit Digestion" :backTo="route('digestions.show', digestion.id)">
        <DigestionForm :digestion="digestion" :isEdit="true" />
    </Layout>
</template>

