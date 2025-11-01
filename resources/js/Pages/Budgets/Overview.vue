<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PeriodHeader from '@/Components/PeriodHeader.vue'
import BudgetManagerModal from '@/Components/BudgetManagerModal.vue'
import CategoryTransactionsModal from '@/Components/CategoryTransactionsModal.vue'
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
    allCategories: Array,
    budgetsForMonth: Array,
    transactionsForAssignment: Array,
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
const isBudgetModalOpen = ref(false)
const modalMode = ref('create')
const selectedCategory = ref(null)
const isTransactionsModalOpen = ref(false)
const transactionsCategory = ref(null)
const duplicatingCategoryId = ref(null)

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

const tabOptions = [
    { key: 'overview', label: 'סקירת תקציבים' },
    { key: 'assignment', label: 'שיוך לקטגוריות' },
]

const availableTabs = tabOptions.map(tab => tab.key)

const readTabFromLocation = () => {
    if (typeof window === 'undefined') {
        return 'overview'
    }

    try {
        const params = new URLSearchParams(window.location.search)
        const tab = params.get('tab')
        if (tab && availableTabs.includes(tab)) {
            return tab
        }
    } catch (error) {
        // ignore malformed URLs and fall back to overview
    }

    return 'overview'
}

const activeTab = ref(readTabFromLocation())

const tabButtonClass = (tabKey) => {
    const base = 'w-full text-center px-4 py-2 text-sm font-medium transition focus:outline-none sm:flex-1 sm:w-auto'
    if (activeTab.value === tabKey) {
        return `${base} border-b-2 border-indigo-500 text-indigo-600 bg-white`
    }

    return `${base} border-b-2 border-transparent text-gray-600 hover:text-indigo-600 hover:bg-gray-50`
}

const syncTabFromLocation = () => {
    const nextTab = readTabFromLocation()
    if (nextTab !== activeTab.value) {
        activeTab.value = nextTab
    }
}

const handlePopState = () => {
    syncTabFromLocation()
}

const isOverviewTab = computed(() => activeTab.value === 'overview')

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

const totalIncomeAmount = computed(() => Number(props.totalIncome ?? 0))

