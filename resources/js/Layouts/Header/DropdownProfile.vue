<template>
  <div class="relative inline-flex">
    <button
      ref="trigger"
      class="group inline-flex items-center gap-2.5 rounded-full py-1 pl-1 pr-3 transition hover:bg-gray-100 dark:hover:bg-white/5"
      aria-haspopup="true"
      @click.prevent="dropdownOpen = !dropdownOpen"
      :aria-expanded="dropdownOpen"
    >
      <!-- Avatar with brand gradient ring -->
      <span class="rounded-full bg-linear-to-br from-primary-400 via-primary-500 to-accent p-[2px] shadow-[0_0_18px_-4px_rgba(31,173,174,0.7)]">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white font-display text-sm font-bold text-primary-600 dark:bg-gray-950 dark:text-primary-300">{{ initials }}</span>
      </span>
      <span class="hidden sm:block truncate max-w-32 text-sm font-medium text-gray-700 group-hover:text-gray-900 dark:text-gray-200 dark:group-hover:text-white">{{ user.name }}</span>
      <svg class="w-3 h-3 shrink-0 fill-current text-gray-400 transition-transform dark:text-gray-500" :class="dropdownOpen && 'rotate-180'" viewBox="0 0 12 12">
        <path d="M5.9 11.4L.5 6l1.4-1.4 4 4 4-4L11.3 6z" />
      </svg>
    </button>
    <transition
      enter-active-class="transition ease-out duration-200 transform"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-out duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-show="dropdownOpen" class="origin-top-right z-10 absolute top-full mt-2 min-w-56 overflow-hidden rounded-2xl border border-gray-200 bg-white/90 p-1.5 shadow-xl backdrop-blur-xl dark:border-white/10 dark:bg-gray-900/90 dark:shadow-[0_25px_50px_-20px_rgba(31,173,174,0.35)]" :class="align === 'right' ? 'right-0' : 'left-0'">
        <div class="mb-1 border-b border-gray-200 px-3 pt-2 pb-3 dark:border-white/10">
          <div class="font-display font-semibold text-gray-900 dark:text-white">{{ user.name }}</div>
          <div class="truncate text-xs text-gray-500">{{ user.email }}</div>
        </div>
        <ul
          ref="dropdown"
          @focusin="dropdownOpen = true"
          @focusout="dropdownOpen = false"
        >
          <li>
            <Link :href="route('profile.show')" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-primary-500/10 hover:text-primary-700 dark:text-gray-300 dark:hover:text-primary-300" @click="dropdownOpen = false">
              <UserCircleIcon class="h-4 w-4" />Profil
            </Link>
          </li>
          <li>
            <form @submit.prevent="logout">
              <button type="submit" class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-red-500/10 hover:text-red-600 dark:text-gray-300 dark:hover:text-red-400" @click="dropdownOpen = false">
                <ArrowRightStartOnRectangleIcon class="h-4 w-4" />Abmelden
              </button>
            </form>
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3';
import { UserCircleIcon, ArrowRightStartOnRectangleIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'DropdownProfile',
  props: ['align'],
  components: {
    Link,
    UserCircleIcon,
    ArrowRightStartOnRectangleIcon,
  },
  data() {
    return {
    }
  },  
  setup() {

    const user = computed(() => usePage().props.auth.user)
    const initials = computed(() => user.value.name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase())

    const dropdownOpen = ref(false)
    const trigger = ref(null)
    const dropdown = ref(null)

    const logout = () => {
      router.post(route('logout'));
    };

    // close on click outside
    const clickHandler = ({ target }) => {
      if (!dropdownOpen.value || dropdown.value.contains(target) || trigger.value.contains(target)) return
      dropdownOpen.value = false
    }

    // close if the esc key is pressed
    const keyHandler = ({ keyCode }) => {
      if (!dropdownOpen.value || keyCode !== 27) return
      dropdownOpen.value = false
    }

    onMounted(() => {
      document.addEventListener('click', clickHandler)
      document.addEventListener('keydown', keyHandler)
    })

    onUnmounted(() => {
      document.removeEventListener('click', clickHandler)
      document.removeEventListener('keydown', keyHandler)
    })

    return {
      user,
      initials,
      dropdownOpen,
      trigger,
      dropdown,
      logout,
    }
  }
}
</script>