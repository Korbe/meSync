<script setup>
import { ref } from 'vue';
import Sidebar from './Sidebar/Sidebar.vue';
import Header from './Header/Header.vue';
import { Head } from '@inertiajs/vue3';
import Banner from '@/components/Banner.vue';
import VConfirmDialog from '@/components/VConfirmDialog.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/solid';

const sidebarOpen = ref(false);

defineProps({
  title: String,
  backTo: String,
});

</script>
<template>

  <div
    class="flex h-[100dvh] overflow-hidden antialiased bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400">

    <Head :title="title" />

    <!-- Sidebar -->
    <Sidebar :sidebarOpen="sidebarOpen" @close-sidebar="sidebarOpen = false" />

    <!-- Content area -->
    <div scroll-region class="relative isolate flex flex-col flex-1 overflow-y-auto overflow-x-hidden">

      <!-- Ambient brand glow behind the content -->
      <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-40 right-0 h-[480px] w-[480px] rounded-full bg-primary-400/15 blur-[140px] dark:bg-primary-500/10"></div>
        <div class="absolute bottom-0 left-1/3 h-[320px] w-[320px] rounded-full bg-accent/[0.06] blur-[140px]"></div>
      </div>

      <!-- Site header -->
      <Header :sidebarOpen="sidebarOpen" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <Banner />

      <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-[96rem] mx-auto">

        <!-- Actions -->
        <div class="sm:flex sm:justify-between sm:items-center mb-8">

          <!-- Left: Title -->
          <div class="mb-4 sm:mb-0 flex items-center">
            <Link v-if="backTo" :href="backTo"><ChevronLeftIcon class="w-6 h-6 mr-2"></ChevronLeftIcon></Link>
            <h1 class="p-0 m-0 font-display text-3xl md:text-4xl font-bold tracking-tight text-gray-900 dark:text-white">{{title}}</h1>
          </div>

          <!-- Right: Actions -->
          <!-- Wraps on narrow screens instead of pushing buttons out of view -->
          <div class="flex flex-wrap justify-start sm:justify-end gap-2">

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

  <VConfirmDialog />
</template>