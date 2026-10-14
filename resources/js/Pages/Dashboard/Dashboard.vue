<script setup>
import Layout from '@/Layouts/Layout.vue';
import Datepicker from '@/components/Datepicker.vue';

</script>

<template>
    <Layout title="Dashboard">
        <template v-slot:actions>
            <Datepicker align="right" v-model="dateRange" />
        </template>
    </Layout>
</template>
