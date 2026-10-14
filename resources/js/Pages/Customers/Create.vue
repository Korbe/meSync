<script setup>
import Layout from '@/Layouts/Layout.vue';
import CustomerForm from './CustomerForm.vue';
</script>

<template>
    <Layout title="Neuer Kunde" :backTo="route('customers.index')">
        <CustomerForm />
    </Layout>
</template>
