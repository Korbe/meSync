<script setup>
import Layout from '@/Layouts/Layout.vue';
import OfferForm from './OfferForm.vue';

defineProps({
    offer: Object,
    customers: Array,
    projects: Array,
    catalog: Array,
    types: Array,
    packages: Array,
    features: Array,
    integrations: Array,
    contentDelivery: Array,
    paymentTerms: Array,
    hints: Object,
});
</script>

<template>
    <Layout title="Neues Angebot" :backTo="route('offers.index')">
        <OfferForm :offer="offer" :customers="customers" :projects="projects" :catalog="catalog" :types="types" :packages="packages" :features="features"
            :integrations="integrations" :contentDelivery="contentDelivery" :paymentTerms="paymentTerms" :hints="hints" />
    </Layout>
</template>
