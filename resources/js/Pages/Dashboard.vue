<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TransactionAddModal from '@/Components/TransactionAddModal.vue'
import PeriodSelector from '@/Components/PeriodSelector.vue'
import { loadPeriod, savePeriod } from '@/utils/periodStorage'

const props = defineProps({
    user: Object,
    currentYear: Number,
    currentMonth: Number,
    totalIncome: Number,
    totalExpenses: Number,
    balance: Number,
    accountStatus: Number,
    categoriesWithBudgets: Array,
    cashFlowSources: Array,
})

const defaultYear = Number(props.currentYear) || new Date().getFullYear()
const defaultMonth = Number(props.currentMonth) || new Date().getMonth() + 1

const selectedYear = ref(defaultYear)
const selectedMonth = ref(defaultMonth)
const isTransactionModalOpen = ref(false)

const monthOptions = [
    { value: 1, label: 'ינואר' },
    { value: 2, label: 'פברואר' },
    { value: 3, label: 'מרץ' },
    { value: 4, label: 'אפריל' },
    { value: 5, label: 'מאי' },
    { value: 6, label: 'יוני' },
    { value: 7, label: 'יולי' },
    { value: 8, label: 'אוגוסט' },
    { value: 9, label: 'ספטמבר' },
    { value: 10, label: 'אוקטובר' },
    { value: 11, label: 'נובמבר' },
    { value: 12, label: 'דצמבר' },
]

const yearOptions = [2020, 2021, 2022, 2023, 2024, 2025, 2026]

const selectedMonthLabel = computed(() => {
    const current = monthOptions.find(option => String(option.value) === String(selectedMonth.value))
    return current?.label || selectedMonth.value
})

watch(
    () => props.currentYear,
    (value) => {
        selectedYear.value = Number(value) || new Date().getFullYear()
    }
)

watch(
    () => props.currentMonth,
    (value) => {
        selectedMonth.value = Number(value) || new Date().getMonth() + 1
    }
)

const persistPeriod = (year, month) => {
    if (typeof window === 'undefined') return
    savePeriod(year, month)
}

const navigateToPeriod = (year, month, options = {}) => {
    persistPeriod(year, month)
    router.visit(`/dashboard?year=${year}&month=${month}`, {
        preserveScroll: true,
        replace: true,
        ...options,
    })
}

const tryApplyStoredPeriod = () => {
    if (typeof window === 'undefined') {
        return
    }

    const stored = loadPeriod()
    const params = new URL(window.location.href).searchParams
    const queryYear = Number(params.get('year'))
    const queryMonth = Number(params.get('month'))
    const hasValidQuery = Number.isInteger(queryYear) && Number.isInteger(queryMonth)

    if (hasValidQuery) {
        persistPeriod(queryYear, queryMonth)
        return
    }

    if (!stored) {
        persistPeriod(selectedYear.value, selectedMonth.value)
        return
    }

    if (stored.year !== selectedYear.value || stored.month !== selectedMonth.value) {
        selectedYear.value = stored.year
        selectedMonth.value = stored.month
        navigateToPeriod(stored.year, stored.month)
    } else {
        persistPeriod(stored.year, stored.month)
    }
}

const handleYearUpdate = (value) => {
    selectedYear.value = value
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleMonthUpdate = (value) => {
    selectedMonth.value = value
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleToday = () => {
    const now = new Date()
    const year = now.getFullYear()
    const month = now.getMonth() + 1

    if (year === selectedYear.value && month === selectedMonth.value) {
        navigateToPeriod(year, month)
        return
    }

    selectedYear.value = year
    selectedMonth.value = month
    navigateToPeriod(year, month)
}

onMounted(() => {
    tryApplyStoredPeriod()
})

const openTransactionModal = () => {
    isTransactionModalOpen.value = true
}

const closeTransactionModal = () => {
    isTransactionModalOpen.value = false
}

const onTransactionAdded = () => {
    closeTransactionModal()
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}
</script>

<template>
    <Head title="דשבורד תקציב" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 text-right">
                <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        דשבורד תקציב ביתי
                    </h2>
                    <div class="flex flex-col items-end gap-1 text-sm text-gray-500">
                        <span>
                            בחירת תקופה:
                            <span class="font-semibold text-gray-900">
                                {{ selectedYear }} - {{ selectedMonthLabel }}
                            </span>
                        </span>
                        <PeriodSelector
                            :selected-year="selectedYear"
                            :selected-month="selectedMonth"
                            :year-options="yearOptions"
                            :month-options="monthOptions"
                            @update:year="handleYearUpdate"
                            @update:month="handleMonthUpdate"
                            @today="handleToday"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="p-4 flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 text-xl">💰</span>
                                </div>
                            </div>
                            <div class="flex-1 text-right">
                                <p class="text-sm font-medium text-gray-500">מצב העו"ש</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    {{ formatCurrency(props.accountStatus) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="p-4 flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-green-600 text-xl">📈</span>
                                </div>
                            </div>
                            <div class="flex-1 text-right">
                                <p class="text-sm font-medium text-gray-500">הכנסות</p>
                                <p class="text-2xl font-bold text-green-600">
                                    {{ formatCurrency(props.totalIncome) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="p-4 flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 text-xl">📉</span>
                                </div>
                            </div>
                            <div class="flex-1 text-right">
                                <p class="text-sm font-medium text-gray-500">הוצאות</p>
                                <p class="text-2xl font-bold text-red-600">
                                    {{ formatCurrency(props.totalExpenses) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="p-4 flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 text-xl">📊</span>
                                </div>
                            </div>
                            <div class="flex-1 text-right">
                                <p class="text-sm font-medium text-gray-500">יתרה</p>
                                <p class="text-2xl font-bold text-green-600">
                                    {{ formatCurrency(props.balance) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mt-8">
                    <div class="p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 text-right">
                                    ניהול תזרימים
                                </h3>
                                <p class="text-sm text-gray-500">
                                    הוסף תזרים חדש או עבור למסך ניהול התזרימים המלא.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 justify-end">
                                <Link
                                    :href="route('cashflow.index', { year: selectedYear, month: selectedMonth })"
                                    class="inline-flex items-center px-4 py-2 border border-indigo-500 text-indigo-600 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    מעבר לניהול תזרים
                                </Link>
                                <button 
                                    @click="openTransactionModal"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    הוסף תזרים
                                </button>
                                <Link
                                    :href="route('cashflow.import.index')"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-indigo-500 rounded-md font-semibold text-xs text-indigo-600 uppercase tracking-widest hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    ייבוא נתונים
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mt-8">
                    <div class="p-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 text-right">קטגוריות ותקציבים</h3>
                            <p class="text-sm text-gray-500">עבור למסך הייעודי כדי לראות ולערוך את כל התקציבים.</p>
                        </div>
                        <Link
                            :href="route('budgets.overview', { year: selectedYear, month: selectedMonth })"
                            class="inline-flex items-center px-4 py-2 bg-white border border-indigo-500 rounded-md font-semibold text-xs text-indigo-600 uppercase tracking-widest hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            ניהול קטגוריות ותקציבים
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <TransactionAddModal
            :show="isTransactionModalOpen"
            mode="create"
            :categories="props.categoriesWithBudgets"
            :cash-flow-sources="props.cashFlowSources"
            :budgets="[]"
            @close="closeTransactionModal"
            @transaction-added="onTransactionAdded"
        />
    </AuthenticatedLayout>
</template>
