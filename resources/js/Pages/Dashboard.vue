<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TransactionAddModal from '@/Components/TransactionAddModal.vue'
import PeriodHeader from '@/Components/PeriodHeader.vue'
import IncomeExpenseChart from '@/Components/IncomeExpenseChart.vue'
import CategoryExpenseChart from '@/Components/CategoryExpenseChart.vue'
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
    monthlyTransactions: Array,
    incomeExpenseChart: Object,
    categoryExpenseChart: Object,
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
const isTransactionModalOpen = ref(false)

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
    router.visit(`/dashboard?year=${normalizedYear}&month=${normalizedMonth}`, {
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

const balanceTrend = computed(() => {
    const value = Number(props.balance) || 0

    if (value > 0) {
        return {
            direction: 'positive',
            arrow: '▲',
            arrowClass: 'text-green-600',
            badgeClass: 'bg-green-100',
            amountClass: 'text-green-600',
        }
    }

    if (value < 0) {
        return {
            direction: 'negative',
            arrow: '▼',
            arrowClass: 'text-red-600',
            badgeClass: 'bg-red-100',
            amountClass: 'text-red-600',
        }
    }

    return {
        direction: 'neutral',
        arrow: '',
        arrowClass: 'text-gray-500',
        badgeClass: 'bg-gray-100',
        amountClass: 'text-gray-700',
    }
})

const parseDate = (value) => {
    if (!value) {
        return null
    }

    const date = new Date(value)
    return Number.isNaN(date.valueOf()) ? null : date
}

const toDateKey = (date) => {
    if (!date) {
        return ''
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const fromDateKey = (key) => {
    if (!key) {
        return null
    }

    const parts = key.split('-')
    if (parts.length !== 3) {
        return null
    }

    const [year, month, day] = parts.map(part => Number(part))
    if ([year, month, day].some(value => Number.isNaN(value))) {
        return null
    }

    return new Date(year, month - 1, day)
}

const buildDisplayTransaction = (transaction, preferredDate = null) => {
    const preferred = parseDate(preferredDate)
    const fallback = parseDate(transaction?.posting_date) || parseDate(transaction?.transaction_date)
    const resolvedDate = preferred || fallback

    return {
        id: transaction.id,
        description: transaction.description || 'ללא תיאור',
        categoryName: transaction?.category?.name || 'ללא קטגוריה',
        categoryColor: transaction?.category?.color || '',
        icon: transaction?.category?.icon || (transaction.type === 'income' ? '💸' : '💳'),
        amount: Number(transaction.amount) || 0,
        isIncome: transaction.type === 'income',
        sourceName: transaction?.cash_flow_source?.name || null,
        time: resolvedDate,
        timeLabel: resolvedDate
            ? resolvedDate.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' })
            : '',
        transactionDate: preferred || fallback,
    }
}

const timelineEntries = computed(() => {
    const transactions = props.monthlyTransactions || []
    const groupedByPostingDate = new Map()

    transactions.forEach((transaction) => {
        const postingDate = transaction?.posting_date || transaction?.transaction_date
        const postingDateObj = parseDate(postingDate)
        if (!postingDateObj) {
            return
        }

        const dateKey = toDateKey(postingDateObj)
        if (!dateKey) {
            return
        }

        if (!groupedByPostingDate.has(dateKey)) {
            groupedByPostingDate.set(dateKey, [])
        }

        groupedByPostingDate.get(dateKey).push(transaction)
    })

    const entries = Array.from(groupedByPostingDate.entries()).map(([dateKey, items]) => {
        const dateObj = fromDateKey(dateKey)
        const totals = { income: 0, expense: 0 }
        const regularTransactions = []
        const cashFlowSourceMap = new Map()

        items.forEach((transaction) => {
            const amountValue = Math.abs(Number(transaction.amount) || 0)
            if (transaction.type === 'income') {
                totals.income += amountValue
            } else {
                totals.expense += amountValue
            }

            if (transaction.cash_flow_source_id) {
                const sourceId = transaction.cash_flow_source_id

                if (!cashFlowSourceMap.has(sourceId)) {
                    cashFlowSourceMap.set(sourceId, {
                        sourceId,
                        sourceName: transaction?.cash_flow_source?.name || 'מקור תזרים',
                        sourceIcon: transaction?.cash_flow_source?.icon || '💳',
                        sourceColor: transaction?.cash_flow_source?.color || '#EEF2FF',
                        totals: { income: 0, expense: 0 },
                        dateGroups: new Map(),
                    })
                }

                const group = cashFlowSourceMap.get(sourceId)
                if (transaction.type === 'income') {
                    group.totals.income += amountValue
                } else {
                    group.totals.expense += amountValue
                }

                const transactionDate = parseDate(transaction?.transaction_date) || parseDate(transaction?.posting_date)
                const transactionDateKey = toDateKey(transactionDate) || dateKey

                if (!group.dateGroups.has(transactionDateKey)) {
                    group.dateGroups.set(transactionDateKey, {
                        transactions: [],
                        totals: { income: 0, expense: 0 },
                    })
                }

                const dateGroup = group.dateGroups.get(transactionDateKey)
                const displayTransaction = buildDisplayTransaction(transaction, transaction?.transaction_date)

                dateGroup.transactions.push(displayTransaction)

                const entryAmount = Math.abs(displayTransaction.amount)
                if (displayTransaction.isIncome) {
                    dateGroup.totals.income += entryAmount
                } else {
                    dateGroup.totals.expense += entryAmount
                }
            } else {
                regularTransactions.push(buildDisplayTransaction(transaction))
            }
        })

        regularTransactions.sort((a, b) => {
            const aTime = a.time ? a.time.getTime() : 0
            const bTime = b.time ? b.time.getTime() : 0
            return aTime - bTime
        })

        const cashFlowGroups = Array.from(cashFlowSourceMap.values()).map((group) => {
            const dateGroups = Array.from(group.dateGroups.entries()).map(([groupDateKey, payload]) => {
                const groupDateObj = fromDateKey(groupDateKey)

                payload.transactions.sort((a, b) => {
                    const aTime = a.transactionDate ? a.transactionDate.getTime() : 0
                    const bTime = b.transactionDate ? b.transactionDate.getTime() : 0
                    return aTime - bTime
                })

                return {
                    dateKey: groupDateKey,
                    dateLabel: groupDateObj
                        ? groupDateObj.toLocaleDateString('he-IL', { day: 'numeric', month: 'long', year: 'numeric' })
                        : groupDateKey,
                    weekdayLabel: groupDateObj
                        ? groupDateObj.toLocaleDateString('he-IL', { weekday: 'long' })
                        : '',
                    totals: payload.totals,
                    transactions: payload.transactions,
                }
            })

            dateGroups.sort((a, b) => {
                const aDate = fromDateKey(a.dateKey)
                const bDate = fromDateKey(b.dateKey)
                return (aDate ? aDate.getTime() : 0) - (bDate ? bDate.getTime() : 0)
            })

            return {
                sourceId: group.sourceId,
                sourceName: group.sourceName,
                sourceIcon: group.sourceIcon,
                sourceColor: group.sourceColor,
                totals: group.totals,
                dateGroups,
            }
        })

        cashFlowGroups.sort((a, b) => a.sourceName.localeCompare(b.sourceName, 'he', { sensitivity: 'base' }))

        return {
            dateKey,
            dateLabel: dateObj
                ? dateObj.toLocaleDateString('he-IL', { day: 'numeric', month: 'long', year: 'numeric' })
                : dateKey,
            weekdayLabel: dateObj
                ? dateObj.toLocaleDateString('he-IL', { weekday: 'long' })
                : '',
            totalIncome: totals.income,
            totalExpenses: totals.expense,
            regularTransactions,
            cashFlowGroups,
        }
    })

    entries.sort((a, b) => {
        const aDate = fromDateKey(a.dateKey)
        const bDate = fromDateKey(b.dateKey)
        return (aDate ? aDate.getTime() : 0) - (bDate ? bDate.getTime() : 0)
    })

    return entries
})

const selectedHorizontalSourceId = ref(null)

const isHorizontalSourceSelected = computed(() => selectedHorizontalSourceId.value !== null)

const horizontalSourceOptions = computed(() => {
    const map = new Map()

    timelineEntries.value.forEach((entry) => {
        entry.cashFlowGroups.forEach((group) => {
            if (!map.has(group.sourceId)) {
                map.set(group.sourceId, {
                    sourceId: group.sourceId,
                    name: group.sourceName,
                    icon: group.sourceIcon,
                    color: group.sourceColor,
                    totals: { income: 0, expense: 0 },
                })
            }

            const option = map.get(group.sourceId)
            option.totals.income += Math.abs(group.totals?.income || 0)
            option.totals.expense += Math.abs(group.totals?.expense || 0)
        })
    })

    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, 'he', { sensitivity: 'base' }))
})

const selectedHorizontalSource = computed(() => {
    if (!isHorizontalSourceSelected.value) {
        return null
    }

    return horizontalSourceOptions.value.find(option => option.sourceId === selectedHorizontalSourceId.value) || null
})

const horizontalTimelineEntries = computed(() => {
    if (!timelineEntries.value.length) {
        return []
    }

    if (!isHorizontalSourceSelected.value) {
        return timelineEntries.value.map((entry) => ({
            dateKey: entry.dateKey,
            dateLabel: entry.dateLabel,
            weekdayLabel: entry.weekdayLabel,
            totals: { income: entry.totalIncome, expense: entry.totalExpenses },
            directTransactions: entry.regularTransactions,
            sourceSummaries: entry.cashFlowGroups.map((group) => ({
                sourceId: group.sourceId,
                sourceName: group.sourceName,
                sourceIcon: group.sourceIcon,
                sourceColor: group.sourceColor,
                totals: group.totals,
            })),
            transactions: [],
        }))
    }

    const sourceId = selectedHorizontalSourceId.value
    const grouped = new Map()

    ;(props.monthlyTransactions || []).forEach((transaction) => {
        if (transaction.cash_flow_source_id !== sourceId) {
            return
        }

        const dateObj = parseDate(transaction?.transaction_date) || parseDate(transaction?.posting_date)
        if (!dateObj) {
            return
        }

        const dateKey = toDateKey(dateObj)
        if (!dateKey) {
            return
        }

        if (!grouped.has(dateKey)) {
            grouped.set(dateKey, {
                dateObj,
                income: 0,
                expense: 0,
                transactions: [],
            })
        }

        const bucket = grouped.get(dateKey)
        const displayTransaction = buildDisplayTransaction(transaction, transaction?.transaction_date)
        bucket.transactions.push(displayTransaction)

        const amountValue = Math.abs(displayTransaction.amount)
        if (displayTransaction.isIncome) {
            bucket.income += amountValue
        } else {
            bucket.expense += amountValue
        }
    })

    const entries = Array.from(grouped.entries()).map(([dateKey, bucket]) => {
        bucket.transactions.sort((a, b) => {
            const aTime = a.transactionDate ? a.transactionDate.getTime() : 0
            const bTime = b.transactionDate ? b.transactionDate.getTime() : 0
            return aTime - bTime
        })

        return {
            dateKey,
            dateLabel: bucket.dateObj
                ? bucket.dateObj.toLocaleDateString('he-IL', { day: 'numeric', month: 'long', year: 'numeric' })
                : dateKey,
            weekdayLabel: bucket.dateObj
                ? bucket.dateObj.toLocaleDateString('he-IL', { weekday: 'long' })
                : '',
            totals: { income: bucket.income, expense: bucket.expense },
            directTransactions: [],
            sourceSummaries: [],
            transactions: bucket.transactions,
        }
    })

    entries.sort((a, b) => {
        const aDate = fromDateKey(a.dateKey)
        const bDate = fromDateKey(b.dateKey)
        return (aDate ? aDate.getTime() : 0) - (bDate ? bDate.getTime() : 0)
    })

    return entries
})

const selectHorizontalSource = (sourceId = null) => {
    if (sourceId === null) {
        selectedHorizontalSourceId.value = null
        return
    }

    if (selectedHorizontalSourceId.value === sourceId) {
        selectedHorizontalSourceId.value = null
        return
    }

    selectedHorizontalSourceId.value = sourceId
}

const expandedSourceGroups = ref({})

const getSourceGroupKey = (entryKey, sourceId) => `${entryKey}:${sourceId}`

const isSourceGroupExpanded = (entryKey, sourceId) => {
    const key = getSourceGroupKey(entryKey, sourceId)
    const state = expandedSourceGroups.value[key]
    return state === undefined ? true : Boolean(state)
}

const toggleSourceGroup = (entryKey, sourceId) => {
    const key = getSourceGroupKey(entryKey, sourceId)
    const current = isSourceGroupExpanded(entryKey, sourceId)
    expandedSourceGroups.value = {
        ...expandedSourceGroups.value,
        [key]: !current,
    }
}

watch(
    () => props.monthlyTransactions,
    () => {
        expandedSourceGroups.value = {}
        selectedHorizontalSourceId.value = null
    }
)

watch(horizontalSourceOptions, (options) => {
    if (!selectedHorizontalSourceId.value) {
        return
    }

    const exists = options.some(option => option.sourceId === selectedHorizontalSourceId.value)
    if (!exists) {
        selectedHorizontalSourceId.value = null
    }
})
</script>

<template>
    <Head title="דשבורד תקציב" />

    <AuthenticatedLayout>
        <template #header>
            <PeriodHeader
                :selected-year="selectedYear"
                :selected-month="selectedMonth"
                :period-display="periodDisplay"
                :year-options="yearOptions"
                :month-options="monthOptions"
                summary-order="start"
                summary-wrapper-class="w-full lg:flex-none lg:w-auto"
                @update:year="handleYearUpdate"
                @update:month="handleMonthUpdate"
                @today="handleToday"
            >
                <template #summary>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 text-right lg:text-left">
                        דשבורד תקציב ביתי
                    </h2>
                </template>
            </PeriodHeader>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    

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
                                <div class="flex items-center justify-end gap-2">
                                    <p class="text-2xl font-bold" :class="balanceTrend.amountClass">
                                        {{ formatCurrency(props.balance) }}
                                    </p>
                                    <span
                                        v-if="balanceTrend.direction !== 'neutral'"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-lg"
                                        :class="[balanceTrend.badgeClass, balanceTrend.arrowClass]"
                                    >
                                        {{ balanceTrend.arrow }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
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
                </div>

                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mt-8">
                    <div class="p-6">
                        <IncomeExpenseChart
                            :chart-data="props.incomeExpenseChart"
                            :selected-year="selectedYear"
                            :selected-month="selectedMonth"
                        />
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mt-8">
                    <div class="p-6">
                        <CategoryExpenseChart
                            :chart-data="props.categoryExpenseChart"
                        />
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mt-8">
                    <div class="p-6">
                        <div class="flex flex-col gap-1 text-right">
                            <h3 class="text-lg font-medium text-gray-900">ציר תזרימים רוחבי</h3>
                            <p class="text-sm text-gray-500">
                                תצוגה רוחבית עם גלילה שמרכזת את הסכומים והעסקאות לכל יום בחודש הנבחר.
                            </p>
                        </div>

                        <div v-if="!timelineEntries.length" class="mt-6 text-sm text-gray-500 text-right">
                            אין תזרימים מתועדים לחודש זה.
                        </div>

                        <div v-else dir="rtl" class="relative mt-6">
                            <div v-if="horizontalSourceOptions.length" class="mb-4 flex flex-col gap-2 text-xs text-right">
                                <div v-if="selectedHorizontalSource" class="flex flex-wrap items-center justify-end gap-2">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-3 py-1 font-medium text-indigo-700">
                                        {{ selectedHorizontalSource.name }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-white px-3 py-1 font-medium text-indigo-600 transition hover:bg-indigo-50"
                                        @click="selectHorizontalSource(null)"
                                    >
                                        חזרה לכל התזרימים
                                    </button>
                                </div>
                                <div v-else class="text-gray-500">
                                    ניתן ללחוץ על מקור תזרים כדי להתמקד בו בציר הרוחבי.
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button
                                        v-for="option in horizontalSourceOptions"
                                        :key="`horizontal-option-${option.sourceId}`"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 transition"
                                        :class="selectedHorizontalSourceId === option.sourceId
                                            ? 'border-indigo-500 bg-indigo-100 text-indigo-700'
                                            : 'border-indigo-200 bg-white text-gray-600 hover:border-indigo-300 hover:text-indigo-600'"
                                        @click="selectHorizontalSource(option.sourceId)"
                                    >
                                        <span class="text-base">{{ option.icon || '💳' }}</span>
                                        <span class="font-medium">{{ option.name }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="horizontalTimelineEntries.length" class="overflow-x-auto pb-4">
                                <div class="relative inline-block min-w-full">
                                    <div class="absolute top-6 right-0 left-0 h-0.5 bg-gray-200"></div>
                                    <div class="flex min-w-max gap-6 pr-4 sm:pr-8">
                                        <div
                                            v-for="entry in horizontalTimelineEntries"
                                            :key="`horizontal-${entry.dateKey}`"
                                            class="relative flex min-w-[260px] flex-col gap-4 rounded-lg border border-gray-200 bg-white px-4 pb-4 pt-8 shadow-sm"
                                        >
                                            <span class="absolute top-4 right-1/2 flex h-3 w-3 translate-x-1/2 transform rounded-full border-2 border-white bg-indigo-500 shadow"></span>
                                            <div class="flex flex-col gap-1 text-right">
                                                <p class="text-sm font-semibold text-gray-900">{{ entry.dateLabel }}</p>
                                                <p v-if="entry.weekdayLabel" class="text-xs text-gray-500">{{ entry.weekdayLabel }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center justify-end gap-3 text-xs">
                                                <span v-if="entry.totals.income" class="font-medium text-green-600">
                                                    +{{ formatCurrency(entry.totals.income) }} ₪
                                                </span>
                                                <span v-if="entry.totals.expense" class="font-medium text-red-600">
                                                    -{{ formatCurrency(entry.totals.expense) }} ₪
                                                </span>
                                            </div>
                                            <div class="flex flex-col gap-3 text-right">
                                                <template v-if="!isHorizontalSourceSelected">
                                                    <div v-if="entry.directTransactions.length" class="space-y-2">
                                                        <p class="text-xs font-semibold text-gray-500">עסקאות ישירות</p>
                                                        <div class="flex flex-col gap-2">
                                                            <div
                                                                v-for="transaction in entry.directTransactions"
                                                                :key="`horizontal-direct-${entry.dateKey}-${transaction.id}`"
                                                                class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2"
                                                            >
                                                                <div class="flex items-center justify-between gap-3">
                                                                    <div class="flex items-center justify-end gap-2">
                                                                        <div
                                                                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-lg"
                                                                            :style="{ backgroundColor: transaction.categoryColor || (transaction.isIncome ? '#dcfce7' : '#fee2e2') }"
                                                                        >
                                                                            {{ transaction.icon }}
                                                                        </div>
                                                                        <div class="text-right">
                                                                            <p class="text-xs font-medium text-gray-900 truncate">
                                                                                {{ transaction.description }}
                                                                            </p>
                                                                            <p class="text-[11px] text-gray-500 truncate">
                                                                                {{ transaction.categoryName }}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex flex-col items-end gap-1 text-left sm:text-right">
                                                                        <span
                                                                            class="text-xs font-semibold"
                                                                            :class="transaction.isIncome ? 'text-green-600' : 'text-red-600'"
                                                                        >
                                                                            {{ transaction.isIncome ? '+' : '-' }}{{ formatCurrency(Math.abs(transaction.amount)) }} ₪
                                                                        </span>
                                                                        <span v-if="transaction.timeLabel" class="text-[11px] text-gray-400">
                                                                            {{ transaction.timeLabel }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div v-if="entry.sourceSummaries.length" class="space-y-2">
                                                        <p class="text-xs font-semibold text-gray-500">מקורות תזרים</p>
                                                        <div class="flex flex-col gap-2">
                                                            <button
                                                                v-for="summary in entry.sourceSummaries"
                                                                :key="`horizontal-summary-${entry.dateKey}-${summary.sourceId}`"
                                                                type="button"
                                                                class="flex items-center justify-between gap-3 rounded-md border border-indigo-100 bg-white px-3 py-2 text-right shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
                                                                @click="selectHorizontalSource(summary.sourceId)"
                                                            >
                                                                <div class="flex items-center justify-end gap-2">
                                                                    <div
                                                                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-lg"
                                                                        :style="{ backgroundColor: summary.sourceColor || '#EEF2FF' }"
                                                                    >
                                                                        {{ summary.sourceIcon }}
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <p class="text-xs font-semibold text-indigo-900 truncate">
                                                                            {{ summary.sourceName }}
                                                                        </p>
                                                                        <div class="flex flex-wrap items-center justify-end gap-2 text-[11px] text-indigo-600">
                                                                            <span v-if="summary.totals.income">+{{ formatCurrency(summary.totals.income) }} ₪</span>
                                                                            <span v-if="summary.totals.expense" class="text-red-500">-{{ formatCurrency(summary.totals.expense) }} ₪</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <span class="text-[11px] text-indigo-500">לחץ למיקוד</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template v-else>
                                                    <div v-if="entry.transactions.length" class="flex flex-col gap-2">
                                                        <div
                                                            v-for="transaction in entry.transactions"
                                                            :key="`horizontal-selected-${entry.dateKey}-${transaction.id}`"
                                                            class="rounded-md border border-indigo-100 bg-white px-3 py-2 shadow-sm"
                                                        >
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div class="flex items-center justify-end gap-2">
                                                                    <div
                                                                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-xl"
                                                                        :style="{ backgroundColor: transaction.categoryColor || (transaction.isIncome ? '#dcfce7' : '#fee2e2') }"
                                                                    >
                                                                        {{ transaction.icon }}
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <p class="text-sm font-medium text-gray-900">
                                                                            {{ transaction.description }}
                                                                        </p>
                                                                        <p class="text-xs text-gray-500">
                                                                            {{ transaction.categoryName }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex flex-col items-end gap-1 text-left sm:text-right">
                                                                    <span
                                                                        class="text-sm font-semibold"
                                                                        :class="transaction.isIncome ? 'text-green-600' : 'text-red-600'"
                                                                    >
                                                                        {{ transaction.isIncome ? '+' : '-' }}{{ formatCurrency(Math.abs(transaction.amount)) }} ₪
                                                                    </span>
                                                                    <span v-if="transaction.timeLabel" class="text-xs text-gray-400">
                                                                        {{ transaction.timeLabel }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-else class="text-xs text-gray-500">
                                                        אין עסקאות למקור זה ביום זה.
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="mt-6 text-sm text-gray-500 text-right">
                                אין עסקאות למקור התזרים שנבחר לחודש זה.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mt-8">
                    <div class="p-6">
                        <div class="flex flex-col gap-1 text-right">
                            <h3 class="text-lg font-medium text-gray-900">ציר תזרימים חודשי</h3>
                            <p class="text-sm text-gray-500">
                                תצוגת קו־זמן של כל התזרימים ביום בו בוצעו במהלך החודש הנבחר.
                            </p>
                        </div>

                        <div v-if="!timelineEntries.length" class="mt-6 text-sm text-gray-500 text-right">
                            אין תזרימים מתועדים לחודש זה.
                        </div>

                        <div v-else dir="rtl" class="relative mt-6">
                            <div class="absolute top-0 bottom-0 right-4 hidden w-px bg-gray-200 sm:block"></div>
                            <div class="space-y-6">
                                <div
                                    v-for="entry in timelineEntries"
                                    :key="entry.dateKey"
                                    class="relative flex flex-col gap-3 pr-4 sm:pr-16"
                                >
                                    <span class="absolute right-[14px] hidden h-3 w-3 rounded-full border-2 border-white bg-indigo-500 shadow sm:block"></span>
                                    <div class="flex flex-col gap-1 text-right">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900">{{ entry.dateLabel }}</p>
                                                <p v-if="entry.weekdayLabel" class="text-xs text-gray-500">{{ entry.weekdayLabel }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center justify-end gap-3 text-xs">
                                                <span v-if="entry.totalIncome" class="font-medium text-green-600">
                                                    +{{ formatCurrency(entry.totalIncome) }} ₪ הכנסות
                                                </span>
                                                <span v-if="entry.totalExpenses" class="font-medium text-red-600">
                                                    -{{ formatCurrency(entry.totalExpenses) }} ₪ הוצאות
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <div
                                            v-for="transaction in entry.regularTransactions"
                                            :key="`${entry.dateKey}-regular-${transaction.id}`"
                                            class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-right shadow-sm"
                                        >
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="flex flex-1 flex-col gap-2 sm:items-end">
                                                    <div class="flex items-center justify-end gap-3">
                                                        <div
                                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-xl"
                                                            :style="{ backgroundColor: transaction.categoryColor || (transaction.isIncome ? '#dcfce7' : '#fee2e2') }"
                                                        >
                                                            {{ transaction.icon }}
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-sm font-medium text-gray-900">
                                                                {{ transaction.description }}
                                                            </p>
                                                            <p class="text-xs text-gray-500">
                                                                {{ transaction.categoryName }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex flex-col items-end gap-1 text-left sm:text-right">
                                                    <span
                                                        class="text-base font-semibold"
                                                        :class="transaction.isIncome ? 'text-green-600' : 'text-red-600'"
                                                    >
                                                        {{ transaction.isIncome ? '+' : '-' }}{{ formatCurrency(Math.abs(transaction.amount)) }} ₪
                                                    </span>
                                                    <span v-if="transaction.timeLabel" class="text-xs text-gray-400">
                                                        {{ transaction.timeLabel }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-for="group in entry.cashFlowGroups"
                                            :key="`${entry.dateKey}-source-${group.sourceId}`"
                                            class="rounded-lg border border-indigo-200 bg-indigo-50/60 px-4 py-4 text-right shadow-sm"
                                        >
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="flex items-center justify-end gap-3">
                                                    <div
                                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-xl"
                                                        :style="{ backgroundColor: group.sourceColor || '#EEF2FF' }"
                                                    >
                                                        {{ group.sourceIcon }}
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-sm font-semibold text-gray-900">
                                                            {{ group.sourceName }}
                                                        </p>
                                                        <p class="text-xs text-gray-500">
                                                            פירוט לפי תאריך עסקה
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex flex-col items-end gap-2 sm:flex-row sm:items-center sm:gap-4">
                                                    <div class="flex flex-col items-end gap-1 text-xs">
                                                        <span v-if="group.totals.income" class="font-medium text-green-600">
                                                            +{{ formatCurrency(group.totals.income) }} ₪ הכנסות
                                                        </span>
                                                        <span v-if="group.totals.expense" class="font-medium text-red-600">
                                                            -{{ formatCurrency(group.totals.expense) }} ₪ הוצאות
                                                        </span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-white px-3 py-1 text-xs font-medium text-indigo-600 transition hover:bg-indigo-50"
                                                        @click="toggleSourceGroup(entry.dateKey, group.sourceId)"
                                                    >
                                                        <span>
                                                            {{ isSourceGroupExpanded(entry.dateKey, group.sourceId) ? 'הסתר פירוט' : 'הצג פירוט' }}
                                                        </span>
                                                        <span aria-hidden="true">
                                                            {{ isSourceGroupExpanded(entry.dateKey, group.sourceId) ? '▲' : '▼' }}
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                v-if="isSourceGroupExpanded(entry.dateKey, group.sourceId)"
                                                class="relative mt-4 pr-4 sm:pr-12"
                                            >
                                                <div class="absolute top-1 bottom-1 right-2 hidden w-px bg-indigo-200 sm:block"></div>
                                                <div class="space-y-4">
                                                    <div
                                                        v-for="dateGroup in group.dateGroups"
                                                        :key="`${group.sourceId}-${dateGroup.dateKey}`"
                                                        class="relative flex flex-col gap-2 pr-2 sm:pr-8"
                                                    >
                                                        <span class="absolute right-[10px] hidden h-2.5 w-2.5 rounded-full border-2 border-white bg-indigo-400 sm:block"></span>
                                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                                                            <div>
                                                                <p class="text-sm font-semibold text-indigo-900">{{ dateGroup.dateLabel }}</p>
                                                                <p v-if="dateGroup.weekdayLabel" class="text-xs text-indigo-500">{{ dateGroup.weekdayLabel }}</p>
                                                            </div>
                                                            <div class="flex flex-wrap items-center justify-end gap-3 text-xs">
                                                                <span v-if="dateGroup.totals.income" class="font-medium text-green-600">
                                                                    +{{ formatCurrency(dateGroup.totals.income) }} ₪
                                                                </span>
                                                                <span v-if="dateGroup.totals.expense" class="font-medium text-red-600">
                                                                    -{{ formatCurrency(dateGroup.totals.expense) }} ₪
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-2">
                                                            <div
                                                                v-for="transaction in dateGroup.transactions"
                                                                :key="`${group.sourceId}-${dateGroup.dateKey}-${transaction.id}`"
                                                                class="rounded-md border border-white bg-white px-3 py-2 text-right shadow-sm"
                                                            >
                                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                                    <div class="flex items-center justify-end gap-3">
                                                                        <div
                                                                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-lg"
                                                                            :style="{ backgroundColor: transaction.categoryColor || (transaction.isIncome ? '#dcfce7' : '#fee2e2') }"
                                                                        >
                                                                            {{ transaction.icon }}
                                                                        </div>
                                                                        <div class="text-right">
                                                                            <p class="text-sm font-medium text-gray-900">
                                                                                {{ transaction.description }}
                                                                            </p>
                                                                            <p class="text-xs text-gray-500">
                                                                                {{ transaction.categoryName }}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex flex-col items-end gap-1 text-left sm:text-right">
                                                                        <span
                                                                            class="text-sm font-semibold"
                                                                            :class="transaction.isIncome ? 'text-green-600' : 'text-red-600'"
                                                                        >
                                                                            {{ transaction.isIncome ? '+' : '-' }}{{ formatCurrency(Math.abs(transaction.amount)) }} ₪
                                                                        </span>
                                                                        <span v-if="transaction.timeLabel" class="text-xs text-gray-400">
                                                                            {{ transaction.timeLabel }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
