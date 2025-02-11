<script setup>
import Layout from '@/Layouts/Layout.vue';
import Welcome from '@/JetstreamComponents/Welcome.vue';
</script>

<template>
    <Layout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <Welcome />
                </div>
            </div>
        </div>
    </Layout>
</template>
