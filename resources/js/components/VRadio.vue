<template>
    <div>
      <label class="flex items-center">
        <input 
          type="radio" 
          class="form-radio" 
          :class="{ 'disabled:border-gray-200 dark:disabled:border-gray-700 disabled:bg-gray-100 dark:disabled:bg-gray-800': disabled }"
          :checked="modelValue === value"
          :disabled="disabled"
          @change="$emit('update:modelValue', value)"
          :name="name"
        />
        <span class="text-sm ml-2">{{ label }}</span>
      </label>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      modelValue: { type: [String, Number], required: true },
      value: { type: [String, Number], required: true },
      label: { type: String, required: true },
      name: { type: String, required: true },
      disabled: { type: Boolean, default: false }
    }
  };
  </script>
  
  <style scoped>
  .form-radio {
    width: 16px;
    height: 16px;
  }
  </style>