const totalPlannedBudget = computed(() => {
    return (props.categoriesWithBudgets || []).reduce((sum, category) => {
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

const categoryTypeBadgeClass = (type) => {
    if (type === 'income') {
        return 'bg-green-100 text-green-700'
    }

    if (type === 'both') {
        return 'bg-blue-100 text-blue-700'
    }

    return 'bg-red-100 text-red-700'
}

const categoryTypeLabel = (type) => {
    if (type === 'income') {
        return 'הכנסה'
    }

    if (type === 'both') {
        return 'הכנסה והוצאה'
    }

    return 'הוצאה'
}

const categoryBudgetPlaceholder = (type) => {
    if (type === 'income') {
        return 'קטגוריית הכנסה ללא תקציב'
    }

    if (type === 'both') {
        return 'קטגוריה משולבת ללא תקציב'
    }

    return 'טרם נקבע תקציב לקטגוריה'
}

const budgetProgressBarClass = (percentage) => {
    if (percentage > 90) return 'bg-red-500'
    if (percentage > 75) return 'bg-yellow-500'
    return 'bg-green-500'
}

const budgetProgressTextClass = (percentage) => {
    if (percentage > 90) return 'text-red-600'
    if (percentage > 75) return 'text-yellow-600'
    return 'text-green-600'
}

const budgetSpentClass = (budget) => {
    if (!budget) {
        return 'text-gray-600 font-semibold'
    }

    const spent = Number(budget.spent_amount ?? 0)

    if (spent < 0) {
        return 'text-green-600 font-semibold'
    }

    if (spent === 0) {
        return 'text-gray-600 font-semibold'
    }

    return 'text-red-600 font-semibold'
}

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

const normalizeAssignmentTransaction = (transaction) => {
    const primaryDate = transaction?.primary_date
        || transaction?.posting_date
        || transaction?.transaction_date
        || ''

    return {
        ...transaction,
        displayDate: primaryDate,
        categoryName: transaction?.category?.name || 'ללא קטגוריה',
        categoryType: transaction?.category?.type || null,
        cashFlowSourceName: transaction?.cash_flow_source?.name || null,
        directionClass: transaction?.type === 'income' ? 'text-green-600' : 'text-red-600',
    }
}

const assignmentTransactions = ref(
    (props.transactionsForAssignment || []).map(normalizeAssignmentTransaction)
)

const selectedAssignmentTransactionIds = ref([])

watch(
    () => props.transactionsForAssignment,
    (value) => {
        assignmentTransactions.value = (value || []).map(normalizeAssignmentTransaction)
    }
)

watch(assignmentTransactions, () => {
    const availableIds = new Set(
        assignmentTransactions.value
            .map(transaction => Number(transaction.id))
            .filter(Number.isFinite)
    )
    selectedAssignmentTransactionIds.value = selectedAssignmentTransactionIds.value.filter(
        (id) => availableIds.has(id)
    )
})

watch(selectedAssignmentTransactionIds, () => {
    assignmentFeedback.value = null
})

const assignmentTransactionsMap = computed(() => {
    const map = new Map()
    assignmentTransactions.value.forEach((transaction) => {
        if (transaction?.id !== undefined && transaction?.id !== null) {
            map.set(Number(transaction.id), transaction)
        }
    })
    return map
})

const assignmentSearchTerm = ref('')
const showOnlyUnassigned = ref(false)
const assignmentFeedback = ref(null)
const isAssignmentSubmitting = ref(false)

const hasAssignmentSelection = computed(() => selectedAssignmentTransactionIds.value.length > 0)
const selectedAssignmentCount = computed(() => selectedAssignmentTransactionIds.value.length)

const isTransactionSelected = (id) => {
    const normalizedId = Number(id)
    if (!Number.isFinite(normalizedId)) {
        return false
    }

    return selectedAssignmentTransactionIds.value.includes(normalizedId)
}

const toggleTransactionSelection = (id) => {
    const normalizedId = Number(id)

    if (!Number.isFinite(normalizedId)) {
        return
    }

    if (isTransactionSelected(normalizedId)) {
        selectedAssignmentTransactionIds.value = selectedAssignmentTransactionIds.value.filter(
            (existingId) => existingId !== normalizedId
        )
    } else {
        selectedAssignmentTransactionIds.value = [
            ...selectedAssignmentTransactionIds.value,
            normalizedId,
        ]
    }
}

const clearTransactionSelection = () => {
    selectedAssignmentTransactionIds.value = []
}

const handleTransactionRowClick = (id) => {
    if (isAssignmentSubmitting.value) {
        return
    }

    toggleTransactionSelection(id)
}

const visibleAssignmentTransactions = computed(() => {
    const search = assignmentSearchTerm.value.trim().toLowerCase()

    return assignmentTransactions.value.filter((transaction) => {
        if (showOnlyUnassigned.value && transaction.category_id) {
            return false
        }

        if (!search) {
            return true
        }

        const haystack = [
            transaction.description,
            transaction.notes,
            transaction.categoryName,
            transaction.cashFlowSourceName,
            transaction.formatted_amount,
            transaction.displayDate,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return haystack.includes(search)
    })
})

const visibleAssignmentIds = computed(() => visibleAssignmentTransactions.value
    .map(transaction => Number(transaction.id))
    .filter(Number.isFinite)
)

const isAllVisibleSelected = computed(() => {
    if (!visibleAssignmentIds.value.length) {
        return false
    }

    return visibleAssignmentIds.value.every((id) => isTransactionSelected(id))
})

const toggleSelectAllVisible = () => {
    if (!visibleAssignmentIds.value.length) {
        return
    }

    if (isAllVisibleSelected.value) {
        const toRemove = new Set(visibleAssignmentIds.value)
        selectedAssignmentTransactionIds.value = selectedAssignmentTransactionIds.value.filter(
            (id) => !toRemove.has(id)
        )
        return
    }

    const merged = new Set([
        ...selectedAssignmentTransactionIds.value,
        ...visibleAssignmentIds.value,
    ])
    selectedAssignmentTransactionIds.value = Array.from(merged)
}

const selectedAssignmentTypes = computed(() => {
    const types = new Set()
    selectedAssignmentTransactionIds.value.forEach((id) => {
        const transaction = assignmentTransactionsMap.value.get(Number(id))
        if (transaction?.type) {
            types.add(transaction.type)
        }
    })
    return Array.from(types)
})

const assignmentSelectionType = computed(() => {
    const types = selectedAssignmentTypes.value

    if (!types.length) {
        return null
    }

    if (types.length === 1) {
        return types[0]
    }

    const unique = new Set(types)
    if (unique.size === 2 && unique.has('income') && unique.has('expense')) {
        return 'both'
    }

    return null
})

const assignmentSelectionTypeLabel = computed(() => {
    const type = assignmentSelectionType.value
    return type ? categoryTypeLabel(type) : 'ללא'
})

const isCategoryTypeCompatible = (selectionType, categoryType) => {
    if (!selectionType || !categoryType) {
        return true
    }

    if (selectionType === 'both') {
        return categoryType === 'both'
    }

    if (categoryType === 'both') {
        return true
    }

    return selectionType === categoryType
}

const assignmentCategorySearchTerm = ref('')

const assignmentCategories = computed(() => {
    return (props.categoriesWithBudgets || []).map((category) => {
        const id = category.category_id ?? category.id

        return {
            id,
            name: category.category_name ?? category.name ?? 'ללא שם',
            type: category.type,
            color: category.category_color ?? category.color,
            icon: category.category_icon ?? category.icon,
            description: category.description,
            isActive: category.is_active !== false,
            budget: category.budget ?? null,
        }
    })
})

const filteredAssignmentCategories = computed(() => {
    const search = assignmentCategorySearchTerm.value.trim().toLowerCase()
    if (!search) {
        return assignmentCategories.value
    }

    return assignmentCategories.value.filter((category) => {
        const haystack = [
            category.name,
            category.description,
            categoryTypeLabel(category.type),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return haystack.includes(search)
    })
})

const isCategorySelectable = (category) => {
    if (!category || !hasAssignmentSelection.value) {
        return false
    }

    return isCategoryTypeCompatible(assignmentSelectionType.value, category.type)
}

const assignmentSelectionSummary = computed(() => {
    if (!hasAssignmentSelection.value) {
        return 'אין תזרימים שנבחרו'
    }

    return `${selectedAssignmentCount.value} תזרימים • סוג: ${assignmentSelectionTypeLabel.value}`
})

const assignTransactionsToCategory = async (category) => {
    if (!category || isAssignmentSubmitting.value) {
        return
    }

    if (!hasAssignmentSelection.value) {
        assignmentFeedback.value = 'בחר תחילה לפחות תזרים אחד לשיוך.'
        return
    }

    if (!isCategorySelectable(category)) {
        assignmentFeedback.value = 'סוג הקטגוריה אינו תואם לבחירת התזרימים הנוכחית.'
        return
    }

    const categoryId = Number(category.id)
    if (!categoryId) {
        assignmentFeedback.value = 'התרחשה שגיאה בזיהוי הקטגוריה.'
        return
    }

    isAssignmentSubmitting.value = true
    assignmentFeedback.value = null

    const httpClient = typeof window !== 'undefined' ? window.axios : null

    if (!httpClient) {
        isAssignmentSubmitting.value = false
        assignmentFeedback.value = 'לא ניתן לבצע שיוך כעת. רכיב הרשת לא זמין.'
        return
    }

    try {
        const response = await httpClient.post(
            route('budgets.manage.transactions.assign', categoryId),
            {
                transaction_ids: selectedAssignmentTransactionIds.value,
            }
        )

        const assignedIds = Array.isArray(response?.data?.assigned)
            ? response.data.assigned
            : []

        if (!assignedIds.length) {
            assignmentFeedback.value = 'לא בוצע שיוך. ודא שסוג הקטגוריה תואם לתזרימים שנבחרו.'
            isAssignmentSubmitting.value = false
            return
        }

        assignmentFeedback.value = `שיוך ${assignedIds.length} תזרימים לקטגוריה "${category.name}" הושלם בהצלחה.`
        selectedAssignmentTransactionIds.value = []

        router.reload({
            only: [
                'categoriesWithBudgets',
                'budgetsForMonth',
                'transactionsForAssignment',
                'totalIncome',
                'totalExpenses',
                'balance',
            ],
            preserveScroll: true,
            onFinish: () => {
                isAssignmentSubmitting.value = false
            },
            onError: () => {
                isAssignmentSubmitting.value = false
                assignmentFeedback.value = 'התרחשה שגיאה בעת רענון הנתונים אחרי השיוך.'
            },
        })
    } catch (error) {
        isAssignmentSubmitting.value = false
        assignmentFeedback.value = 'אירעה שגיאה בעת ביצוע השיוך. נסה שוב.'
    }
}

const persistPeriod = (year, month) => {
    if (typeof window === 'undefined') return
    savePeriod(Number(year), Number(month))
}

const navigateToPeriod = (year, month, options = {}) => {
    const normalizedYear = Number(year)
    const normalizedMonth = normalizeMonthForYear(normalizedYear, month)
    persistPeriod(normalizedYear, normalizedMonth)
    router.visit(route('budgets.overview', {
        year: normalizedYear,
        month: normalizedMonth,
        tab: activeTab.value,
    }), {
        preserveScroll: true,
        replace: true,
        ...options,
    })
}

const changeTab = (tabKey) => {
    if (!tabKey || !availableTabs.includes(tabKey) || tabKey === activeTab.value) {
        return
    }

    activeTab.value = tabKey

    const normalizedMonth = normalizeMonthForYear(selectedYear.value, selectedMonth.value)
    if (normalizedMonth !== selectedMonth.value) {
        selectedMonth.value = normalizedMonth
    }

    router.visit(route('budgets.overview', {
        year: selectedYear.value,
        month: normalizedMonth,
        tab: tabKey,
    }), {
        preserveScroll: true,
        replace: true,
        preserveState: true,
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
    syncTabFromLocation()
    if (typeof window !== 'undefined') {
        window.addEventListener('popstate', handlePopState)
    }
    tryApplyStoredPeriod()
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return
    }

    window.removeEventListener('popstate', handlePopState)
})

const openNewCategoryModal = () => {
    modalMode.value = 'create'
    selectedCategory.value = null
    isBudgetModalOpen.value = true
}

const openCategoryModal = (category) => {
    modalMode.value = category?.budget ? 'edit' : 'create-existing'
    selectedCategory.value = category
    isBudgetModalOpen.value = true
}

const closeBudgetModal = () => {
    isBudgetModalOpen.value = false
    selectedCategory.value = null
}

const handleModalSaved = () => {
    closeBudgetModal()
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const openTransactionsModal = (category) => {
    transactionsCategory.value = category
    isTransactionsModalOpen.value = true
}

const duplicateCategory = (category) => {
    if (!category) {
        return
    }

    const categoryId = category.category_id || category.id
    duplicatingCategoryId.value = categoryId

    router.post(
        route('budgets.manage.category.duplicate', categoryId),
        {
            year: Number(selectedYear.value),
            month: Number(selectedMonth.value),
            planned_amount:
                category.budget?.planned_amount ?? null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                duplicatingCategoryId.value = null
            },
            onSuccess: () => {
                navigateToPeriod(selectedYear.value, selectedMonth.value)
            },
        }
    )
}

const closeTransactionsModal = () => {
    isTransactionsModalOpen.value = false
    transactionsCategory.value = null
}

const confirmCategoryDelete = (category) => {
    if (!category) {
        return
    }

    if (!confirm('למחוק את הקטגוריה הזו? העסקאות שלה יישארו ללא קטגוריה.')) {
        return
    }

    const budgetId = category.budget?.id

    if (budgetId) {
        router.delete(route('budgets.manage.destroy', budgetId), {
            preserveScroll: true,
            data: { remove_category: true },
            onSuccess: () => navigateToPeriod(selectedYear.value, selectedMonth.value),
        })
    } else {
        router.delete(route('budgets.manage.destroy_category', category.category_id || category.id), {
            preserveScroll: true,
            onSuccess: () => navigateToPeriod(selectedYear.value, selectedMonth.value),
        })
    }
}

const hasCategories = computed(() => Array.isArray(props.categoriesWithBudgets) && props.categoriesWithBudgets.length > 0)
</script>

<template>
    <Head title="קטגוריות ותקציבים" />

    <AuthenticatedLayout>
        <template #header>
            <PeriodHeader
                :metrics="headerMetrics"
                :selected-year="selectedYear"
                :selected-month="selectedMonth"
                :period-display="periodDisplay"
                :year-options="yearOptions"
                :month-options="monthOptions"
                summary-order="start"
                summary-wrapper-class="w-full lg:flex-1 lg:min-w-[280px]"
                @update:year="handleYearUpdate"
                @update:month="handleMonthUpdate"
                @today="handleToday"
            >
                <template #summary>
                    <div class="h-full rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between lg:flex-col lg:items-end lg:text-left">
                            <div>
                                <p class="text-xs text-indigo-700">
                                    סך התקציבים המתוכננים עבור {{ selectedMonthLabel }} {{ selectedYear }}.
                                </p>
                            </div>
                            <div class="text-sm font-semibold text-indigo-900">
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
                </template>
            </PeriodHeader>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 bg-gray-50/70">
                        <nav class="flex flex-col sm:flex-row">
                            <button
                                v-for="tab in tabOptions"
                                :key="tab.key"
                                type="button"
                                :class="tabButtonClass(tab.key)"
                                @click="changeTab(tab.key)"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <div class="p-6" v-if="isOverviewTab">
                        
                        <div v-if="hasCategories" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="category in categoriesWithBudgets"
                                :key="category.category_id"
                                class="flex h-full flex-col gap-4 rounded-lg border-2 border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
                                :class="{
                                    'border-indigo-300 bg-indigo-50': category.budget,
                                    'opacity-75': category.is_active === false,
                                }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-12 w-12 items-center justify-center rounded-md text-3xl shadow-sm ring-1 ring-black/5"
                                            :style="{
                                                backgroundColor: category.category_color || '#E5E7EB',
                                                color: getContrastingTextColor(category.category_color || '#E5E7EB'),
                                            }"
                                            aria-hidden="true"
                                        >
                                            {{ category.category_icon || '📁' }}
                                        </span>
                                        <div class="text-right">
                                            <h4 class="text-lg font-semibold text-gray-900">{{ category.category_name }}</h4>
                                            <div class="mt-2 flex items-center justify-end gap-2 text-xs">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 font-medium" :class="categoryTypeBadgeClass(category.type)">
                                                    {{ categoryTypeLabel(category.type) }}
                                                </span>
                                                <span
                                                    v-if="category.is_active === false"
                                                    class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-1 font-medium text-gray-700"
                                                >
                                                    לא פעילה
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            class="inline-flex items-center rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-50"
                                            @click.stop="openCategoryModal(category)"
                                        >
                                            ✏️ עריכה
                                        </button>
                                        <button
                                            class="inline-flex items-center rounded-md border border-green-200 px-3 py-1.5 text-xs font-semibold text-green-600 transition-colors hover:bg-green-50 disabled:opacity-60"
                                            :disabled="duplicatingCategoryId === (category.category_id || category.id)"
                                            @click.stop="duplicateCategory(category)"
                                        >
                                            <svg
                                                v-if="duplicatingCategoryId === (category.category_id || category.id)"
                                                class="-ml-1 mr-2 h-4 w-4 animate-spin text-green-600"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            📄 שכפול
                                        </button>
                                        <button
                                            class="inline-flex items-center rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                                            @click.stop="confirmCategoryDelete(category)"
                                        >
                                            🗑️ מחיקה
                                        </button>
                                    </div>
                                </div>

                                <div v-if="category.description" class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                    {{ category.description }}
                                </div>

                                <div v-if="category.budget" class="rounded-md border border-gray-200 bg-white p-3 text-sm shadow-sm">
                                    <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                                        <span>תקציב לחודש</span>
                                        <span>{{ formatCurrency(category.budget.planned_amount) }} ₪ מתוכנן</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span>הוצאה נטו</span>
                                        <span :class="budgetSpentClass(category.budget)">{{ formatCurrency(category.budget.spent_amount) }} ₪</span>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between text-sm">
                                        <span>נותר</span>
                                        <span :class="category.budget.remaining_amount >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">
                                            {{ formatCurrency(category.budget.remaining_amount) }} ₪
                                        </span>
                                    </div>
                                    <div class="mt-3 h-2 w-full rounded-full bg-gray-200">
                                        <div
                                            class="h-2 rounded-full transition-all duration-300"
                                            :class="budgetProgressBarClass(category.budget.progress_percentage)"
                                            :style="{ width: Math.min(category.budget.progress_percentage, 100) + '%' }"
                                        ></div>
                                    </div>
                                    <div class="mt-1 text-center text-xs font-medium" :class="budgetProgressTextClass(category.budget.progress_percentage)">
                                        {{ category.budget.progress_percentage }}% נוצל
                                    </div>
                                </div>

                                <div v-else class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-center text-sm text-gray-500">
                                    {{ categoryBudgetPlaceholder(category.type) }}
                                </div>

                                <div class="mt-auto text-center">
                                    <button class="text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-800" @click.stop="openTransactionsModal(category)">
                                        ניהול עסקאות
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-12 text-center text-gray-500">
                            אין קטגוריות להצגה עבור התקופה שנבחרה
                        </div>
                    </div>

                    <div class="p-6" v-else>
                        <div class="flex flex-col gap-6">
                            <div class="flex flex-col-reverse gap-3 text-right sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">שיוך עסקאות לקטגוריות</h3>
                                    <p class="text-sm text-gray-500">
                                        בחר תזרימים מהרשימה, ולאחר מכן לחץ על הקטגוריה המתאימה כדי להשלים את השיוך.
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-2 sm:flex-row sm:items-center">
                                    <span class="text-sm text-gray-600">
                                        {{ assignmentSelectionSummary }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!hasAssignmentSelection || isAssignmentSubmitting"
                                        @click="clearTransactionSelection"
                                    >
                                        נקה בחירה
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="assignmentFeedback"
                                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                            >
                                {{ assignmentFeedback }}
                            </div>

                            <div
                                v-if="isAssignmentSubmitting"
                                class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700"
                            >
                                מבצע שיוך... אנא המתן לסיום העדכון.
                            </div>

                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-stretch">
                                <div class="lg:col-span-1">
                                    <div class="flex h-full min-h-0 flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                        <div class="flex flex-col gap-3">
                                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                                <input
                                                    id="show-unassigned"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    v-model="showOnlyUnassigned"
                                                >
                                                <label for="show-unassigned" class="cursor-pointer select-none">
                                                    הצג רק עסקאות ללא קטגוריה
                                                </label>
                                            </div>
                                            
                                        </div>

                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span>סה"כ תזרימים בחודש: {{ assignmentTransactions.length }}</span>
                                            <!-- <button
                                                type="button"
                                                class="text-indigo-600 transition hover:text-indigo-800 disabled:cursor-not-allowed disabled:opacity-60"
                                                @click="toggleSelectAllVisible"
                                                :disabled="!visibleAssignmentIds.length || isAssignmentSubmitting"
                                            >
                                                {{ isAllVisibleSelected ? 'בטל בחירה בכל הנראים' : 'בחר את כל הנראים' }}
                                            </button> -->
                                        </div>
                                        <div class="relative w-full">
                                                <input
                                                    type="search"
                                                    v-model="assignmentSearchTerm"
                                                    placeholder="חיפוש בתיאור, מקור או קטגוריה..."
                                                    class="w-full rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm text-right shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                                >
                                                <svg
                                                    class="pointer-events-none absolute inset-y-0 left-2 h-5 w-5 text-gray-400"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="1.5"
                                                    stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607-10.607 7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            </div>

                                        <div class="flex-1 overflow-hidden rounded-md border border-gray-200">
                                            <div class="h-full max-h-[520px] overflow-x-auto overflow-y-auto">
                                                <table class="min-w-full divide-y divide-gray-200 text-right">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col" class="w-12 px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                            <input
                                                                type="checkbox"
                                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                                                :checked="isAllVisibleSelected"
                                                                :disabled="!visibleAssignmentIds.length || isAssignmentSubmitting"
                                                                @change="toggleSelectAllVisible"
                                                            >
                                                        </th>
                                                        <th scope="col" class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">תיאור</th>
                                                        <th scope="col" class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">תאריך</th>
                                                        <th scope="col" class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">סכום</th>
                                                        <th scope="col" class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">קטגוריה נוכחית</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white">
                                                    <template v-if="visibleAssignmentTransactions.length">
                                                        <tr
                                                            v-for="transaction in visibleAssignmentTransactions"
                                                            :key="transaction.id"
                                                            class="transition hover:bg-gray-50"
                                                            :class="{
                                                                'bg-indigo-50/50': isTransactionSelected(transaction.id),
                                                                'cursor-pointer': !isAssignmentSubmitting,
                                                                'cursor-not-allowed': isAssignmentSubmitting,
                                                            }"
                                                            @click="handleTransactionRowClick(transaction.id)"
                                                        >
                                                            <td class="px-3 py-3 text-center">
                                                                <input
                                                                    type="checkbox"
                                                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                                                    :checked="isTransactionSelected(transaction.id)"
                                                                    :disabled="isAssignmentSubmitting"
                                                                    @change.stop="toggleTransactionSelection(transaction.id)"
                                                                    @click.stop
                                                                >
                                                            </td>
                                                            <td class="px-3 py-3 align-top">
                                                                <div class="flex flex-col gap-1 text-right">
                                                                    <span class="text-sm font-medium text-gray-900">
                                                                        {{ transaction.description || 'ללא תיאור' }}
                                                                    </span>
                                                                    <span class="inline-flex w-fit items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">
                                                                        {{ categoryTypeLabel(transaction.type) }}
                                                                    </span>
                                                                    <span
                                                                        v-if="transaction.cashFlowSourceName"
                                                                        class="text-xs text-gray-500"
                                                                    >
                                                                        מקור: {{ transaction.cashFlowSourceName }}
                                                                    </span>
                                                                    <span
                                                                        v-if="transaction.notes"
                                                                        class="text-xs text-gray-400"
                                                                    >
                                                                        הערות: {{ transaction.notes }}
                                                                    </span>
                                                                    <span
                                                                        v-if="transaction.status"
                                                                        class="text-[11px] text-gray-400"
                                                                    >
                                                                        סטטוס: {{ transaction.status }}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-3 py-3 align-top text-sm text-gray-600">
                                                                {{ transaction.displayDate || '—' }}
                                                            </td>
                                                            <td class="px-3 py-3 align-top text-sm font-semibold" :class="transaction.directionClass">
                                                                {{ transaction.formatted_amount }} ₪
                                                            </td>
                                                            <td class="px-3 py-3 align-top">
                                                                <div class="flex flex-col items-end">
                                                                    <template v-if="transaction.category">
                                                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                                                            <span aria-hidden="true">{{ transaction.category.icon || '🏷️' }}</span>
                                                                            <span>{{ transaction.categoryName }}</span>
                                                                        </span>
                                                                        <span class="text-[11px] text-gray-500">
                                                                            סוג: {{ categoryTypeLabel(transaction.categoryType) }}
                                                                        </span>
                                                                    </template>
                                                                    <span v-else class="text-xs font-medium text-gray-400">
                                                                        ללא קטגוריה
                                                                    </span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                    <tr v-else>
                                                        <td colspan="5" class="px-3 py-12 text-center text-sm text-gray-500">
                                                            אין תזרימים מתאימים להצגה.
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="lg:col-span-1">
                                    <div class="flex h-full flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                        <div class="flex flex-col gap-1 text-right">
                                            <h4 class="text-base font-semibold text-gray-900">קטגוריות זמינות</h4>
                                            <p class="text-xs text-gray-500">
                                                לחיצה על קטגוריה תשייך אליה את כל התזרימים שנבחרו.
                                            </p>
                                            <span class="text-xs text-gray-500">
                                                קטגוריות פעילות: {{ assignmentCategories.length }}
                                            </span>
                                        </div>

                                        <div class="relative">
                                            <input
                                                type="search"
                                                v-model="assignmentCategorySearchTerm"
                                                placeholder="חיפוש לפי שם קטגוריה או תיאור..."
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm text-right shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            >
                                            <svg
                                                class="pointer-events-none absolute inset-y-0 left-2 h-5 w-5 text-gray-400"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0a7.5 7.5 0 1 0-10.607-10.607 7.5 7.5 0 0 0 10.607 10.607Z" />
                                            </svg>
                                        </div>

                                        <div class="max-h-[520px] space-y-3 overflow-y-auto pr-1">
                                            <template v-if="assignmentCategories.length">
                                                <template v-if="filteredAssignmentCategories.length">
                                                <button
                                                    v-for="category in filteredAssignmentCategories"
                                                    :key="category.id"
                                                    type="button"
                                                    class="w-full rounded-lg border border-gray-200 bg-white p-3 text-right transition hover:border-indigo-300 hover:bg-indigo-50 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                                    :class="{
                                                        'border-indigo-300 bg-indigo-50/70': isCategorySelectable(category) && hasAssignmentSelection && !isAssignmentSubmitting,
                                                    }"
                                                    :disabled="isAssignmentSubmitting || (hasAssignmentSelection && !isCategorySelectable(category))"
                                                    @click="assignTransactionsToCategory(category)"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="flex items-center gap-3">
                                                            <span
                                                                class="inline-flex h-10 w-10 items-center justify-center rounded-md text-2xl shadow-sm ring-1 ring-black/5"
                                                                :style="{
                                                                    backgroundColor: category.color || '#E5E7EB',
                                                                    color: getContrastingTextColor(category.color || '#E5E7EB'),
                                                                }"
                                                                aria-hidden="true"
                                                            >
                                                                {{ category.icon || '📁' }}
                                                            </span>
                                                            <div class="text-right">
                                                                <p class="text-sm font-semibold text-gray-900">
                                                                    {{ category.name }}
                                                                </p>
                                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">
                                                                    {{ categoryTypeLabel(category.type) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <svg
                                                            v-if="hasAssignmentSelection && isCategorySelectable(category)"
                                                            class="h-5 w-5 text-indigo-500"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke-width="1.5"
                                                            stroke="currentColor"
                                                        >
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                        </svg>
                                                    </div>

                                                    <p v-if="category.description" class="mt-2 text-xs text-gray-500">
                                                        {{ category.description }}
                                                    </p>

                                                    <div v-if="category.budget" class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-xs">
                                                        <div class="flex items-center justify-between text-gray-600">
                                                            <span>מתוכנן</span>
                                                            <span>{{ formatCurrency(category.budget.planned_amount) }} ₪</span>
                                                        </div>
                                                        <div class="mt-1 flex items-center justify-between">
                                                            <span>הוצאה</span>
                                                            <span :class="budgetSpentClass(category.budget)">
                                                                {{ formatCurrency(category.budget.spent_amount) }} ₪
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div v-else class="mt-3 rounded-md border border-dashed border-gray-300 bg-white px-3 py-2 text-xs text-gray-500">
                                                        ללא תקציב לחודש שנבחר
                                                    </div>
                                                </button>
                                                </template>
                                                <div v-else class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-12 text-center text-sm text-gray-500">
                                                    אין קטגוריות שמתאימות לחיפוש שבחרת.
                                                </div>
                                            </template>
                                            <div v-else class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-12 text-center text-sm text-gray-500">
                                                אין קטגוריות פעילות. ניתן להוסיף קטגוריה חדשה בעזרת הכפתור למעלה.
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="mt-auto inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            @click="openNewCategoryModal"
                                        >
                                            הוסף קטגוריה חדשה
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <BudgetManagerModal
            :show="isBudgetModalOpen"
            :mode="modalMode"
            :category="selectedCategory"
            :year="selectedYear"
            :month="selectedMonth"
            @close="closeBudgetModal"
            @saved="handleModalSaved"
            @deleted="handleModalSaved"
        />

        <CategoryTransactionsModal
            :show="isTransactionsModalOpen"
            :category="transactionsCategory"
            :year="selectedYear"
            :month="selectedMonth"
            :categories="props.allCategories"
            :cash-flow-sources="props.cashFlowSources"
            :budgets="props.budgetsForMonth"
            @close="closeTransactionsModal"
        />

    </AuthenticatedLayout>
</template>
