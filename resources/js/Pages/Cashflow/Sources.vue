<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
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
const openSourceActionId = ref(null)

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
    if (typeof window !== 'undefined') {
        window.addEventListener('click', handleGlobalSourceActionClick)
    }
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return
    }

    window.removeEventListener('click', handleGlobalSourceActionClick)
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
    {
        key: 'balance',
        label: 'יתרה',
        value: formatCurrencyWithSymbol(props.balance),
        valueClass: 'text-gray-900',
    },
    {
        key: 'accountStatus',
        label: 'מצב העו"ש',
        value: formatCurrencyWithSymbol(props.accountStatus),
        valueClass: 'text-gray-900',
    },
])

const sourceTypeLabel = (type) => (type === 'income' ? 'הכנסה' : 'הוצאה')

const sourceTypeBadgeClass = (type) => (type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')

const netAmountForSource = (source) => Number(source?.monthly_total_amount ?? source?.monthly_net_amount ?? 0)

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

const toRgbaWithAlpha = (hexColor, alpha = 0.2) => {
    if (typeof hexColor !== 'string') {
        return `rgba(229, 231, 235, ${alpha})`
    }

    const sanitized = hexColor.replace('#', '').trim()
    if (![3, 6].includes(sanitized.length) || !/^[0-9a-fA-F]+$/.test(sanitized)) {
        return `rgba(229, 231, 235, ${alpha})`
    }

    const normalized = sanitized.length === 3
        ? sanitized.split('').map(char => char + char).join('')
        : sanitized

    const r = parseInt(normalized.slice(0, 2), 16)
    const g = parseInt(normalized.slice(2, 4), 16)
    const b = parseInt(normalized.slice(4, 6), 16)

    return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const getSourceHeaderBackground = (color) => toRgbaWithAlpha(color || '#E5E7EB', 0.2)

const sourceBudgetPlaceholder = (type) => (type === 'income' ? 'מקור הכנסה ללא תקציב' : 'טרם הוגדר תקציב למקור תזרים זה')

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

const getSourceUniqueId = (source) => source?.id

const toggleSourceActionMenu = (source) => {
    const id = getSourceUniqueId(source)
    if (!id) {
        openSourceActionId.value = null
        return
    }

    openSourceActionId.value = openSourceActionId.value === id ? null : id
}

const closeSourceActionMenu = () => {
    openSourceActionId.value = null
}

const isSourceActionMenuOpen = (source) => {
    const id = getSourceUniqueId(source)
    if (!id) {
        return false
    }

    return openSourceActionId.value === id
}

const isSourceDuplicating = (source) => {
    const id = getSourceUniqueId(source)
    if (!id) {
        return false
    }

    return duplicatingSourceId.value === id
}

const handleEditSourceClick = (source) => {
    closeSourceActionMenu()
    openEditSourceModal(source)
}

const handleDuplicateSourceClick = (source) => {
    closeSourceActionMenu()
    duplicateSource(source)
}

const handleDeleteSourceClick = (source) => {
    closeSourceActionMenu()
    confirmSourceDelete(source)
}

const handleGlobalSourceActionClick = (event) => {
    const target = event.target
    if (typeof Element === 'undefined' || !(target instanceof Element)) {
        return
    }

    if (!target.closest('[data-source-actions]')) {
        closeSourceActionMenu()
    }
}
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
                                class="flex h-full flex-col gap-4 rounded-lg border-2 border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md"
                                :class="{
                                    'border-indigo-300 bg-indigo-50': source.budget,
                                    'opacity-75': source.is_active === false,
                                }"
                            >
                                <div
                                    class="px-3 py-2"
                                    :style="{ backgroundColor: getSourceHeaderBackground(source.color) }"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex flex-1 items-center gap-3">
                                            <span
                                                class="flex h-10 w-10 items-center justify-center rounded-md text-2xl shadow-sm ring-1 ring-black/5"
                                                :style="{ color: source.color || '#4F46E5' }"
                                                aria-hidden="true"
                                            >
                                                {{ source.icon || (source.type === 'income' ? '💰' : '💸') }}
                                            </span>
                                            <div class="flex flex-1 flex-col text-right">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="text-lg font-semibold text-gray-900">
                                                        {{ source.name }}
                                                    </h4>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="sourceTypeBadgeClass(source.type)">
                                                        {{ sourceTypeLabel(source.type) }}
                                                    </span>
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center justify-end gap-2 text-[11px] text-gray-600">
                                                    <span
                                                        v-if="source.is_active === false"
                                                        class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700"
                                                    >
                                                        לא פעיל
                                                    </span>
                                                    <span>
                                                        {{ source.monthly_transaction_count ?? 0 }} עסקאות בחודש
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="relative" data-source-actions @click.stop>
                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-lg text-gray-500 transition hover:border-indigo-200 hover:text-indigo-600 focus:outline-none"
                                                :disabled="isSourceDuplicating(source)"
                                                @click.stop="toggleSourceActionMenu(source)"
                                                :aria-expanded="isSourceActionMenuOpen(source)"
                                            >
                                                <svg
                                                    v-if="isSourceDuplicating(source)"
                                                    class="h-5 w-5 animate-spin text-indigo-600"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647ז"></path>
                                                </svg>
                                                <span v-else class="inline-block text-2xl leading-none">⋮</span>
                                                <span class="sr-only">אפשרויות</span>
                                            </button>

                                            <transition
                                                enter-active-class="transition transform duration-150 ease-out"
                                                enter-from-class="opacity-0 translate-y-2"
                                                enter-to-class="opacity-100 translate-y-0"
                                                leave-active-class="transition transform duration-100 ease-in"
                                                leave-from-class="opacity-100 translate-y-0"
                                                leave-to-class="opacity-0 translate-y-2"
                                            >
                                                <div
                                                    v-if="isSourceActionMenuOpen(source)"
                                                    class="absolute left-auto right-0 z-20 mt-2 w-40 rounded-xl border border-gray-200 bg-white p-2 text-xs font-semibold text-gray-600 shadow-lg"
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-gray-700 transition hover:bg-gray-200 hover:text-indigo-700"
                                                        @click="handleEditSourceClick(source)"
                                                    >
                                                        ✏️
                                                        <span>עריכה</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="mt-1 flex w-full items-center justify-between rounded-lg px-3 py-2 text-gray-700 transition hover:bg-gray-200 hover:text-indigo-700 disabled:opacity-60"
                                                        :disabled="isSourceDuplicating(source)"
                                                        @click="handleDuplicateSourceClick(source)"
                                                    >
                                                        <span class="flex items-center gap-1">
                                                            <svg
                                                                v-if="isSourceDuplicating(source)"
                                                                class="h-4 w-4 animate-spin text-gray-500"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647ז"></path>
                                                            </svg>
                                                            <span v-else>📄</span>
                                                        </span>
                                                        <span class="font-medium">שכפול</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="mt-1 flex w-full items-center justify-between rounded-lg px-3 py-2 text-gray-700 transition hover:bg-gray-200 hover:text-indigo-700 disabled:opacity-60"
                                                        :disabled="deletingSourceId === source.id"
                                                        @click="handleDeleteSourceClick(source)"
                                                    >
                                                        <span class="flex items-center gap-1">
                                                            <svg
                                                                v-if="deletingSourceId === source.id"
                                                                class="h-4 w-4 animate-spin text-gray-500"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647ז"></path>
                                                            </svg>
                                                            <span v-else>🗑️</span>
                                                        </span>
                                                        <span>מחיקה</span>
                                                    </button>
                                                </div>
                                            </transition>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-1 flex-col gap-3 px-3 pb-4 text-sm text-gray-600">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            v-if="source.exclude_from_totals"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200"
                                        >
                                            לא בסיכום הכללי
                                        </span>
                                        <span
                                            v-if="source.year && source.month"
                                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-200"
                                        >
                                            לחודש {{ source.month }}/{{ source.year }}
                                        </span>
                                    </div>

                                    <div class="rounded-md border border-gray-200 bg-white/80 p-3 text-xs text-gray-600 shadow-sm">
                                        <div class="flex items-center justify-between text-sm">
                                            <span>סה"כ לחודש</span>
                                            <span :class="monthlyAmountClass(source)">{{ formatMonthlyAmount(source) }} ₪</span>
                                        </div>
                                        <div
                                            v-if="source.allows_refunds"
                                            class="mt-2 grid gap-1 text-[11px] text-gray-500"
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
                                        <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
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
                                        {{ sourceBudgetPlaceholder(source.type) }}
                                    </div>

                                    <div class="mt-auto pt-2 text-center">
                                        <button
                                            class="text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-800"
                                            @click.stop="openTransactionsModal(source)"
                                        >
                                            ניהול עסקאות
                                        </button>
                                    </div>
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
