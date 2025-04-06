<template>
  <div class="relative">
    <flat-pickr
      class="form-input pl-9 dark:bg-gray-800 text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100 font-medium w-[15.5rem]"
      :config="config" v-model="date" />
    <div class="absolute inset-0 right-auto flex items-center pointer-events-none">
      <svg class="fill-current text-gray-400 dark:text-gray-500 ml-3" width="16" height="16" viewBox="0 0 16 16">
        <path d="M5 4a1 1 0 0 0 0 2h6a1 1 0 1 0 0-2H5Z" />
        <path
          d="M4 0a4 4 0 0 0-4 4v8a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4V4a4 4 0 0 0-4-4H4ZM2 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4Z" />
      </svg>
    </div>
  </div>
</template>

<script>
import flatPickr from "vue-flatpickr-component";
import equal from 'fast-deep-equal';

export default {
  name: "Datepicker",
  props: {
    modelValue: {
      type: Object,
      default: () => ({
        startDate: new Date(new Date().setDate(new Date().getDate() - 6)),
        endDate: new Date(),
      }),
    },
    align: {
      type: String,
      default: "",
    },
  },
  data() {
    return {
      date: [this.modelValue.startDate, this.modelValue.endDate], // Initialize with startDate and endDate
      config: {
        mode: "range",
        static: true,
        monthSelectorType: "static",
        dateFormat: "M j, Y",
        prevArrow:
          '<svg class="fill-current" width="7" height="11" viewBox="0 0 7 11"><path d="M5.4 10.8l1.4-1.4-4-4 4-4L5.4 0 0 5.4z" /></svg>',
        nextArrow:
          '<svg class="fill-current" width="7" height="11" viewBox="0 0 7 11"><path d="M1.4 10.8L0 9.4l4-4-4-4L1.4 0l5.4 5.4z" /></svg>',
        onReady: (selectedDates, dateStr, instance) => {
          instance.element.value = dateStr.replace("to", "-");
          const customClass = this.align ? this.align : "";
          instance.calendarContainer.classList.add(`flatpickr-${customClass}`);
        },
        onChange: (selectedDates, dateStr, instance) => {
          instance.element.value = dateStr.replace("to", "-");

          if (selectedDates.length === 2) {
            const [startDate, endDate] = selectedDates.map(date => new Date(date).getTime());
            const currentStartDate = new Date(this.modelValue.startDate).getTime();
            const currentEndDate = new Date(this.modelValue.endDate).getTime();

            if (startDate !== currentStartDate || endDate !== currentEndDate)
              this.$emit("update:modelValue", {
                startDate: selectedDates[0],
                endDate: selectedDates[1]
              });
          }
        }
      },
    };
  },
  watch: {
    modelValue: {
      handler(newVal) {
        if (!equal([newVal.startDate, newVal.endDate], this.date)) {
          this.date = [newVal.startDate, newVal.endDate];
        }
      },
      deep: true,
    }
  },
  components: {
    flatPickr,
  },
};
</script>