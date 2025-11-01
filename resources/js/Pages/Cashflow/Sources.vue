<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PeriodHeader from '@/Components/PeriodHeader.vue'
import CashFlowSourceModal from '@/Components/CashFlowSourceModal.vue'
import CashFlowSourceTransactionsModal from '@/Components/CashFlowSourceTransactionsModal.vue'
import { loadPeriod, savePeriod } from '@/utils/periodStorage'

const props = defineProps({
    user: Object,
    currentYear: Number,
    currentMonth: Number,
    totalIncome: Number,
    totalExpenses: Number,
    balance: Number,
    accountStatus: Number,
    cashFlowSourcesWithStats: Array,
    cashFlowSources: Array,
    allCashFlowSources: Array,
    allCategories: Array,
    budgetsForMonth: Array,
    availableYears: {
        type: Array,
        default: () => [],
    },
    availablePeriods: {
        type: Array,
        default: () => [],
    },
})

const defaultYear = Number(props.currentYear) || new Date().getFullYear()
const defaultMonth = Number(props.currentMonth) || new Date().getMonth() + 1

const selectedYear = ref(defaultYear)
const selectedMonth = ref(defaultMonth)
const isSourceModalOpen = ref(false)
const modalMode = ref('create')
const selectedSource = ref(null)
const isTransactionsModalOpen = ref(false)
const transactionsSource = ref(null)
const deletingSourceId = ref(null)
const duplicatingSourceId = ref(null)

