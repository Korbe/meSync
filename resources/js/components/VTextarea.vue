<template>
    <textarea
      :id="id"
      class="form-textarea w-full focus:border-gray-300"
      :rows="rows"
      :placeholder="placeholder"
      v-model="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    ></textarea>
  </template>
  
  <script>
  export default {
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
      placeholder: {
        type: String,
        default: "",
      },
    },
  };
  </script>