<script setup>
import Layout from '@/Layouts/Layout.vue';
import CustomerForm from './CustomerForm.vue';

defineProps({
    interests: Array,
});
</script>

<template>
    <Layout title="Neuer Kunde" :backTo="route('customers.index')">
        <CustomerForm :interests="interests" />
    </Layout>
</template>