const allMonthOptions = [
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

const availablePeriodsByYear = computed(() => {
    const map = new Map()

    if (Array.isArray(props.availablePeriods)) {
        props.availablePeriods.forEach((period) => {
            const year = Number(period?.year)
            if (!Number.isFinite(year)) {
                return
            }

            const months = Array.isArray(period?.months)
                ? period.months
                    .map(month => Number(month))
                    .filter(month => Number.isFinite(month) && month >= 1 && month <= 12)
                : []

            const uniqueMonths = Array.from(new Set(months)).sort((a, b) => b - a)
            map.set(year, uniqueMonths)
        })
    }

    if (!map.size) {
        map.set(defaultYear, [defaultMonth])
    }

    if (Array.isArray(props.availableYears)) {
        props.availableYears.forEach((year) => {
            const numericYear = Number(year)
            if (Number.isFinite(numericYear) && !map.has(numericYear)) {
                map.set(numericYear, [])
            }
        })
    }

    return map
})

const yearOptions = computed(() => {
    const years = Array.from(availablePeriodsByYear.value.keys())
    if (!years.includes(defaultYear)) {
        years.push(defaultYear)
    }

    const uniqueYears = Array.from(new Set(
        years.filter(year => Number.isFinite(year))
    ))

    return uniqueYears.sort((a, b) => b - a)
})

const availableMonthsForYear = (year) => {
    const months = availablePeriodsByYear.value.get(Number(year))
    if (!Array.isArray(months) || !months.length) {
        return null
    }

    return months
}

const monthOptions = computed(() => {
    const months = availableMonthsForYear(selectedYear.value)
    if (!months) {
        return allMonthOptions
    }

    const allowed = new Set(months)
    const filtered = allMonthOptions.filter(option => allowed.has(Number(option.value)))

    return filtered.length ? filtered : allMonthOptions
})

const normalizeMonthForYear = (year, month) => {
    const months = availableMonthsForYear(year)
    if (!months || !months.length) {
        return Number(month)
    }

    const numericMonth = Number(month)
    if (months.includes(numericMonth)) {
        return numericMonth
    }

    return months[0]
}

const selectedMonthLabel = computed(() => {
    const current = allMonthOptions.find(option => Number(option.value) === Number(selectedMonth.value))
    return current?.label || selectedMonth.value
})

const periodDisplay = computed(() => `${selectedYear.value} - ${selectedMonthLabel.value}`)

watch(
    () => props.currentYear,
    (value) => {
        const year = Number(value) || new Date().getFullYear()
        selectedYear.value = year
        const normalized = normalizeMonthForYear(year, selectedMonth.value)
        if (normalized !== selectedMonth.value) {
            selectedMonth.value = normalized
        }
    }
)

watch(
    () => props.currentMonth,
    (value) => {
        const month = Number(value) || new Date().getMonth() + 1
        selectedMonth.value = normalizeMonthForYear(selectedYear.value, month)
    }
)

const persistPeriod = (year, month) => {
    if (typeof window === 'undefined') return
    savePeriod(Number(year), Number(month))
}

const navigateToPeriod = (year, month, options = {}) => {
    const normalizedYear = Number(year)
    const normalizedMonth = normalizeMonthForYear(normalizedYear, month)
    persistPeriod(normalizedYear, normalizedMonth)
    router.visit(`/cashflow/sources?year=${normalizedYear}&month=${normalizedMonth}`, {
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
        const normalizedMonth = normalizeMonthForYear(queryYear, queryMonth)
        selectedYear.value = queryYear
        selectedMonth.value = normalizedMonth
        persistPeriod(queryYear, normalizedMonth)
        return
    }

    if (!stored) {
        const normalizedMonth = normalizeMonthForYear(selectedYear.value, selectedMonth.value)
        selectedMonth.value = normalizedMonth
        persistPeriod(selectedYear.value, normalizedMonth)
        return
    }

    if (stored.year !== selectedYear.value || stored.month !== selectedMonth.value) {
        selectedYear.value = stored.year
        selectedMonth.value = normalizeMonthForYear(stored.year, stored.month)
        navigateToPeriod(selectedYear.value, selectedMonth.value)
    } else {
        const normalizedMonth = normalizeMonthForYear(stored.year, stored.month)
        selectedMonth.value = normalizedMonth
        persistPeriod(stored.year, normalizedMonth)
    }
}

const handleYearUpdate = (value) => {
    const year = Number(value)
    const normalizedMonth = normalizeMonthForYear(year, selectedMonth.value)
    selectedYear.value = year
    if (normalizedMonth !== selectedMonth.value) {
        selectedMonth.value = normalizedMonth
    }
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleMonthUpdate = (value) => {
    selectedMonth.value = normalizeMonthForYear(selectedYear.value, value)
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleToday = () => {
    const now = new Date()
    const year = now.getFullYear()
    const month = normalizeMonthForYear(year, now.getMonth() + 1)

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

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}

const formatCurrencyWithSymbol = (amount) => `${formatCurrency(amount)} ₪`

const headerMetrics = computed(() => [
    {
        key: 'accountStatus',
        label: 'מצב העו"ש',
        value: formatCurrencyWithSymbol(props.accountStatus),
        valueClass: 'text-gray-900',
    },
    {
        key: 'balance',
        label: 'יתרה',
        value: formatCurrencyWithSymbol(props.balance),
        valueClass: 'text-gray-900',
    },
    {
        key: 'income',
        label: 'סה\"כ הכנסות',
        value: formatCurrencyWithSymbol(props.totalIncome),
        valueClass: 'text-green-600',
    },
    {
        key: 'expenses',
        label: 'סה\"כ הוצאות',
        value: formatCurrencyWithSymbol(props.totalExpenses),
        valueClass: 'text-red-600',
    },
])

const sourceTypeLabel = (type) => (type === 'income' ? 'הכנסה' : 'הוצאה')

const sourceTypeBadgeClass = (type) => (type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')

const netAmountForSource = (source) => Number(source?.monthly_total_amount ?? source?.monthly_net_amount ?? 0)

const getContrastingTextColor = (hexColor) => {
    if (typeof hexColor !== 'string') {
        return '#111827'
    }

    const sanitized = hexColor.replace('#', '').trim()
    if (!/^[0-9a-fA-F]{3,6}$/.test(sanitized)) {
        return '#111827'
    }

    const normalized = sanitized.length === 3
        ? sanitized.split('').map(char => char + char).join('')
        : sanitized.padEnd(6, '0')

    const r = parseInt(normalized.slice(0, 2), 16)
    const g = parseInt(normalized.slice(2, 4), 16)
    const b = parseInt(normalized.slice(4, 6), 16)

    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
    return luminance > 0.6 ? '#111827' : '#FFFFFF'
}

const formatMonthlyAmount = (source) => {
    const amount = netAmountForSource(source)

    if (!amount) {
        return formatCurrency(0)
    }

    if (source?.type === 'income') {
        const prefix = amount >= 0 ? '+' : '-'
        return `${prefix}${formatCurrency(Math.abs(amount))}`
    }

    const prefix = amount >= 0 ? '-' : '+'
    return `${prefix}${formatCurrency(Math.abs(amount))}`
}

const monthlyAmountClass = (source) => {
    const amount = netAmountForSource(source)

    if (source?.type === 'income') {
        return amount >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'
    }

    return amount >= 0 ? 'text-red-600 font-semibold' : 'text-green-600 font-semibold'
}

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
            <PeriodHeader
                :metrics="headerMetrics"
                :selected-year="selectedYear"
                :selected-month="selectedMonth"
                :period-display="periodDisplay"
                :year-options="yearOptions"
                :month-options="monthOptions"
                @update:year="handleYearUpdate"
                @update:month="handleMonthUpdate"
                @today="handleToday"
            />
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
                                        <span
                                            class="flex h-12 w-12 items-center justify-center rounded-md text-3xl shadow-sm ring-1 ring-black/5"
                                            :style="{
                                                backgroundColor: source.color || '#E5E7EB',
                                                color: getContrastingTextColor(source.color || '#E5E7EB'),
                                            }"
                                            aria-hidden="true"
                                        >
                                            {{ source.icon || (source.type === 'income' ? '💰' : '💸') }}
                                        </span>
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
                                <div
                                    v-if="source.allows_refunds"
                                    class="flex flex-col gap-1 text-xs text-gray-500"
                                >
                                    <div class="flex items-center justify-between">
                                        <span>סך הוצאות</span>
                                        <span class="font-medium text-red-600">-{{ formatCurrency(Number(source.monthly_expense_amount || 0)) }} ₪</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>סך זיכויים</span>
                                        <span class="font-medium text-green-600">+{{ formatCurrency(Number(source.monthly_income_amount || 0)) }} ₪</span>
                                    </div>
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
