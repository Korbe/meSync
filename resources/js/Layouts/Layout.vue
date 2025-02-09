<script setup>
    import { ref } from 'vue';
    import Sidebar from './Sidebar/Sidebar.vue';
    import Header from './Header/Header.vue';

    const sidebarOpen = ref(false);

</script>
<template>
    
    <div class="font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400 flex h-[100dvh] overflow-hidden">
  
      <!-- Sidebar -->
      <Sidebar :sidebarOpen="sidebarOpen" @close-sidebar="sidebarOpen = false" />
  
      <!-- Content area -->
      <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
        
        <!-- Site header -->
        <Header :sidebarOpen="sidebarOpen" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

        <main class="grow">
            <slot />
        </main>
  
      </div> 
  
    </div>
  </template>