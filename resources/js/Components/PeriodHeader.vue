<script setup>
import { computed, useSlots } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PeriodSelector from '@/Components/PeriodSelector.vue'

const props = defineProps({
    metrics: {
        type: Array,
        default: () => [],
    },
    selectedYear: {
        type: Number,
        required: true,
    },
    selectedMonth: {
        type: Number,
        required: true,
    },
    periodDisplay: {
        type: String,
        required: true,
    },
    yearOptions: {
        type: Array,
        default: () => [],
    },
    monthOptions: {
        type: Array,
        default: () => [],
    },
    periodLabel: {
        type: String,
        default: 'בחירת תקופה:',
    },
    summaryOrder: {
        type: String,
        default: 'end', // 'start' | 'end'
    },
    summaryWrapperClass: {
        type: String,
        default: 'lg:flex-1',
    },
    showTodayButton: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showBudgetSummary: {
        type: Boolean,
        default: true,
    },
    budgetSummaryPlacement: {
        type: String,
        default: 'end',
        validator: value => ['start', 'end'].includes(value),
    },
    budgetSummaryFullWidth: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:year', 'update:month', 'today'])

const page = usePage()
const slots = useSlots()

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}

const categoriesWithBudgets = computed(() => {
    const categories = page?.props?.categoriesWithBudgets
    return Array.isArray(categories) ? categories : []
})

const totalIncomeAmount = computed(() => Number(page?.props?.totalIncome ?? 0))

const totalPlannedBudget = computed(() => {
    return categoriesWithBudgets.value.reduce((sum, category) => {
        const planned = category?.budget?.planned_amount
        const parsed = planned !== undefined && planned !== null ? parseFloat(planned) : 0
        return Number.isFinite(parsed) ? sum + parsed : sum
    }, 0)
})

const remainingIncomeAfterBudget = computed(() => totalIncomeAmount.value - totalPlannedBudget.value)

const budgetCoveragePercentage = computed(() => {
    const income = totalIncomeAmount.value
    if (income <= 0) {
        return totalPlannedBudget.value > 0 ? 100 : 0
    }

    const ratio = (totalPlannedBudget.value / income) * 100
    return Math.round(Math.min(Math.max(ratio, 0), 999))
})

const budgetCoveragePercentageDisplay = computed(() => budgetCoveragePercentage.value.toFixed(0))

const totalBudgetProgressWidth = computed(() => `${Math.min(budgetCoveragePercentage.value, 100)}%`)

const totalBudgetProgressBarClass = computed(() => {
    if (budgetCoveragePercentage.value > 110) return 'bg-red-500'
    if (budgetCoveragePercentage.value > 95) return 'bg-orange-400'
    if (budgetCoveragePercentage.value > 75) return 'bg-yellow-400'
    return 'bg-indigo-500'
})

const remainingIncomeClass = computed(() => {
    if (remainingIncomeAfterBudget.value < 0) return 'text-red-600 font-semibold'
    if (remainingIncomeAfterBudget.value === 0) return 'text-gray-600 font-semibold'
    return 'text-green-600 font-semibold'
})

const resolvedYear = computed(() => {
    const numericPropYear = Number(props.selectedYear)
    if (Number.isFinite(numericPropYear)) {
        return numericPropYear
    }

    const pageYear = Number(page?.props?.currentYear)
    if (Number.isFinite(pageYear)) {
        return pageYear
    }

    return new Date().getFullYear()
})

const selectedMonthLabel = computed(() => {
    const month = Number(props.selectedMonth)
    if (!Number.isFinite(month) || month < 1 || month > 12) {
        return props.selectedMonth
    }

    try {
        const date = new Date(Date.UTC(resolvedYear.value, month - 1, 1))
        return new Intl.DateTimeFormat('he-IL', { month: 'long' }).format(date)
    } catch (error) {
        return props.selectedMonth
    }
})

const metricsWrapperClass = computed(() => {
    if (!props.metrics.length) {
        return null
    }

    return 'grid w-full grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3 lg:flex lg:flex-row lg:flex-1 lg:flex-nowrap lg:gap-3'
})

const hasSummarySlot = computed(() => Boolean(slots.summary))

const showBudgetSummaryCard = computed(() => props.showBudgetSummary)

const shouldRenderSummary = computed(() => showBudgetSummaryCard.value || hasSummarySlot.value)

const summaryClass = computed(() => {
    if (!shouldRenderSummary.value) {
        return null
    }

    return ['w-full', props.summaryWrapperClass].filter(Boolean).join(' ')
})

const summaryContainerLayoutClass = computed(() => {
    return 'flex h-full flex-col gap-3 lg:flex-row lg:items-center lg:gap-4'
})

const summaryCardPlacementClass = computed(() => {
    if (props.budgetSummaryPlacement === 'start') {
        return 'lg:order-last'
    }

    return ''
})

const budgetSummaryFullWidth = computed(() => props.budgetSummaryFullWidth !== false)
</script>

<template>
    <div class="flex flex-col gap-3 text-right lg:flex-row lg:items-stretch lg:gap-4">
        <div class="flex items-stretch justify-end lg:flex-none">
            <div class="flex h-full w-full flex-col items-end gap-2 rounded-md border border-indigo-100 bg-white px-3 py-2 text-sm text-gray-500 shadow-sm lg:min-w-[190px]">
                <div class="w-full text-right">
                    <slot dir="rtl" name="period-label">
                        <span>
                            {{ periodLabel }}
                            <span class="font-semibold text-gray-900">
                                {{ periodDisplay }}
                            </span>
                        </span>
                    </slot>
                </div>
                <PeriodSelector
                    :selected-year="selectedYear"
                    :selected-month="selectedMonth"
                    :year-options="yearOptions"
                    :month-options="monthOptions"
                    :disabled="disabled"
                    :show-today-button="showTodayButton"
                    @update:year="value => emit('update:year', value)"
                    @update:month="value => emit('update:month', value)"
                    @today="emit('today')"
                />
                <slot name="period-extra" />
            </div>
        </div>

        <div v-if="metricsWrapperClass" :class="metricsWrapperClass">
            <div
                v-for="metric in metrics"
                :key="metric.key || metric.label"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-right shadow-sm lg:flex-1 lg:min-w-[140px]"
            >
                <p class="text-xs text-gray-500">
                    {{ metric.label }}
                </p>
                <p class="text-base font-semibold" :class="metric.valueClass">
                    {{ metric.value }}
                </p>
                <p v-if="metric.helper" class="text-xs text-gray-400">
                    {{ metric.helper }}
                </p>
            </div>
        </div>

        <div v-if="shouldRenderSummary" :class="summaryClass">
            <div :class="summaryContainerLayoutClass">
                <div
                    v-if="showBudgetSummaryCard"
                    class="h-full rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 shadow-sm"
                    :class="[summaryCardPlacementClass, { 'w-full': budgetSummaryFullWidth }]"
                >
                    <div class="flex w-full flex-col gap-2 text-right sm:flex-row sm:items-center sm:justify-between lg:flex-col lg:items-end">
                        <div class="w-full text-right">
                            <p class="text-xs text-indigo-700">
                                סך התקציבים המתוכננים עבור {{ selectedMonthLabel }} {{ resolvedYear }}.
                            </p>
                        </div>
                        <div class="w-full text-right text-sm font-semibold text-indigo-900">
                            {{ formatCurrency(totalPlannedBudget) }} ₪ מתוך {{ formatCurrency(totalIncomeAmount) }} ₪ הכנסות
                        </div>
                    </div>
                    <div class="mt-3 h-2.5 w-full rounded-full bg-white/70">
                        <div
                            class="h-2.5 rounded-full transition-all duration-500"
                            :class="totalBudgetProgressBarClass"
                            :style="{ width: totalBudgetProgressWidth }"
                        ></div>
                    </div>
                    <div class="mt-2 flex flex-col gap-1 text-xs text-indigo-900 sm:flex-row sm:items-center sm:justify-between">
                        <span>כיסוי תקציב: {{ budgetCoveragePercentageDisplay }}%</span>
                        <span :class="remainingIncomeClass">
                            נותר להקצות: {{ formatCurrency(remainingIncomeAfterBudget) }} ₪
                        </span>
                    </div>
                </div>

                <slot name="summary" />
            </div>
        </div>
    </div>
</template>
