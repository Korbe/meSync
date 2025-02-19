<template>
  <canvas ref="canvas" :width="width" :height="height"></canvas>
</template>

<script>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Chart, LineController, LineElement, Filler, PointElement, LinearScale, TimeScale, Tooltip } from 'chart.js'
import 'chartjs-adapter-moment'
import { useDark } from '@vueuse/core'
import { getChartColors, chartAreaGradient } from '@/services/ChartjsConfig'
import { getCssVariable, adjustColorOpacity  } from '@/utils/Utils'

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
    }
  },
  setup(props) {
    const canvas = ref(null)
    let chart = null
    const darkMode = useDark()
    const { tooltipBodyColor, tooltipBgColor, tooltipBorderColor } = getChartColors()

    const initChart = () => {
      const ctx = canvas.value
      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: props.labels,
          datasets: [
            {
              label: 'Score', // Diese Bezeichnung kannst du anpassen
              data: props.data,
              fill: true,
              backgroundColor: function (context) {
                const chart = context.chart
                const { ctx, chartArea } = chart
                return chartAreaGradient(ctx, chartArea, [
                  { stop: 0, color: adjustColorOpacity(getCssVariable('--color-primary-500'), 0) },
                  { stop: 1, color: adjustColorOpacity(getCssVariable('--color-primary-500'), 0.2) },
                ])
              },
              borderColor: getCssVariable('--color-primary-500'),
              borderWidth: 2,
              pointRadius: 0,
              pointHoverRadius: 3,
              pointBackgroundColor: getCssVariable('--color-primary-500'),
              pointHoverBackgroundColor: getCssVariable('--color-primary-500'),
              pointBorderWidth: 0,
              pointHoverBorderWidth: 0,
              clip: 20,
              tension: 0.2,
            },
          ],
        },
        options: {
          layout: {
            padding: 20,
          },
          scales: {
            y: {
              display: false,
              beginAtZero: true,
            },
            x: {
              type: 'time',
              time: {
                unit: 'day', // oder 'week' für Wochen
                stepSize: 3,  // Nur alle 3 Tage ein Label (beim 'day' Unit)
                tooltipFormat: 'DD-MM-YYYY',
                displayFormats: {
                  day: 'DD-MM', // Format für das Tageslabel
                  week: 'DD-MM', // Format für das Wochenlabel
                },
              },
              ticks: {
                autoSkip: true,
                maxTicksLimit: 5, // Maximal 5 Ticks anzeigen
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

    watch(() => props.data, () => {
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
