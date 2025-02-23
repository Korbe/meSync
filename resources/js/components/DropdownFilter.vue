<template>
  <div class="relative inline-flex">
    <button
      ref="trigger"
      class="cursor-pointer btn px-2.5 bg-white dark:bg-gray-800 border-gray-200 hover:border-gray-300 dark:border-gray-700/60 dark:hover:border-gray-600 text-gray-400 dark:text-gray-500"
      aria-haspopup="true"
      @click.prevent="dropdownOpen = !dropdownOpen"
      :aria-expanded="dropdownOpen"
    >
      <span class="sr-only">Filter</span>
      <svg class="fill-current" width="16" height="16" viewBox="0 0 16 16">
        <path d="M0 3a1 1 0 0 1 1-1h14a1 1 0 1 1 0 2H1a1 1 0 0 1-1-1ZM3 8a1 1 0 0 1 1-1h8a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1ZM7 12a1 1 0 1 0 0 2h2a1 1 0 1 0 0-2H7Z" />
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
      <div
        v-show="dropdownOpen"
        class="origin-top-right z-10 absolute top-full left-0 right-auto min-w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 pt-1.5 rounded-lg shadow-lg overflow-hidden mt-1"
        :class="align === 'right' ? 'md:left-auto md:right-0' : 'md:left-0 md:right-auto'"
      >
        <div ref="dropdown">
          <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase pt-1.5 pb-2 px-3">
            Filter
          </div>
          <ul class="mb-4">
            <li
              v-for="(option, index) in localOptions"
              :key="option.value"
              class="py-1 px-3"
            >
              <label class="flex items-center">
                <input
                  type="checkbox"
                  class="form-checkbox"
                  v-model="localOptions[index].selected"
                  @change="onFilterChange(option)"
                />
                <span class="text-sm font-medium ml-2">
                  {{ option.label }}
                </span>
              </label>
            </li>
          </ul>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, watch, reactive } from 'vue'

export default {
  name: 'DropDownFilter',
  props: {
    align: {
      type: String,
      default: 'left'
    },
    // Erwartet ein Array von Objekten, z.B.:
    // [{ label: 'Direct VS Indirect', value: 'directIndirect', selected: false }, ...]
    options: {
      type: Array,
      default: () => []
    }
  },
  emits: ['update:options', 'filtersChanged'],
  setup(props, { emit }) {
    const dropdownOpen = ref(false)
    const trigger = ref(null)
    const dropdown = ref(null)
    // Erstelle eine lokale Kopie der Optionen, um diese reaktiv zu bearbeiten
    const localOptions = reactive(props.options.map(option => ({ ...option })))

    // Schließt Dropdown bei Klick außerhalb
    const clickHandler = ({ target }) => {
      if (
        !dropdownOpen.value ||
        dropdown.value.contains(target) ||
        trigger.value.contains(target)
      )
        return
      dropdownOpen.value = false
    }

    // Schließt Dropdown bei ESC-Taste
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

    const onFilterChange = (option) => {
      // Option wurde geändert, gib die aktualisierte Optionen-Liste an das Parent weiter
      emit('update:options', localOptions)
      emit('filtersChanged', localOptions)
    }

    const clearFilters = () => {
      localOptions.forEach(opt => (opt.selected = false))
      emit('update:options', localOptions)
      emit('filtersChanged', localOptions)
    }

    const applyFilters = () => {
      dropdownOpen.value = false
      emit('filtersChanged', localOptions)
    }

    // Falls sich die prop "options" ändert, aktualisiere localOptions
    watch(
      () => props.options,
      (newOptions) => {
        newOptions.forEach((option, idx) => {
          localOptions[idx] = { ...option }
        })
      }
    )

    return {
      dropdownOpen,
      trigger,
      dropdown,
      localOptions,
      onFilterChange,
      clearFilters,
      applyFilters
    }
  }
}
</script>
