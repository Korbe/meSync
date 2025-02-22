<template>
  <div class="w-full">
    <!-- Label with optional tooltip and mandatory mark -->
    <div class="flex items-center justify-between mb-2" v-if="label">
      <label :for="id" class="block text-sm font-medium">
        {{ label }}
        <span v-if="mandatory" class="text-red-500">*</span>
      </label>

      <VTooltip v-if="tooltip" class="ml-2" bg="dark" size="md">
        <div class="text-sm text-gray-500">{{ tooltip }}</div>
      </VTooltip>
    </div>

    <!-- Slider Input -->
    <div class="relative flex items-center space-x-2">
      <span class="text-sm font-medium">{{ modelValue }}</span>
      <input type="range" :id="id" class="w-full h-2 dark:bg-gray-800 rounded-lg cursor-pointer" :min="min" :max="max"
        :step="step" :value="modelValue" @input="onSliderInput" :disabled="disabled" />
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
    id: { type: String, required: false },
    label: { type: String, default: '' },
    tooltip: { type: String, default: '' },
    mandatory: { type: Boolean, default: false },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 100 },
    step: { type: Number, default: 1 },
    disabled: { type: Boolean, default: false },
    modelValue: { type: Number, default: 0 },
    supportingText: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' }
  },
  methods: {
    onSliderInput(event) {
      this.$emit('update:modelValue', Number(event.target.value));
    }
  }
};
</script>

<style scoped>
input[type="range"] {
  -webkit-appearance: none;
  appearance: none;
  height: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

input[type="range"]:focus {
  outline: none;
}

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  background: #4CAF50;
  border-radius: 50%;
  cursor: pointer;
}

input[type="range"]:disabled::-webkit-slider-thumb {
  background: #ccc;
}

input[type="range"]::-moz-range-thumb {
  width: 20px;
  height: 20px;
  background: #4CAF50;
  border-radius: 50%;
  cursor: pointer;
}

input[type="range"]:disabled::-moz-range-thumb {
  background: #ccc;
}
</style>
