<template>
  <!-- If href is provided, render Inertia Link, else fallback to standard button -->
  <Link
    v-if="href"
    :href="href"
    class="btn"
    :class="buttonClasses"
    :disabled="disabled"
  >
    <svg
      v-if="loading"
      class="animate-spin fill-current shrink-0"
      width="16"
      height="16"
      viewBox="0 0 16 16"
    >
      <path
        d="M8 16a7.928 7.928 0 01-3.428-.77l.857-1.807A6.006 6.006 0 0014 8c0-3.309-2.691-6-6-6a6.006 6.006 0 00-5.422 8.572l-1.806.859A7.929 7.929 0 010 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"
      />
    </svg>
    <component v-if="icon" :is="iconComponent" class="w-5 h-5 mr-2" />
    <span v-if="$slots.default">
      <slot></slot>
    </span>
  </Link>

  <!-- Standard button behavior if no href -->
  <button
    v-else
    :type="type"
    class="btn"
    :class="buttonClasses"
    :disabled="disabled"
    @click="$emit('click')"
  >
    <svg
      v-if="loading"
      class="animate-spin fill-current shrink-0"
      width="16"
      height="16"
      viewBox="0 0 16 16"
    >
      <path
        d="M8 16a7.928 7.928 0 01-3.428-.77l.857-1.807A6.006 6.006 0 0014 8c0-3.309-2.691-6-6-6a6.006 6.006 0 00-5.422 8.572l-1.806.859A7.929 7.929 0 010 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"
      />
    </svg>
    <component v-if="icon" :is="iconComponent" class="w-5 h-5 mr-2" />
    <span v-if="$slots.default">
      <slot></slot>
    </span>
  </button>
</template>


<script>
import * as HeroIcons from '@heroicons/vue/24/solid';

export default {
  props: {
    href: {
      type: String,
      default: "",
    },
    type: {
      type: String,
      default: "button",
      validator: (value) => ["button", "submit", "reset"].includes(value),
    },
    variant: {
      type: String,
      default: "primary",
      validator: (value) => ["primary", "secondary", "danger"].includes(value),
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    icon: {
      type: String,
      default: "",
    },
  },
  computed: {
    buttonClasses() {
      return {
        "cursor-pointer bg-primary-500 text-gray-100 hover:bg-primary-600 active:bg-primary-600 transition-all duration-150 dark:bg-primary-500 dark:text-gray-100 dark:hover:bg-primary-600 dark:active:bg-primary-600": this.variant === "primary",
        "cursor-pointer bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 text-gray-800 dark:text-gray-300 active:bg-gray-100 dark:active:bg-gray-700": this.variant === "secondary",
        "cursor-pointer bg-red-500 hover:bg-red-600 active:bg-red-700 text-white transition-all duration-150": this.variant === "danger",
        "disabled:border-gray-200 dark:disabled:border-gray-700 disabled:bg-white dark:disabled:bg-gray-800 disabled:text-gray-300 dark:disabled:text-gray-600 disabled:cursor-not-allowed": this.disabled,
      };
    },
    iconComponent() {
      return HeroIcons[this.icon] || null;
    },
  },
};
</script>