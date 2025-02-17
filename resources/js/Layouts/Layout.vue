<script setup>
import { ref } from 'vue';
import Sidebar from './Sidebar/Sidebar.vue';
import Header from './Header/Header.vue';
import { Head } from '@inertiajs/vue3';
import Banner from '@/components/Banner.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/solid';

const sidebarOpen = ref(false);

defineProps({
  title: String,
  backTo: String,
});

</script>
<template>

  <div
    class="flex h-[100dvh] overflow-hidden font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400">

    <Head :title="title" />

    <!-- Sidebar -->
    <Sidebar :sidebarOpen="sidebarOpen" @close-sidebar="sidebarOpen = false" />

    <!-- Content area -->
    <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">

      <!-- Site header -->
      <Header :sidebarOpen="sidebarOpen" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <Banner />

      <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-[96rem] mx-auto">

        <!-- Actions -->
        <div class="sm:flex sm:justify-between sm:items-center mb-8">

          <!-- Left: Title -->
          <div class="mb-4 sm:mb-0 flex items-center">
            <Link v-if="backTo" :href="backTo"><ChevronLeftIcon class="w-6 h-6 mr-2"></ChevronLeftIcon></Link>
            <h1 class="p-0 m-0 text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">{{title}}</h1>
          </div>

          <!-- Right: Actions -->
          <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">

            <slot name="actions" />
            <!-- <template v-slot:actions></template> -->
          </div>

        </div>

        <div>
          <slot />
        </div>
        <!-- <div class="grid grid-cols-12 gap-6">
          
        </div> -->

      </div>

    </div>

  </div>
</template>