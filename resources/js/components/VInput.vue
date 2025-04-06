<template>
  <div>
    <!-- Label with optional tooltip and mandatory mark -->
    <div class="flex items-center justify-between" v-if="label">
      <label :for="id" class="block text-sm font-medium mb-1">
        {{ label }}
        <span v-if="mandatory" class="text-red-500">*</span>
      </label>

      <VTooltip v-if="tooltip" class="ml-2" bg="dark" size="md">
        <div class="text-sm text-gray-500">{{ tooltip }}</div>
      </VTooltip>
    </div>

    <!-- Input wrapper for prefix, suffix, and icon -->
    <div class="relative">
      <input 
        :id="id" 
        class="form-input w-full" 
        :class="inputClass" 
        :type="type" 
        :placeholder="placeholder"
        :required="mandatory" 
        :disabled="disabled"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
      />

      <!-- Prefix -->
      <div v-if="prefix" class="absolute inset-0 right-auto flex items-center pointer-events-none">
        <span class="text-sm text-gray-400 dark:text-gray-500 font-medium px-3">{{ prefix }}</span>
      </div>

      <!-- Suffix -->
      <div v-if="suffix" class="absolute inset-0 left-auto flex items-center pointer-events-none">
        <span class="text-sm text-gray-400 dark:text-gray-500 font-medium px-3">{{ suffix }}</span>
      </div>

      <!-- Icon -->
      <div v-if="icon" class="absolute inset-0 right-auto flex items-center pointer-events-none">
        <component :is="icon" class="w-5 h-5 text-gray-400 dark:text-gray-500 ml-3" />
      </div>
    </div>

    <!-- Supporting Text / Error / Success Messages -->
    <div v-if="supportingText" class="text-xs mt-1">{{ supportingText }}</div>
    <div v-if="error" class="text-xs mt-1 text-red-500">{{ error }}</div>
    <div v-if="success" class="text-xs mt-1 text-green-500">{{ success }}</div>
  </div>
</template>

<script>
import VTooltip from './VTooltip.vue';

export default {
  components: {
    VTooltip
  },
  props: {
    id: { type: String },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    mandatory: { type: Boolean, default: false },
    tooltip: { type: String, default: '' },
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
    icon: { type: [String, Object], default: null },
    supportingText: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
    modelValue: { type: String, default: '' }
  },
  computed: {
    inputClass() {
      return {
        'pl-12': this.prefix,
        'pr-8': this.suffix || this.icon,
        'border-red-300': this.error,
        'border-green-300': this.success,
        'dark:disabled:placeholder:text-gray-600 disabled:border-gray-200 dark:disabled:border-gray-700 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-600 disabled:cursor-not-allowed shadow-none': this.disabled
      };
    }
  }
};
</script>