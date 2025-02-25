<template>
  <canvas ref="canvas" :width="width" :height="height"></canvas>
</template>

<script>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Chart, LineController, LineElement, Filler, PointElement, LinearScale, TimeScale, Tooltip } from 'chart.js'
import 'chartjs-adapter-moment'
import { useDark } from '@vueuse/core'
import { getChartColors, chartAreaGradient } from '@/services/ChartjsConfig'
import { getCssVariable, adjustColorOpacity } from '@/utils/Utils'

Chart.register(LineController, LineElement, Filler, PointElement, LinearScale, TimeScale, Tooltip)

export default {
  name: 'LineChart',
  props: {
    data: {
      type: Array,
      required: true
    },
    labels: {
      type: Array,
      required: true
    },
    width: {
      type: Number,
      default: 400
    },
    height: {
      type: Number,
      default: 200
    },
    color: {
      type: String,
      default: 'primary-500' // Standard als CSS-Variable-Name
    }
  },
  setup(props) {
    const canvas = ref(null)
    let chart = null
    const darkMode = useDark()
    const { tooltipBodyColor, tooltipBgColor, tooltipBorderColor } = getChartColors()

    // Funktion zur Umwandlung in eine echte CSS-Variable
    const resolveCssVariable = (variableName) => getCssVariable(`--color-${variableName}`)

    const initChart = () => {
      const primaryColor = resolveCssVariable(props.color)

      const ctx = canvas.value
      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: props.labels,
          datasets: [
            {
              label: 'Score',
              data: props.data,
              fill: true,
              backgroundColor: function (context) {
                const { ctx, chartArea } = context.chart
                return chartAreaGradient(ctx, chartArea, [
                  { stop: 0, color: adjustColorOpacity(primaryColor, 0) },
                  { stop: 1, color: adjustColorOpacity(primaryColor, 0.2) },
                ])
              },
              borderColor: primaryColor,
              borderWidth: 2,
              pointRadius: 0,
              pointHoverRadius: 3,
              pointBackgroundColor: primaryColor,
              pointHoverBackgroundColor: primaryColor,
              pointBorderWidth: 0,
              pointHoverBorderWidth: 0,
              clip: 20,
              tension: 0.2,
            },
          ],
        },
        options: {
          layout: { padding: 20 },
          scales: {
            y: {
              display: false,
              beginAtZero: true,
            },
            x: {
              type: 'time',
              time: {
                unit: 'day',
                stepSize: 3,
                tooltipFormat: 'DD-MM-YYYY',
                displayFormats: {
                  day: 'DD-MM',
                  week: 'DD-MM',
                },
              },
              ticks: {
                autoSkip: true,
                maxTicksLimit: 5,
              },
            },
          },
          plugins: {
            tooltip: {
              callbacks: {
                title: () => false,
                label: (context) => `${context.label} - ${context.parsed.y}`,
              },
              bodyColor: darkMode.value ? tooltipBodyColor.dark : tooltipBodyColor.light,
              backgroundColor: darkMode.value ? tooltipBgColor.dark : tooltipBgColor.light,
              borderColor: darkMode.value ? tooltipBorderColor.dark : tooltipBorderColor.light,
            },
            legend: {
              display: false,
            },
          },
          interaction: {
            intersect: false,
            mode: 'nearest',
          },
          maintainAspectRatio: false,
          resizeDelay: 200,
        },
      })
    }

    onMounted(() => initChart())
    onUnmounted(() => chart.destroy())

    watch([() => props.data, () => props.color], () => {
      if (chart) {
        chart.destroy()
        chart = null
      }
      initChart()
    }, { deep: true })

    return {
      canvas,
    }
  },
}
</script>
