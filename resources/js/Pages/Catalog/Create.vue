<script setup>
import Layout from '@/Layouts/Layout.vue';
import CatalogForm from './CatalogForm.vue';
</script>

<template>
    <Layout title="Neue Leistung" :backTo="route('catalog.index')">
        <CatalogForm />
    </Layout>
</template>
