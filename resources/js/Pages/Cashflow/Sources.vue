<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PeriodSelector from '@/Components/PeriodSelector.vue'
import CashFlowSourceModal from '@/Components/CashFlowSourceModal.vue'
import CashFlowSourceTransactionsModal from '@/Components/CashFlowSourceTransactionsModal.vue'

const props = defineProps({
    user: Object,
    currentYear: Number,
    currentMonth: Number,
    totalIncome: Number,
    totalExpenses: Number,
    balance: Number,
    cashFlowSourcesWithStats: Array,
    cashFlowSources: Array,
    allCashFlowSources: Array,
    allCategories: Array,
    budgetsForMonth: Array,
})

const selectedYear = ref(Number(props.currentYear) || new Date().getFullYear())
const selectedMonth = ref(Number(props.currentMonth) || new Date().getMonth() + 1)
const isSourceModalOpen = ref(false)
const modalMode = ref('create')
const selectedSource = ref(null)
const isTransactionsModalOpen = ref(false)
const transactionsSource = ref(null)
const deletingSourceId = ref(null)
const duplicatingSourceId = ref(null)

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

const navigateToPeriod = (year, month) => {
    router.visit(`/cashflow/sources?year=${year}&month=${month}`, {
        preserveScroll: true,
        replace: true,
    })
}

