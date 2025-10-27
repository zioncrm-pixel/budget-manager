<script setup>
import { ref, computed } from 'vue'
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
            yearly: [],
            monthly: [],
            weekly: [],
            daily: [],
        }),
    },
    selectedYear: {
        type: Number,
        default: new Date().getFullYear(),
    },
    selectedMonth: {
        type: Number,
        default: new Date().getMonth() + 1,
    },
})

const timeframe = ref('monthly')

const timeframeOptions = [
    { value: 'yearly', label: 'שנתי' },
    { value: 'monthly', label: 'חודשי' },
    { value: 'weekly', label: 'שבועי' },
    { value: 'daily', label: 'יומי' },
]

const activeSeries = computed(() => {
    const dataset = props.chartData?.[timeframe.value] ?? []
    return Array.isArray(dataset) ? dataset : []
})

const labels = computed(() => activeSeries.value.map(item => item.label ?? item.key ?? ''))
const incomeValues = computed(() => activeSeries.value.map(item => Number(item.income) || 0))
const expenseValues = computed(() => activeSeries.value.map(item => Number(item.expense) || 0))

const maxValue = computed(() => {
    const values = [...incomeValues.value, ...expenseValues.value].map(value => Math.abs(Number(value) || 0))
    return values.length ? Math.max(...values) : 0
})

const barThickness = computed(() => {
    switch (timeframe.value) {
        case 'daily':
            return 12
        case 'weekly':
            return 20
        default:
            return 28
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

const chartPayload = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            label: 'הכנסות',
            backgroundColor: '#16a34a',
            borderRadius: 6,
            barThickness: barThickness.value,
            data: incomeValues.value,
        },
        {
            label: 'הוצאות',
            backgroundColor: '#dc2626',
            borderRadius: 6,
            barThickness: barThickness.value,
            data: expenseValues.value,
        },
    ],
}))

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
                callback: (value, index) => labels.value[index] ?? value,
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
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 text-right sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900">גרף הכנסות מול הוצאות</h3>
                <p class="text-sm text-gray-500">
                    השווה בין הכנסות והוצאות בתקופה שנבחרה באמצעות תצוגות שנתיות, חודשיות, שבועיות ויומיות.
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
        </div>

        <div v-if="chartPayload.labels.length" class="h-[26rem]">
            <Bar :data="chartPayload" :options="chartOptions" />
        </div>
        <div
            v-else
            class="flex h-[26rem] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-sm text-gray-500"
        >
            אין נתונים להצגה עבור טווח זה.
        </div>
    </div>
</template>
