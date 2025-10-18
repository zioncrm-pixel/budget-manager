<script setup>
const props = defineProps({
    selectedYear: {
        type: Number,
        required: true,
    },
    selectedMonth: {
        type: Number,
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
    disabled: {
        type: Boolean,
        default: false,
    },
    showTodayButton: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:year', 'update:month', 'today'])

const handleYearChange = (event) => {
    const value = parseInt(event.target.value, 10)
    if (!Number.isNaN(value)) {
        emit('update:year', value)
    }
}

const handleMonthChange = (event) => {
    const value = parseInt(event.target.value, 10)
    if (!Number.isNaN(value)) {
        emit('update:month', value)
    }
}

const handleTodayClick = () => {
    emit('today')
}
</script>

<template>
    <div class="flex items-center gap-3 flex-row-reverse">
        <div class="relative">
            <select
                :value="selectedYear"
                @change="handleYearChange"
                :disabled="disabled"
                class="appearance-none border border-gray-300 rounded-md px-3 py-2 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <option v-for="year in yearOptions" :key="year" :value="year">
                    {{ year }}
                </option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                ▼
            </span>
        </div>

        <div class="relative">
            <select
                :value="selectedMonth"
                @change="handleMonthChange"
                :disabled="disabled"
                class="appearance-none border border-gray-300 rounded-md px-3 py-2 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <option
                    v-for="option in monthOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                ▼
            </span>
        </div>

        <button
            v-if="showTodayButton"
            type="button"
            class="inline-flex items-center rounded-md border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-50 disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="disabled"
            @click="handleTodayClick"
        >
            היום
        </button>
    </div>
</template>