const handleYearUpdate = (value) => {
    selectedYear.value = value
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleMonthUpdate = (value) => {
    selectedMonth.value = value
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}

const sourceTypeLabel = (type) => (type === 'income' ? 'הכנסה' : 'הוצאה')

const sourceTypeBadgeClass = (type) => (type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')

const formatMonthlyAmount = (source) => {
    const amount = Number(source?.monthly_total_amount || 0)
    const prefix = source?.type === 'income' ? '+' : '-'
    return `${prefix}${formatCurrency(Math.abs(amount))}`
}

const monthlyAmountClass = (source) => (source?.type === 'income' ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold')

const formatDateLabel = (date) => {
    if (!date) return 'אין נתונים'
    return new Date(date).toLocaleDateString('he-IL')
}

const openNewSourceModal = () => {
    modalMode.value = 'create'
    selectedSource.value = null
    isSourceModalOpen.value = true
}

const openEditSourceModal = (source) => {
    modalMode.value = 'edit'
    selectedSource.value = source
    isSourceModalOpen.value = true
}

const closeSourceModal = () => {
    isSourceModalOpen.value = false
    selectedSource.value = null
}

const handleSourceSaved = () => {
    closeSourceModal()
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleSourceDeleted = () => {
    closeSourceModal()
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const confirmSourceDelete = (source) => {
    if (!source) {
        return
    }

    if (!confirm('למחוק את מקור התזרים הזה? כל העסקאות יישארו משויכות ללא מקור.')) {
        return
    }

    deletingSourceId.value = source.id

    router.delete(route('cashflow.sources.destroy', source.id), {
        preserveScroll: true,
        onFinish: () => {
            deletingSourceId.value = null
        },
        onSuccess: () => {
            navigateToPeriod(selectedYear.value, selectedMonth.value)
        },
    })
}

const openTransactionsModal = (source) => {
    transactionsSource.value = source
    isTransactionsModalOpen.value = true
}

const duplicateSource = (source) => {
    if (!source) {
        return
    }

    duplicatingSourceId.value = source.id

    router.post(
        route('cashflow.sources.duplicate', source.id),
        {
            year: Number(selectedYear.value),
            month: Number(selectedMonth.value),
            planned_amount: source.budget?.planned_amount ?? null,
            with_transactions: false,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                duplicatingSourceId.value = null
            },
            onSuccess: () => {
                navigateToPeriod(selectedYear.value, selectedMonth.value)
            },
        }
    )
}

const closeTransactionsModal = () => {
    isTransactionsModalOpen.value = false
    transactionsSource.value = null
}

const hasSources = computed(() => Array.isArray(props.cashFlowSourcesWithStats) && props.cashFlowSourcesWithStats.length > 0)
</script>

<template>
    <Head title="מקורות תזרים" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 text-right">
                <div class="flex w-full flex-row items-start gap-6 text-right">
                    <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-md border border-gray-200 bg-white px-4 py-3 text-right">
                            <p class="text-xs text-gray-500">יתרה</p>
                            <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(props.balance) }} ₪</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-4 py-3 text-right">
                            <p class="text-xs text-gray-500">סה"כ הכנסות</p>
                            <p class="text-lg font-semibold text-green-600">{{ formatCurrency(props.totalIncome) }} ₪</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-4 py-3 text-right">
                            <p class="text-xs text-gray-500">סה"כ הוצאות</p>
                            <p class="text-lg font-semibold text-red-600">{{ formatCurrency(props.totalExpenses) }} ₪</p>
                        </div>
                    </div>
                    <div class="ml-auto flex flex-col items-end gap-3 sm:flex-row sm:items-center sm:gap-6">
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
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 text-right">ניהול מקורות תזרים</h3>
                            <p class="text-sm text-gray-500">הגדר תקציב לכל מקור תזרים ולעקוב אחר העסקאות המשויכות אליו.</p>
                        </div>
                        <button
                            @click="openNewSourceModal"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            הוסף מקור
                        </button>
                    </div>

                    <div class="p-6">
                        <div v-if="hasSources" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="source in props.cashFlowSourcesWithStats"
                                :key="source.id"
                                class="flex h-full flex-col gap-4 rounded-lg border-2 border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
                                :class="{
                                    'border-indigo-300 bg-indigo-50': source.is_active,
                                    'opacity-75': source.is_active === false,
                                }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl">{{ source.icon || (source.type === 'income' ? '💰' : '💸') }}</span>
                                        <div class="text-right">
                                            <h4 class="text-lg font-semibold text-gray-900">{{ source.name }}</h4>
                                            <div class="mt-2 flex items-center justify-end gap-2 text-xs">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 font-medium" :class="sourceTypeBadgeClass(source.type)">
                                                    {{ sourceTypeLabel(source.type) }}
                                                </span>
                                                <span
                                                    v-if="source.is_active === false"
                                                    class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-1 font-medium text-gray-700"
                                                >
                                                    לא פעיל
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            class="inline-flex items-center rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-50"
                                            @click.stop="openEditSourceModal(source)"
                                        >
                                            ✏️ עריכה
                                        </button>
                                        <button
                                            class="inline-flex items-center rounded-md border border-green-200 px-3 py-1.5 text-xs font-semibold text-green-600 transition-colors hover:bg-green-50 disabled:opacity-60"
                                            :disabled="duplicatingSourceId === source.id"
                                            @click.stop="duplicateSource(source)"
                                        >
                                            <svg
                                                v-if="duplicatingSourceId === source.id"
                                                class="-ml-1 mr-2 h-4 w-4 animate-spin text-green-600"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                                            </svg>
                                            📄 שכפול
                                        </button>
                                        <button
                                            class="inline-flex items-center rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                                            :disabled="deletingSourceId === source.id"
                                            @click.stop="confirmSourceDelete(source)"
                                        >
                                            <svg
                                                v-if="deletingSourceId === source.id"
                                                class="-ml-1 mr-2 h-4 w-4 animate-spin text-red-600"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                                            </svg>
                                            🗑️ מחיקה
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 text-sm text-gray-600">
                                    <div class="flex items-center justify-between">
                                        <span>סה"כ לחודש</span>
                                        <span :class="monthlyAmountClass(source)">{{ formatMonthlyAmount(source) }} ₪</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>מספר עסקאות</span>
                                        <span class="font-semibold text-gray-900">{{ source.monthly_transaction_count }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span>עסקה אחרונה</span>
                                        <span>{{ formatDateLabel(source.latest_transaction_date) }}</span>
                                    </div>
                                </div>

                                <div v-if="source.description" class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                    {{ source.description }}
                                </div>

                                <div v-if="source.budget" class="rounded-md border border-gray-200 bg-white p-3 text-sm shadow-sm">
                                    <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                                        <span>תקציב לחודש</span>
                                        <span>{{ formatCurrency(source.budget.planned_amount) }} ₪ מתוכנן</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span>בוצע</span>
                                        <span :class="source.budget.spent_amount >= source.budget.planned_amount ? 'text-red-600 font-semibold' : 'text-indigo-600 font-semibold'">
                                            {{ formatCurrency(source.budget.spent_amount) }} ₪
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between text-sm">
                                        <span>נותר</span>
                                        <span :class="source.budget.remaining_amount >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">
                                            {{ formatCurrency(source.budget.remaining_amount) }} ₪
                                        </span>
                                    </div>
                                    <div class="mt-3 h-2 w-full rounded-full bg-gray-200">
                                        <div
                                            class="h-2 rounded-full transition-all duration-300"
                                            :class="source.budget.progress_bar_color"
                                            :style="{ width: Math.min(source.budget.progress_percentage, 100) + '%' }"
                                        ></div>
                                    </div>
                                    <div class="mt-1 text-center text-xs font-medium text-gray-500">
                                        {{ source.budget.progress_percentage }}% נוצל
                                    </div>
                                </div>

                                <div v-else class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-center text-sm text-gray-500">
                                    לא הוגדר תקציב לחודש זה
                                </div>

                                <div class="mt-auto text-center">
                                    <button
                                        class="text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-800"
                                        @click.stop="openTransactionsModal(source)"
                                    >
                                        ניהול עסקאות
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-12 text-center text-gray-500">
                            עדיין אין מקורות תזרים להצגה עבור החודש הנבחר
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <CashFlowSourceModal
            :show="isSourceModalOpen"
            :mode="modalMode"
            :source="selectedSource"
            :year="selectedYear"
            :month="selectedMonth"
            @close="closeSourceModal"
            @saved="handleSourceSaved"
            @deleted="handleSourceDeleted"
        />

        <CashFlowSourceTransactionsModal
            :show="isTransactionsModalOpen"
            :source="transactionsSource"
            :year="selectedYear"
            :month="selectedMonth"
            :categories="props.allCategories"
            :cash-flow-sources="props.allCashFlowSources"
            :budgets="props.budgetsForMonth"
            @close="closeTransactionsModal"
        />

    </AuthenticatedLayout>
</template>
