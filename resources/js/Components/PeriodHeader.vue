<script setup>
import { computed, useSlots } from 'vue'
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
})

const emit = defineEmits(['update:year', 'update:month', 'today'])

const slots = useSlots()

const metricsWrapperClass = computed(() => {
    if (!props.metrics.length) {
        return null
    }

    return 'grid w-full grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3 lg:flex lg:flex-row lg:flex-1 lg:flex-nowrap lg:gap-3'
})

const summaryClass = computed(() => {
    if (!slots.summary) {
        return null
    }

    return ['w-full', props.summaryWrapperClass].filter(Boolean).join(' ')
})
</script>

<template>
    <div class="flex flex-col gap-3 text-right lg:flex-row lg:items-stretch lg:gap-4">
        <div class="flex items-stretch justify-end lg:flex-none">
            <div class="flex h-full w-full flex-col items-end gap-2 rounded-md border border-indigo-100 bg-white px-3 py-2 text-sm text-gray-500 shadow-sm lg:min-w-[190px]">
                <slot name="period-label">
                    <span>
                        {{ periodLabel }}
                        <span class="font-semibold text-gray-900">
                            {{ periodDisplay }}
                        </span>
                    </span>
                </slot>
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
        <!-- <div v-if="slots.summary && summaryOrder === 'start'" :class="summaryClass">
            <slot name="summary" />
        </div> -->

        <div  :class="summaryClass">
            <slot name="summary" />
        </div>
    </div>
</template>
