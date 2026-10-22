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
    <Layout :title="project.name" :backTo="route('projects.show', project.id)">
        <ProjectForm :project="project" :customers="customers" :types="types" />
    </Layout>
</template>
