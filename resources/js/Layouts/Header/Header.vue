<template>
  <header class="sticky top-0 z-30 border-b border-gray-200/70 bg-white/70 backdrop-blur-xl dark:border-white/[0.06] dark:bg-gray-950/60">
    <!-- Teal hairline glow along the bottom edge -->
    <div class="pointer-events-none absolute inset-x-0 -bottom-px h-px bg-linear-to-r from-transparent via-primary-500/50 to-transparent" aria-hidden="true"></div>

    <div class="px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">

        <!-- Header: Left side -->
        <div class="flex items-center gap-4">

          <!-- Hamburger button -->
          <button class="glass-button lg:hidden" @click.stop="$emit('toggle-sidebar')" aria-controls="sidebar" :aria-expanded="sidebarOpen">
            <span class="sr-only">Open sidebar</span>
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <rect x="4" y="5" width="16" height="2" rx="1" />
              <rect x="4" y="11" width="16" height="2" rx="1" />
              <rect x="4" y="17" width="10" height="2" rx="1" />
            </svg>
          </button>

          <!-- Date pill -->
          <p class="hidden sm:inline-flex items-center gap-3 rounded-full border border-gray-200 bg-white/60 px-4 py-1.5 font-mono text-[11px] uppercase tracking-[0.22em] text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
            <span class="relative flex h-2 w-2">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary-400 opacity-75"></span>
              <span class="relative inline-flex h-2 w-2 rounded-full bg-primary-500"></span>
            </span>
            {{ today }}
          </p>

        </div>

        <!-- Header: Right side -->
        <div class="flex items-center gap-3">
          <ThemeToggle />
          <!-- korbitsch.at brand mark (loaded straight from the website) -->
          <a href="https://korbitsch.at" target="_blank" rel="noopener" title="korbitsch.at" class="glass-button">
            <img src="https://korbitsch.at/assets/logo-DC1elymH.png" alt="korbitsch.at" class="h-5 w-5 object-contain">
          </a>
          <!-- Divider -->
          <hr class="w-px h-6 bg-gray-200 dark:bg-white/10 border-none" />
          <UserMenu align="right" />
        </div>

      </div>
    </div>
  </header>
</template>

<script>
import ThemeToggle from './ThemeToggle.vue'
import UserMenu from './DropdownProfile.vue'

export default {
  name: 'Header',
  props: [
    'sidebarOpen',
    'variant',
  ],
  components: {
    ThemeToggle,
    UserMenu,
  },
  setup() {
    const today = new Date().toLocaleDateString('de-AT', { weekday: 'long', day: 'numeric', month: 'long' })

    return { today }
  }
}
</script>