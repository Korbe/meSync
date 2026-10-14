<template>
  <div>
    <label class="flex items-center">
      <input 
        type="checkbox" 
        :name="name"
        class="form-checkbox rounded border-gray-300 text-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:focus:ring-offset-gray-950" 
        :class="{ 'disabled:border-gray-200 dark:disabled:border-gray-700 disabled:bg-gray-100 dark:disabled:bg-gray-800': disabled }"
        :checked="modelValue"
        :disabled="disabled"
        @change="$emit('update:modelValue', $event.target.checked)"
      />
      <span v-if="label || $slots.default" class="text-sm ml-2"><slot>{{ label }}</slot></span>
    </label>
  </div>
</template>

<script>
export default {
  props: {
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    name: { type: String, default: undefined },
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