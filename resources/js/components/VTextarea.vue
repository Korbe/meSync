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
    <textarea :id="id" class="form-textarea w-full" :rows="rows" :placeholder="placeholder"
      :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"></textarea>
  </div>
</template>
<script>
import VTooltip from './VTooltip.vue';

export default {
  components: {
    VTooltip
  },
  props: {
    modelValue: String,
    id: {
      type: String,
      default: "notes",
    },
    rows: {
      type: Number,
      default: 4,
    },
    label: {
      type: String,
      default: "",
    },
    placeholder: {
      type: String,
      default: "",
    },
    tooltip: {
      type: String,
      default: "",
    },
    mandatory: {
      type: Boolean,
      default: false,
    },
  },
};
</script>
