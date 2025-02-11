<template>
  <div>
    <label class="flex items-center">
      <input 
        type="checkbox" 
        class="form-checkbox" 
        :class="{ 'disabled:border-gray-200 dark:disabled:border-gray-700 disabled:bg-gray-100 dark:disabled:bg-gray-800': disabled }"
        :checked="modelValue"
        :disabled="disabled"
        @change="$emit('update:modelValue', $event.target.checked)"
      />
      <span class="text-sm ml-2">{{ label }}</span>
    </label>
  </div>
</template>

<script>
export default {
  props: {
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false }
  }
};
</script>

<style scoped>
.form-checkbox {
  width: 16px;
  height: 16px;
}
</style>