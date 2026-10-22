<script setup>
import Layout from '@/Layouts/Layout.vue';
import ProjectForm from './ProjectForm.vue';

defineProps({
    project: Object,
    customers: Array,
    types: Array,
});
</script>

<template>
    <Layout title="Neues Projekt" :backTo="route('projects.index')">
        <ProjectForm :project="project" :customers="customers" :types="types" />
    </Layout>
</template>
