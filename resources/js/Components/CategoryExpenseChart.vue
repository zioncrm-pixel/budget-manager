<script setup>
import { ref, computed, watch } from 'vue'
import { Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Title,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend, Title)

const props = defineProps({
    chartData: {
        type: Object,
        default: () => ({
            yearly: { labels: [], datasets: [] },
            monthly: { labels: [], datasets: [] },
            weekly: { labels: [], datasets: [] },
            daily: { labels: [], datasets: [] },
        }),
    },
})

const timeframe = ref('monthly')

const showUncategorized = ref(false)
const hiddenCategoryIds = ref(new Set())

const timeframeOptions = [
    { value: 'yearly', label: 'שנתי' },
    { value: 'monthly', label: 'חודשי' },
    { value: 'weekly', label: 'שבועי' },
    { value: 'daily', label: 'יומי' },
]

const activeSeries = computed(() => {
    const payload = props.chartData?.[timeframe.value]
    if (!payload) {
        return { labels: [], datasets: [] }
    }

    const labels = Array.isArray(payload.labels) ? payload.labels : []
    const datasets = Array.isArray(payload.datasets) ? payload.datasets : []

    return { labels, datasets }
})

const labels = computed(() => activeSeries.value.labels)
const rawDatasets = computed(() => activeSeries.value.datasets || [])

const visibleDatasets = computed(() =>
    rawDatasets.value.filter((dataset) => {
        const isUncategorizedHidden = dataset.categoryId === 'uncategorized' && !showUncategorized.value
        const isLegendHidden = hiddenCategoryIds.value.has(dataset.categoryId)
        return !isUncategorizedHidden && !isLegendHidden
    })
)

const hasData = computed(() =>
    visibleDatasets.value.some(dataset => Array.isArray(dataset.data) && dataset.data.some(value => Number(value) > 0))
)

const maxValue = computed(() => {
    const values = visibleDatasets.value
        .flatMap(dataset => (Array.isArray(dataset.data) ? dataset.data : []))
        .map(value => Math.abs(Number(value) || 0))

    return values.length ? Math.max(...values) : 0
})

const barThickness = computed(() => {
    switch (timeframe.value) {
        case 'daily':
            return 8
        case 'weekly':
            return 12
        case 'yearly':
            return 20
        default:
            return 16
    }
})

const yStepSize = computed(() => (timeframe.value === 'daily' ? 100 : 500))

const ySuggestedMax = computed(() => {
    const step = yStepSize.value || 1
    const max = maxValue.value
    if (max <= 0) {
        return step
    }

    return Math.ceil(max / step) * step
})

const chartDatasets = computed(() =>
    rawDatasets.value.map(dataset => {
        const hiddenByCheckbox = dataset.categoryId === 'uncategorized' && !showUncategorized.value
        const hiddenByLegend = hiddenCategoryIds.value.has(dataset.categoryId)

        return {
            categoryId: dataset.categoryId,
            label: dataset.label || 'קטגוריה',
            backgroundColor: dataset.color || '#6366F1',
            borderRadius: 6,
            barThickness: barThickness.value,
            hidden: hiddenByCheckbox || hiddenByLegend,
            data: Array.isArray(dataset.data) ? dataset.data : [],
        }
    })
)

const chartPayload = computed(() => ({
    labels: labels.value,
    datasets: chartDatasets.value,
}))

const defaultLegendOnClick = ChartJS.defaults?.plugins?.legend?.onClick

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: {
                color: '#111827',
                usePointStyle: true,
                padding: 20,
            },
            onClick: (event, legendItem, legend) => {
                const chart = legend.chart
                const datasetIndex = legendItem.datasetIndex
                const dataset = chart.data?.datasets?.[datasetIndex]
                const categoryId = dataset?.categoryId

                if (categoryId) {
                    const isVisible = chart.isDatasetVisible(datasetIndex)

                    if (defaultLegendOnClick) {
                        defaultLegendOnClick.call(chart, event, legendItem, legend)
                    } else {
                        if (isVisible) {
                            chart.hide(datasetIndex)
                        } else {
                            chart.show(datasetIndex)
                        }
                    }

                    const nextHidden = new Set(hiddenCategoryIds.value)
                    if (isVisible) {
                        nextHidden.add(categoryId)
                    } else {
                        nextHidden.delete(categoryId)
                    }
                    hiddenCategoryIds.value = nextHidden
                } else if (defaultLegendOnClick) {
                    defaultLegendOnClick.call(chart, event, legendItem, legend)
                }
            },
        },
        tooltip: {
            rtl: true,
            callbacks: {
                label: (context) => {
                    const value = Number(context.parsed.y) || 0
                    return `${context.dataset.label}: ${value.toLocaleString('he-IL')} ₪`
                },
            },
        },
    },
    scales: {
        x: {
            ticks: {
                color: '#4b5563',
            },
            grid: {
                display: false,
            },
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#4b5563',
                callback: (value) => `${Number(value).toLocaleString('he-IL')}`,
                stepSize: yStepSize.value,
            },
            suggestedMax: ySuggestedMax.value,
        },
    },
}))

const setTimeframe = (value) => {
    timeframe.value = value
}

watch(rawDatasets, (datasets) => {
    const existingIds = new Set(datasets.map(dataset => dataset.categoryId))
    const nextHidden = new Set()

    hiddenCategoryIds.value.forEach((categoryId) => {
        if (existingIds.has(categoryId)) {
            nextHidden.add(categoryId)
        }
    })

    hiddenCategoryIds.value = nextHidden
})
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 text-right sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900">גרף הוצאות לפי קטגוריה</h3>
                <p class="text-sm text-gray-500">
                    ניתוח סכומי הוצאות לפי קטגוריות שונות בתצוגות שנתיות, חודשיות, שבועיות ויומיות.
                </p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    v-for="option in timeframeOptions"
                    :key="option.value"
                    type="button"
                    class="inline-flex items-center rounded-full border px-3 py-1 text-sm transition"
                    :class="timeframe === option.value
                        ? 'border-indigo-500 bg-indigo-100 text-indigo-700'
                        : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 hover:text-indigo-600'"
                    @click="setTimeframe(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input
                    v-model="showUncategorized"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                הצג ללא קטגוריה
            </label>
        </div>

        <div v-if="hasData" class="h-[28rem]">
            <Bar :data="chartPayload" :options="chartOptions" />
        </div>
        <div
            v-else
            class="flex h-[28rem] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-sm text-gray-500"
        >
            אין נתונים להצגה עבור טווח זה.
        </div>
    </div>
</template>
