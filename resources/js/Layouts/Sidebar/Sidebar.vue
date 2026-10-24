<template>
  <div class="min-w-fit">
    <!-- Sidebar backdrop (mobile only) -->
    <div class="fixed inset-0 bg-gray-950/70 backdrop-blur-sm z-40 lg:hidden lg:z-auto transition-opacity duration-200" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'" aria-hidden="true"></div>

    <!-- Sidebar (always dark, like korbitsch.at) -->
    <div
      id="sidebar"
      ref="sidebar"
      class="isolate flex lg:flex! flex-col absolute z-40 left-0 top-0 lg:static lg:left-auto lg:top-auto lg:translate-x-0 h-[100dvh] overflow-y-scroll lg:overflow-y-auto no-scrollbar w-64 lg:w-20 lg:sidebar-expanded:!w-64 2xl:w-64! shrink-0 bg-gray-950 text-gray-400 border-r border-white/[0.06] p-4 transition-all duration-200 ease-in-out"
      :class="['rounded-r-2xl lg:rounded-none shadow-2xl lg:shadow-none', sidebarOpen ? 'translate-x-0' : '-translate-x-64']"
    >
      <!-- Glow decoration -->
      <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-24 -left-24 h-64 w-64 rounded-full bg-primary-500/20 blur-[90px]"></div>
        <div class="absolute bottom-0 -right-24 h-56 w-56 rounded-full bg-accent/[0.06] blur-[90px]"></div>
        <div class="sidebar-grid absolute inset-0"></div>
      </div>

      <!-- Sidebar header -->
      <div class="flex items-center justify-between mb-10 pr-3 sm:px-2">
        <!-- Close button -->
        <button
          ref="trigger"
          class="lg:hidden text-gray-500 hover:text-white transition"
          @click.stop="$emit('close-sidebar')"
          aria-controls="sidebar"
          :aria-expanded="sidebarOpen"
        >
          <span class="sr-only">Close sidebar</span>
          <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
          </svg>
        </button>
        <!-- Logo -->
        <Link class="group flex items-center gap-3" href="/">
          <img src="/images/logo/logo-144x144-dark.png?v=2" alt="meSync" class="h-9 w-9 shrink-0 drop-shadow-[0_0_14px_rgba(31,173,174,0.45)] transition-transform duration-300 group-hover:scale-105" />
          <span class="font-display text-xl font-bold tracking-tight text-white lg:hidden lg:sidebar-expanded:block 2xl:block">me<span class="text-primary-400">Sync</span></span>
        </Link>
      </div>

      <!-- Links -->
      <div class="space-y-8">
        <!-- Pages group -->
        <div>
          <h3 class="pl-3 font-mono text-[10px] font-medium uppercase tracking-[0.22em] text-gray-500">
            <span class="hidden lg:block lg:sidebar-expanded:hidden 2xl:hidden text-center w-6" aria-hidden="true">•••</span>
            <span class="lg:hidden lg:sidebar-expanded:block 2xl:block">Menü</span>
          </h3>
          <ul class="mt-3 space-y-1">
            <li>
              <Link href="/" class="nav-link" :class="isActive('/dashboard') && 'nav-link-active'">
                <HomeIcon class="nav-icon" />
                <span class="nav-label">Dashboard</span>
              </Link>
            </li>
            <li>
              <Link :href="route('customers.index')" class="nav-link" :class="isActive('/customers') && 'nav-link-active'">
                <UsersIcon class="nav-icon" />
                <span class="nav-label">Kunden</span>
              </Link>
            </li>
            <li>
              <Link :href="route('offers.index')" class="nav-link" :class="isActive('/offers') && 'nav-link-active'">
                <ClipboardDocumentListIcon class="nav-icon" />
                <span class="nav-label">Angebote</span>
              </Link>
            </li>
            <li>
              <Link :href="route('invoices.index')" class="nav-link" :class="isActive('/invoices') && 'nav-link-active'">
                <DocumentTextIcon class="nav-icon" />
                <span class="nav-label">Rechnungen</span>
              </Link>
            </li>
            <li>
              <Link :href="route('projects.index')" class="nav-link" :class="isActive('/projects') && 'nav-link-active'">
                <FolderIcon class="nav-icon" />
                <span class="nav-label">Projekte</span>
              </Link>
            </li>
            <li>
              <Link :href="route('contracts.index')" class="nav-link" :class="isActive('/contracts') && 'nav-link-active'">
                <span class="relative">
                  <WrenchScrewdriverIcon class="nav-icon" />
                  <!-- Due badge on the icon (visible when the sidebar is collapsed) -->
                  <span v-if="dueToday" class="absolute -top-1.5 -right-1.5 h-4 w-4 animate-ping rounded-full bg-accent opacity-60 max-lg:hidden lg:sidebar-expanded:hidden 2xl:hidden"></span>
                  <span v-if="dueCount" class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-gray-950 shadow-[0_0_10px_rgba(245,165,36,0.7)] max-lg:hidden lg:sidebar-expanded:hidden 2xl:hidden">{{ dueCount }}</span>
                </span>
                <span class="nav-label">Wartungsverträge</span>
                <span v-if="dueCount" class="ml-auto hidden rounded-full bg-accent/15 px-2 py-0.5 text-[11px] font-semibold text-accent ring-1 ring-inset ring-accent/40 lg:sidebar-expanded:inline 2xl:inline max-lg:inline">{{ dueCount }}</span>
              </Link>
            </li>
            <li>
              <Link :href="route('catalog.index')" class="nav-link" :class="isActive('/catalog') && 'nav-link-active'">
                <Squares2X2Icon class="nav-icon" />
                <span class="nav-label">Leistungen</span>
              </Link>
            </li>
            <li>
              <Link :href="route('stopwatch.index')" class="nav-link" :class="isActive('/stopwatch') && 'nav-link-active'">
                <span class="relative">
                  <ClockIcon class="nav-icon" />
                  <span v-if="timerRunning" class="absolute -top-1 -right-1 h-2.5 w-2.5 animate-pulse rounded-full bg-primary-400 ring-2 ring-white dark:ring-gray-950"></span>
                </span>
                <span class="nav-label">Stoppuhr</span>
              </Link>
            </li>
            <li>
              <Link :href="route('monitoring.index')" class="nav-link" :class="isActive('/monitoring') && 'nav-link-active'">
                <span class="relative">
                  <SignalIcon class="nav-icon" />
                  <span v-if="sitesDown" class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.9)] ring-2 ring-white dark:ring-gray-950"></span>
                </span>
                <span class="nav-label">Monitoring</span>
                <span v-if="sitesDown" class="ml-auto hidden rounded-full bg-red-500/15 px-2 py-0.5 text-[11px] font-semibold text-red-500 ring-1 ring-inset ring-red-500/40 lg:sidebar-expanded:inline 2xl:inline max-lg:inline">{{ sitesDown }}</span>
              </Link>
            </li>
            <li>
              <Link :href="route('finance.index')" class="nav-link" :class="isActive('/finance') && 'nav-link-active'">
                <BanknotesIcon class="nav-icon" />
                <span class="nav-label">Finanzen</span>
              </Link>
            </li>
            <li>
              <Link :href="route('inventory.index')" class="nav-link" :class="isActive('/inventory') && 'nav-link-active'">
                <ArchiveBoxIcon class="nav-icon" />
                <span class="nav-label">Inventar</span>
              </Link>
            </li>
          </ul>
        </div>

        <!-- Admin group -->
        <div>
          <h3 class="pl-3 font-mono text-[10px] font-medium uppercase tracking-[0.22em] text-gray-500">
            <span class="hidden lg:block lg:sidebar-expanded:hidden 2xl:hidden text-center w-6" aria-hidden="true">•••</span>
            <span class="lg:hidden lg:sidebar-expanded:block 2xl:block">Admin</span>
          </h3>
          <ul class="mt-3 space-y-1">
            <li>
              <Link :href="route('export.index')" class="nav-link" :class="isActive('/export') && 'nav-link-active'">
                <ArrowDownTrayIcon class="nav-icon" />
                <span class="nav-label">Export</span>
              </Link>
            </li>
            <li>
              <Link :href="route('backup.index')" class="nav-link" :class="isActive('/backup') && 'nav-link-active'">
                <CircleStackIcon class="nav-icon" />
                <span class="nav-label">Backup</span>
              </Link>
            </li>
            <li>
              <Link :href="route('mail-test.index')" class="nav-link" :class="isActive('/mail-test') && 'nav-link-active'">
                <EnvelopeIcon class="nav-icon" />
                <span class="nav-label">E-Mail-Test</span>
              </Link>
            </li>
          </ul>
        </div>
      </div>

      <!-- Expand / collapse button -->
      <div class="pt-3 hidden lg:inline-flex 2xl:hidden justify-end mt-auto">
        <button class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-gray-400 backdrop-blur transition hover:border-primary-500/50 hover:text-primary-300 hover:shadow-[0_0_20px_-4px_rgba(31,173,174,0.6)]" @click.prevent="sidebarExpanded = !sidebarExpanded">
          <span class="sr-only">Expand / collapse sidebar</span>
          <svg class="shrink-0 fill-current sidebar-expanded:rotate-180 transition-transform" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16">
            <path d="M15 16a1 1 0 0 1-1-1V1a1 1 0 1 1 2 0v14a1 1 0 0 1-1 1ZM8.586 7H1a1 1 0 1 0 0 2h7.586l-2.793 2.793a1 1 0 1 0 1.414 1.414l4.5-4.5A.997.997 0 0 0 12 8.01M11.924 7.617a.997.997 0 0 0-.217-.324l-4.5-4.5a1 1 0 0 0-1.414 1.414L8.586 7M12 7.99a.996.996 0 0 0-.076-.373Z" />
          </svg>
        </button>
      </div>

    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { usePage } from "@inertiajs/vue3";


