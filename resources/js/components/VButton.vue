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
    @click="$emit('click', $event)"
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
  emits: ['click'],
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
        "cursor-pointer bg-primary-500 text-white hover:bg-primary-600 active:bg-primary-700 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)] focus:ring-primary-500": this.variant === "primary",
        "cursor-pointer bg-white border-gray-200! text-gray-700 hover:border-gray-300! hover:bg-gray-50 focus:ring-primary-500 dark:bg-white/5 dark:border-white/10! dark:text-gray-200 dark:hover:border-white/20! dark:hover:bg-white/10": this.variant === "secondary",
        "cursor-pointer bg-red-600 text-white hover:bg-red-500 active:bg-red-700 hover:shadow-[0_8px_24px_-8px_rgba(239,68,68,0.7)] focus:ring-red-500": this.variant === "danger",
        "cursor-not-allowed! opacity-50 shadow-none!": this.disabled,
      };
    },
    iconComponent() {
      return HeroIcons[this.icon] || null;
    },
  },
};
</script>