import SidebarLinkGroup from './SidebarLinkGroup.vue'
import { BeakerIcon, HeartIcon, HomeIcon, SparklesIcon, UsersIcon, WrenchScrewdriverIcon, DocumentTextIcon, Squares2X2Icon, ArrowDownTrayIcon, CircleStackIcon, ArchiveBoxIcon, BanknotesIcon, SignalIcon, ClockIcon, ClipboardDocumentListIcon, FolderIcon, EnvelopeIcon } from '@heroicons/vue/24/solid';

export default {
  name: 'Sidebar',
  props: [
    'sidebarOpen',
  ],
  components: {
    SidebarLinkGroup,
    HomeIcon,
    SparklesIcon,
    HeartIcon,
    BeakerIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
    DocumentTextIcon,
    Squares2X2Icon,
    ArrowDownTrayIcon,
    CircleStackIcon,
    ArchiveBoxIcon,
    BanknotesIcon,
    SignalIcon,
    ClockIcon,
    ClipboardDocumentListIcon,
    FolderIcon,
    EnvelopeIcon,
  },  
  setup(props, { emit }) {

    const trigger = ref(null)
    const sidebar = ref(null)

    const storedSidebarExpanded = localStorage.getItem('sidebar-expanded')
    const sidebarExpanded = ref(storedSidebarExpanded === null ? false : storedSidebarExpanded === 'true')

    const currentUrl = computed(() => usePage().url);
    const dueCount = computed(() => usePage().props.dueContractsCount);
    const dueToday = computed(() => usePage().props.dueTodayCount);
    const sitesDown = computed(() => usePage().props.sitesDownCount);
    const timerRunning = computed(() => usePage().props.activeTimer?.status === 'running');

    

    const isActive = (href) => currentUrl.value.startsWith(href);

    const currentRoute = {
        name: '',         // Default to null for route name
        path: './',         // Default to './' for the full path
        hash: '',           // Default to an empty string for hash
        query: {},          // Default to an empty object for query parameters
        params: {},         // Default to an empty object for dynamic route parameters
        fullPath: './',     // Default to './' for the full URL path
        matched: [],        // Default to an empty array for matched route records
  };

    // close on click outside
    const clickHandler = ({ target }) => {
      if (!sidebar.value || !trigger.value) return
      if (
        !props.sidebarOpen ||
        sidebar.value.contains(target) ||
        trigger.value.contains(target)
      ) return
      emit('close-sidebar')
    }

    // close if the esc key is pressed
    const keyHandler = ({ keyCode }) => {
      if (!props.sidebarOpen || keyCode !== 27) return
      emit('close-sidebar')
    } 

    onMounted(() => {
      document.addEventListener('click', clickHandler)
      document.addEventListener('keydown', keyHandler)
    })

    onUnmounted(() => {
      document.removeEventListener('click', clickHandler)
      document.removeEventListener('keydown', keyHandler)
    })

    watch(sidebarExpanded, () => {
      localStorage.setItem('sidebar-expanded', sidebarExpanded.value)
      if (sidebarExpanded.value) {
        document.querySelector('body').classList.add('sidebar-expanded')
      } else {
        document.querySelector('body').classList.remove('sidebar-expanded')
      }
    })

    return {
      trigger,
      sidebar,
      sidebarExpanded,
      currentRoute,
      isActive,
      dueCount,
      dueToday,
      sitesDown,
      timerRunning,
    }
  },
}
</script>