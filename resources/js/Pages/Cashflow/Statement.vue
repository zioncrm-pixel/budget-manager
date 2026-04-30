<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TransactionAddModal from '@/Components/TransactionAddModal.vue'
import PeriodHeader from '@/Components/PeriodHeader.vue'
import Modal from '@/Components/Modal.vue'
import BudgetManagerModal from '@/Components/BudgetManagerModal.vue'
import { loadPeriod, savePeriod } from '@/utils/periodStorage'

const props = defineProps({
    user: Object,
    currentYear: Number,
    currentMonth: Number,
    totalIncome: Number,
    totalExpenses: Number,
    balance: Number,
    accountStatus: Number,
    accountStatementRows: Array,
    allTransactions: Array,
    categoriesWithBudgets: Array,
    cashFlowSources: Array,
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
const selectedAccountRow = ref(null)
const isTransactionModalOpen = ref(false)
const modalMode = ref('create')
const editingTransaction = ref(null)

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

const localAccountStatementRows = ref(props.accountStatementRows?.map(row => ({ ...row })) || [])

const defaultAccountSort = () => ({ key: 'date', direction: 'desc' })
const defaultTransactionSort = () => ({ key: 'date', direction: 'desc' })

const accountSort = ref(defaultAccountSort())
const accountSearchQuery = ref('')
const transactionSort = ref({ key: null, direction: null })
const transactionSearchQuery = ref('')
const duplicatingSourceId = ref(null)
const duplicatingTransactionId = ref(null)
const selectedTransactionIds = ref([])
const selectedAccountTransactionIds = ref([])
const isBulkDuplicateModalOpen = ref(false)
const bulkDuplicateDate = ref('')
const bulkDuplicateError = ref(null)
const isBulkSubmitting = ref(false)
const isBulkDeleting = ref(false)
const bulkActionContext = ref('transactions')
const transactionDayFilter = ref(null)
const isDayFilterOpen = ref(false)
const dayFilterContainer = ref(null)
const selectAllTransactionsCheckbox = ref(null)
const selectAllAccountRowsCheckbox = ref(null)
const assignCategoryContext = ref('account')
const isAssignCategoryModalOpen = ref(false)
const isAssignCategorySubmitting = ref(false)
const assignCategorySelectedId = ref('')
const assignCategoryError = ref(null)
const isCategoryCreateModalOpen = ref(false)
const pendingAssignContext = ref(null)
const lastProcessedCategoryId = ref(null)
const page = usePage()
const csrfToken = typeof document !== 'undefined'
    ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    : ''

const transactionsById = computed(() => {
    const map = new Map()
    ;(props.allTransactions || []).forEach((transaction) => {
        if (transaction?.id !== undefined && transaction?.id !== null) {
            map.set(Number(transaction.id), transaction)
        }
    })
    return map
})

const assignCategorySelectionIds = computed(() => (assignCategoryContext.value === 'account'
    ? selectedAccountTransactionIds.value
    : selectedTransactionIds.value))

const assignCategorySelectionCount = computed(() => assignCategorySelectionIds.value.length)

const assignCategorySelectionTypes = computed(() => {
    const types = new Set()
    assignCategorySelectionIds.value.forEach((id) => {
        const transaction = transactionsById.value.get(Number(id))
        if (transaction?.type) {
            types.add(transaction.type)
        }
    })
    return Array.from(types)
})

const assignCategoryType = computed(() => {
    const types = assignCategorySelectionTypes.value

    if (types.length === 0) {
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

const assignableCategories = computed(() => {
    const type = assignCategoryType.value
    if (!type) {
        return []
    }

    return (props.categoriesWithBudgets || []).filter((category) => {
        const categoryType = (category.type || category.category_type || '').toString()
        return isCategoryTypeCompatible(type, categoryType)
    })
})

const isAssignCategoryReady = computed(() => Boolean(assignCategorySelectedId.value) && Boolean(assignCategoryType.value) && assignableCategories.value.length > 0)

const getCategoryOptionId = (category) => {
    const id = category?.category_id ?? category?.id
    return id !== undefined && id !== null ? String(id) : ''
}

const getCategoryOptionLabel = (category) => category?.category_name || category?.name || 'ללא שם'

const getSelectionIdsForContext = (context) => (context === 'account'
    ? selectedAccountTransactionIds.value
    : selectedTransactionIds.value)

const hasSelectionForContext = (context) => (context === 'account'
    ? hasAccountSelection.value
    : hasTransactionSelection.value)

const clearSelectionForContext = (context) => {
    if (context === 'account') {
        selectedAccountTransactionIds.value = []
    } else {
        selectedTransactionIds.value = []
    }
}

watch(assignableCategories, (categories) => {
    if (!isAssignCategoryModalOpen.value) {
        return
    }

    if (!categories.length) {
        assignCategorySelectedId.value = ''
        return
    }

    const exists = categories.some((category) => getCategoryOptionId(category) === assignCategorySelectedId.value)
    if (!exists) {
        assignCategorySelectedId.value = getCategoryOptionId(categories[0])
    }
})

watch(assignCategorySelectionIds, () => {
    assignCategorySelectedId.value = ''
    assignCategoryError.value = null
})

watch(
    () => page.props?.value?.flash?.created_category,
    async (created) => {
        if (!created || !created.id || !pendingAssignContext.value) {
            return
        }

        if (lastProcessedCategoryId.value === created.id) {
            return
        }

        lastProcessedCategoryId.value = created.id

        const { context, selectionIds, type } = pendingAssignContext.value
        pendingAssignContext.value = null

        closeCategoryCreateModal()

        if (type && created.type && !isCategoryTypeCompatible(type, created.type)) {
            assignCategoryError.value = 'הקטגוריה החדשה אינה תואמת לסוג התזרימים שנבחרו.'
            return
        }

        assignCategoryContext.value = context

        if (context === 'account') {
            selectedAccountTransactionIds.value = selectionIds.map(Number)
        } else {
            selectedTransactionIds.value = selectionIds.map(Number)
        }

        await nextTick()

        const createdId = String(created.id)
        const ensureCategoryExists = () => assignableCategories.value.some(
            (category) => getCategoryOptionId(category) === createdId
        )

        const proceedWithAssignment = async () => {
            assignCategorySelectedId.value = createdId
            assignCategoryError.value = null

            if (!isAssignCategoryModalOpen.value) {
                isAssignCategoryModalOpen.value = true
            }

            await submitAssignCategory()
        }

        if (!ensureCategoryExists()) {
            router.reload({
                only: ['categoriesWithBudgets'],
                preserveState: true,
                onSuccess: async () => {
                    await nextTick()
                    if (!ensureCategoryExists()) {
                        assignCategoryError.value = 'הקטגוריה החדשה לא נטענה. נסה לרענן את העמוד.'
                        return
                    }
                    await proceedWithAssignment()
                },
            })
            return
        }

        await proceedWithAssignment()
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
    router.visit(`/cashflow?year=${normalizedYear}&month=${normalizedMonth}`, {
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

const toTimestamp = (value) => {
    if (value === null || value === undefined) {
        return 0
    }

    if (typeof value === 'number') {
        return value > 1e12 ? value : value * 1000
    }

    if (typeof value === 'string' && value.includes('/')) {
        const parts = value.split('/').map(Number)
        if (parts.length === 3 && parts.every(Number.isFinite)) {
            const [day, monthIndex, year] = parts
            return Date.UTC(year, monthIndex - 1, day)
        }
    }

    const parsed = Date.parse(value)
    return Number.isNaN(parsed) ? 0 : parsed
}

const accountSorters = {
    description: (row) => (row.source_name || '').toString().toLowerCase(),
    date: (row) => {
        if (row.type === 'individual_transaction') {
            if (row.transaction_data?.posting_date) {
                return toTimestamp(row.transaction_data.posting_date)
            }
            if (row.transaction_data?.transaction_date) {
                return toTimestamp(row.transaction_data.transaction_date)
            }
            if (row.transaction_date) {
                return toTimestamp(row.transaction_date)
            }
        }
        return row.sort_timestamp ?? row.updated_timestamp ?? 0
    },
    type: (row) => (row.transaction_type || '').toString().toLowerCase(),
    amount: (row) => {
        if (row.total_amount !== undefined && row.total_amount !== null) {
            return Number(row.total_amount)
        }
        if (row.transaction_data?.amount !== undefined) {
            return Number(row.transaction_data.amount)
        }
        return 0
    },
}

const transactionSorters = {
    date: (transaction) => toTimestamp(transaction.transaction_date || transaction.posting_date),
    posting_date: (transaction) => toTimestamp(transaction.posting_date || transaction.transaction_date),
    description: (transaction) => (transaction.description || '').toString().toLowerCase(),
    category: (transaction) => (transaction.category?.name || '').toString().toLowerCase(),
    amount: (transaction) => Number(transaction.amount || 0),
    status: (transaction) => (transaction.status || '').toString().toLowerCase(),
}

const transactionsForSelectedRow = computed(() => {
    if (!selectedAccountRow.value) return []

    let items = []

    if (selectedAccountRow.value.type === 'cash_flow_source') {
        const allTransactions = props.allTransactions || []
        items = allTransactions.filter(transaction =>
            transaction.cash_flow_source_id === selectedAccountRow.value.cash_flow_source_id &&
            (
                selectedAccountRow.value.allows_refunds
                    ? true
                    : transaction.type === selectedAccountRow.value.transaction_type
            )
        )
    } else if (selectedAccountRow.value.type === 'individual_transaction') {
        items = [selectedAccountRow.value.transaction_data]
    }

    return sortTransactions(items)
})

const getTransactionDateKey = (value) => {
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.valueOf())) {
        return null
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const filteredTransactions = computed(() => {
    let items = transactionsForSelectedRow.value

    if (selectedAccountRow.value?.type === 'cash_flow_source') {
        const query = transactionSearchQuery.value
        if (query && query.trim()) {
            items = items.filter(transaction => transactionMatchesQuery(transaction, query))
        }
    }

    if (transactionDayFilter.value) {
        return items.filter(transaction => getTransactionDateKey(transaction.posting_date || transaction.transaction_date) === transactionDayFilter.value)
    }

    return items
})

const availableTransactionDays = computed(() => {
    if (!selectedAccountRow.value) {
        return []
    }

    const items = transactionsForSelectedRow.value
    const year = Number(selectedYear.value)
    const month = Number(selectedMonth.value)
    const daysInMonth = new Date(year, month, 0).getDate()
    const transactionsSet = new Set(
        items.map(transaction => getTransactionDateKey(transaction.posting_date || transaction.transaction_date))
    )

    const results = []

    for (let day = 1; day <= daysInMonth; day += 1) {
        const key = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        results.push({
            key,
            label: new Date(year, month - 1, day).toLocaleDateString('he-IL'),
            hasTransactions: transactionsSet.has(key),
        })
    }

    return results
})

const transactionDayLabel = computed(() => {
    if (!transactionDayFilter.value) {
        return 'כל הימים'
    }

    return new Date(transactionDayFilter.value).toLocaleDateString('he-IL')
})

const formatDateForInput = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.valueOf())) {
        return ''
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const bulkMinDate = computed(() => formatDateForInput(new Date(selectedYear.value, selectedMonth.value - 1, 1)))
const hasTransactionSelection = computed(() => selectedTransactionIds.value.length > 0)
const selectedTransactionsCount = computed(() => selectedTransactionIds.value.length)
const areAllTransactionsSelected = computed(() => {
    const transactions = filteredTransactions.value
    if (!transactions.length) {
        return false
    }

    const selectedSet = new Set(selectedTransactionIds.value)
    return transactions.every(transaction => selectedSet.has(transaction.id))
})
const filteredAccountStatementRows = computed(() => {
    const rows = sortedAccountStatementRows.value || []
    const query = accountSearchQuery.value

    if (!query || !query.trim()) {
        return rows
    }

    return rows.filter(row => accountRowMatchesQuery(row, query))
})

const selectableAccountRows = computed(() =>
    (filteredAccountStatementRows.value || []).filter(row => row.type === 'individual_transaction' && row.transaction_id)
)
const hasAccountSelection = computed(() => selectedAccountTransactionIds.value.length > 0)
const selectedAccountCount = computed(() => selectedAccountTransactionIds.value.length)
const areAllAccountRowsSelected = computed(() => {
    const rows = selectableAccountRows.value
    if (!rows.length) {
        return false
    }

    const selectedSet = new Set(selectedAccountTransactionIds.value)
    return rows.every(row => selectedSet.has(Number(row.transaction_id)))
})
const bulkSelectionCount = computed(() => (bulkActionContext.value === 'account' ? selectedAccountCount.value : selectedTransactionsCount.value))
const bulkSelectionLabel = computed(() => (bulkActionContext.value === 'account' ? 'שורות עו"ש' : 'תזרימים'))

const compareValues = (a, b) => {
    if (a === b) return 0
    if (typeof a === 'string' && typeof b === 'string') {
        return a.localeCompare(b, 'he', { sensitivity: 'base' })
    }
    return (a ?? 0) > (b ?? 0) ? 1 : -1
}

const sortAccountRows = (rows) => {
    if (!Array.isArray(rows)) return []

    const { key, direction } = accountSort.value
    if (!key || !direction) {
        return [...rows]
    }

    const sorter = accountSorters[key]
    if (!sorter) {
        return [...rows]
    }

    const multiplier = direction === 'asc' ? 1 : -1
    return [...rows].sort((a, b) => compareValues(sorter(a), sorter(b)) * multiplier)
}

const sortTransactions = (transactions) => {
    if (!Array.isArray(transactions)) return []

    const { key, direction } = transactionSort.value
    if (!key || !direction) {
        return [...transactions]
    }

    const sorter = transactionSorters[key]
    if (!sorter) {
        return [...transactions]
    }

    const multiplier = direction === 'asc' ? 1 : -1
    return [...transactions].sort((a, b) => compareValues(sorter(a), sorter(b)) * multiplier)
}

const cycleDirection = (current) => {
    if (current === 'desc') return 'asc'
    if (current === 'asc') return null
    return 'desc'
}

const toggleAccountSort = (key) => {
    if (accountSort.value.key === key) {
        const nextDirection = cycleDirection(accountSort.value.direction)
        accountSort.value = nextDirection ? { key, direction: nextDirection } : { key: null, direction: null }
    } else {
        accountSort.value = { key, direction: 'desc' }
    }
}

const toggleTransactionSort = (key) => {
    if (transactionSort.value.key === key) {
        const nextDirection = cycleDirection(transactionSort.value.direction)
        transactionSort.value = nextDirection ? { key, direction: nextDirection } : { key: null, direction: null }
    } else {
        transactionSort.value = { key, direction: 'desc' }
    }
}

const getSortIcon = (state, key) => {
    if (state.key !== key || !state.direction) {
        return ''
    }

    return state.direction === 'asc' ? '▲' : '▼'
}

const accountSortIcon = (key) => getSortIcon(accountSort.value, key)
const transactionSortIcon = (key) => getSortIcon(transactionSort.value, key)

const isAccountColumnSorted = (key) => accountSort.value.key === key && !!accountSort.value.direction
const isTransactionColumnSorted = (key) => transactionSort.value.key === key && !!transactionSort.value.direction

const sortedAccountStatementRows = computed(() => sortAccountRows(localAccountStatementRows.value))

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
        label: 'מצב העו\"ש',
        value: formatCurrencyWithSymbol(props.accountStatus),
        valueClass: 'text-gray-900',
    },
])

const getTransactionTypeColor = (type) => {
    return type === 'income' 
        ? 'bg-green-100 text-green-800' 
        : 'bg-red-100 text-red-800'
}

const getTransactionTypeIcon = (type) => {
    return type === 'income' ? '📈' : '📉'
}

const getTransactionTypeName = (type) => {
    return type === 'income' ? 'הכנסה' : 'הוצאה'
}

const formatDisplayDate = (value) => {
    if (value === null || value === undefined) {
        return '-'
    }

    let date

    if (value instanceof Date) {
        date = value
    } else if (typeof value === 'number') {
        const normalized = value > 1e12 ? value : value * 1000
        date = new Date(normalized)
    } else {
        date = new Date(value)
    }

    if (Number.isNaN(date.valueOf())) {
        return '-'
    }

    return date.toLocaleDateString('he-IL')
}

const getRowDisplayDate = (row) => {
    if (!row) {
        return '-'
    }

    if (row.type === 'individual_transaction') {
        if (row.transaction_data?.posting_date) {
            return formatDisplayDate(row.transaction_data.posting_date)
        }
        if (row.transaction_data?.transaction_date) {
            return formatDisplayDate(row.transaction_data.transaction_date)
        }
        if (row.transaction_date) {
            return formatDisplayDate(row.transaction_date)
        }
    }

    return formatDisplayDate(
        row.latest_posting_at ||
        row.latest_updated_at ||
        row.latest_created_at ||
        row.updated_timestamp ||
        row.created_timestamp
    )
}

const getRowCategoryLabel = (row) => {
    if (!row) {
        return '-'
    }

    if (row.type === 'individual_transaction') {
        return row.category_name || 'לא מוגדר'
    }

    return row.transaction_type === 'income' ? 'מקור הכנסה' : 'מקור הוצאה'
}

const normalizeSearchValue = (value) => {
    if (value === undefined || value === null) {
        return ''
    }
    return String(value).toLowerCase()
}

const accountRowMatchesQuery = (row, query) => {
    if (!query) {
        return true
    }

    const normalizedQuery = query.trim().toLowerCase()
    if (!normalizedQuery) {
        return true
    }

    const fields = [
        row.source_name,
        row.category_name,
        row.transaction_type,
        row.formatted_amount,
        row.transaction_count,
    ]

    if (row.type === 'cash_flow_source') {
        fields.push(row.source_icon)
    }

    if (row.transaction_data) {
        fields.push(
            row.transaction_data.description,
            row.transaction_data.notes,
            row.transaction_data.reference_number,
            row.transaction_data.amount,
            row.transaction_data.category?.name,
            row.transaction_data.cash_flow_source?.name
        )
    }

    if (row.transaction_type) {
        fields.push(getTransactionTypeName(row.transaction_type))
    }

    return fields
        .filter((value) => value !== undefined && value !== null && value !== '')
        .some((value) => normalizeSearchValue(value).includes(normalizedQuery))
}

const transactionMatchesQuery = (transaction, query) => {
    if (!query) {
        return true
    }

    const normalizedQuery = query.trim().toLowerCase()
    if (!normalizedQuery) {
        return true
    }

    const fields = [
        transaction.description,
        transaction.notes,
        transaction.reference_number,
        transaction.amount,
        transaction.type,
        transaction.status,
        transaction.category?.name,
        transaction.cash_flow_source?.name,
    ]

    return fields
        .filter((value) => value !== undefined && value !== null && value !== '')
        .some((value) => normalizeSearchValue(value).includes(normalizedQuery))
}

const isRowExpanded = (row) => selectedAccountRow.value?.id === row?.id

const selectAccountRow = (row) => {
    if (selectedAccountRow.value?.id === row.id) {
        clearSelection()
        return
    }

    selectedAccountRow.value = row
    selectedTransactionIds.value = []
    transactionDayFilter.value = null
    isDayFilterOpen.value = false
    if (!transactionSort.value.key || !transactionSort.value.direction) {
        transactionSort.value = defaultTransactionSort()
    }
}

const clearSelection = () => {
    selectedAccountRow.value = null
    transactionSort.value = { key: null, direction: null }
    selectedTransactionIds.value = []
    selectedAccountTransactionIds.value = []
    transactionDayFilter.value = null
    isDayFilterOpen.value = false
}

const clearAccountRowSelection = () => {
    selectedAccountTransactionIds.value = []
}

const openAssignCategoryModal = (context = 'account') => {
    const selectionIds = getSelectionIdsForContext(context)

    if (!selectionIds.length) {
        return
    }

    assignCategoryContext.value = context

    assignCategoryError.value = null

    if (assignableCategories.value.length) {
        assignCategorySelectedId.value = getCategoryOptionId(assignableCategories.value[0])
    } else {
        assignCategorySelectedId.value = ''
    }

    isAssignCategoryModalOpen.value = true
}

const closeAssignCategoryModal = () => {
    isAssignCategoryModalOpen.value = false
    assignCategoryError.value = null
    assignCategorySelectedId.value = ''
    assignCategoryContext.value = 'account'
    pendingAssignContext.value = null
}

const openCategoryCreateModal = () => {
    pendingAssignContext.value = {
        context: assignCategoryContext.value,
        selectionIds: assignCategorySelectionIds.value.map((id) => Number(id)),
        type: assignCategoryType.value || null,
    }
    isCategoryCreateModalOpen.value = true
}

const closeCategoryCreateModal = () => {
    isCategoryCreateModalOpen.value = false
    pendingAssignContext.value = null
}

const submitAssignCategory = async () => {
    if (!isAssignCategoryReady.value) {
        if (!assignCategoryType.value) {
            assignCategoryError.value = 'בחר תזרימים מאותו סוג, או קטגוריה משולבת המתאימה לתערובת של הכנסות והוצאות.'
        } else if (!assignableCategories.value.length) {
            assignCategoryError.value = 'לא קיימות קטגוריות מתאימות לשיוך.'
        } else if (!assignCategorySelectedId.value) {
            assignCategoryError.value = 'בחר קטגוריה לשיוך.'
        }
        return
    }

    isAssignCategorySubmitting.value = true
    assignCategoryError.value = null

    try {
        const transactionIds = assignCategorySelectionIds.value
            .map(id => Number(id))
            .filter(Number.isFinite)

        if (!transactionIds.length) {
            assignCategoryError.value = 'לא נמצאו תזרימים תקינים לשיוך.'
            return
        }

        const response = await fetch(route('budgets.manage.transactions.assign', assignCategorySelectedId.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                transaction_ids: transactionIds,
            }),
        })

        if (!response.ok) {
            const data = await response.json().catch(() => null)
            assignCategoryError.value = data?.message || 'נכשלה פעולת השיוך. נסה שוב.'
            return
        }

        const context = assignCategoryContext.value
        closeAssignCategoryModal()
        clearSelectionForContext(context)
        navigateToPeriod(selectedYear.value, selectedMonth.value)
    } catch (error) {
        console.error(error)
        assignCategoryError.value = 'אירעה שגיאה בשיוך הקטגוריה.'
    } finally {
        isAssignCategorySubmitting.value = false
    }
}

const handleCategoryCreateSaved = () => {
    closeCategoryCreateModal()
}

const openCreateModal = () => {
    modalMode.value = 'create'
    editingTransaction.value = null
    isTransactionModalOpen.value = true
}

const openEditModal = (transaction) => {
    modalMode.value = 'edit'
    editingTransaction.value = { ...transaction }
    isTransactionModalOpen.value = true
}

const closeTransactionModal = () => {
    isTransactionModalOpen.value = false
    editingTransaction.value = null
}

const handleTransactionSaved = () => {
    closeTransactionModal()
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleTransactionDeleted = () => {
    closeTransactionModal()
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const confirmDelete = (transaction) => {
    if (!transaction) return
    if (!confirm('האם למחוק את התזרים הזה? הפעולה בלתי הפיכה.')) {
        return
    }

    router.delete(route('transactions.destroy', transaction.id), {
        preserveScroll: true,
        onSuccess: () => navigateToPeriod(selectedYear.value, selectedMonth.value),
    })
}

const duplicateTransaction = (transaction) => {
    if (!transaction?.id) {
        return
    }

    duplicatingTransactionId.value = transaction.id

    router.post(route('transactions.duplicate', transaction.id), {
        year: Number(selectedYear.value),
        month: Number(selectedMonth.value),
    }, {
        preserveScroll: true,
        onSuccess: () => navigateToPeriod(selectedYear.value, selectedMonth.value),
        onFinish: () => {
            duplicatingTransactionId.value = null
        },
    })
}

const duplicateCashFlowSource = (row) => {
    const sourceId = row?.cash_flow_source_id
    if (!sourceId) {
        return
    }

    duplicatingSourceId.value = sourceId

    router.post(route('cashflow.sources.duplicate', sourceId), {
        year: Number(selectedYear.value),
        month: Number(selectedMonth.value),
        with_transactions: true,
    }, {
        preserveScroll: true,
        onSuccess: () => navigateToPeriod(selectedYear.value, selectedMonth.value),
        onFinish: () => {
            duplicatingSourceId.value = null
        },
    })
}

const isDuplicatingTransaction = (id) => duplicatingTransactionId.value === id
const isDuplicatingSource = (id) => duplicatingSourceId.value === id
const isTransactionSelected = (id) => selectedTransactionIds.value.includes(id)
const toggleTransactionSelection = (id, checked) => {
    if (!id) {
        return
    }

    const set = new Set(selectedTransactionIds.value)
    if (checked) {
        set.add(id)
    } else {
        set.delete(id)
    }

    selectedTransactionIds.value = Array.from(set)
}

const toggleSelectAllTransactions = (checked) => {
    if (checked) {
        selectedTransactionIds.value = filteredTransactions.value.map(transaction => transaction.id)
    } else {
        selectedTransactionIds.value = []
    }
}

const isAccountRowSelectable = (row) => row?.type === 'individual_transaction' && Boolean(row?.transaction_id)
const isAccountRowSelected = (row) => {
    if (!isAccountRowSelectable(row)) {
        return false
    }
    const idValue = Number(row.transaction_id)
    return selectedAccountTransactionIds.value.includes(idValue)
}

const toggleAccountRowSelection = (row, checked) => {
    if (!isAccountRowSelectable(row)) {
        return
    }

    const idValue = Number(row.transaction_id)
    if (!Number.isFinite(idValue)) {
        return
    }

    const set = new Set(selectedAccountTransactionIds.value)
    if (checked) {
        set.add(idValue)
    } else {
        set.delete(idValue)
    }

    selectedAccountTransactionIds.value = Array.from(set)
}

const toggleSelectAllAccountRows = (checked) => {
    if (checked) {
        selectedAccountTransactionIds.value = selectableAccountRows.value
            .map(row => Number(row.transaction_id))
            .filter(Number.isFinite)
    } else {
        selectedAccountTransactionIds.value = []
    }
}

const getDefaultBulkDuplicateDate = () => {
    const today = new Date()
    if (today.getFullYear() !== selectedYear.value || (today.getMonth() + 1) !== selectedMonth.value) {
        return bulkMinDate.value
    }

    const monthEndDay = new Date(selectedYear.value, selectedMonth.value, 0).getDate()
    const day = Math.min(today.getDate(), monthEndDay)
    return formatDateForInput(new Date(selectedYear.value, selectedMonth.value - 1, day))
}

const openBulkDuplicateModal = (context = 'transactions') => {
    if (!hasSelectionForContext(context)) {
        return
    }

    bulkActionContext.value = context
    bulkDuplicateError.value = null
    bulkDuplicateDate.value = getDefaultBulkDuplicateDate()
    isBulkDuplicateModalOpen.value = true
}

const closeBulkDuplicateModal = () => {
    isBulkDuplicateModalOpen.value = false
    bulkDuplicateError.value = null
    bulkActionContext.value = 'transactions'
}

const submitBulkDuplicate = () => {
    const selectionIds = getSelectionIdsForContext(bulkActionContext.value)

    if (!selectionIds.length || !bulkDuplicateDate.value) {
        bulkDuplicateError.value = 'בחר תאריך לשכפול'
        return
    }

    isBulkSubmitting.value = true
    bulkDuplicateError.value = null

    router.post(route('transactions.duplicate.bulk'), {
        transaction_ids: selectionIds,
        date: bulkDuplicateDate.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeBulkDuplicateModal()
            clearSelectionForContext(bulkActionContext.value)
            navigateToPeriod(selectedYear.value, selectedMonth.value)
        },
        onError: (errors) => {
            bulkDuplicateError.value = errors.date || errors.transaction_ids || errors.error || 'אירעה שגיאה בשכפול התזרימים'
        },
        onFinish: () => {
            isBulkSubmitting.value = false
        },
    })
}

const confirmBulkDelete = (context = 'transactions') => {
    if (!hasSelectionForContext(context)) {
        return
    }

    if (!confirm('למחוק את כל התזרימים שנבחרו? הפעולה בלתי הפיכה.')) {
        return
    }

    isBulkDeleting.value = true

    router.post(route('transactions.delete.bulk'), {
        transaction_ids: getSelectionIdsForContext(context),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            clearSelectionForContext(context)
            navigateToPeriod(selectedYear.value, selectedMonth.value)
        },
        onError: (errors) => {
            alert(errors.transaction_ids || errors.error || 'אירעה שגיאה במחיקת התזרימים')
        },
        onFinish: () => {
            isBulkDeleting.value = false
        },
    })
}

const toggleDayFilter = () => {
    if (!availableTransactionDays.value.length) {
        return
    }

    isDayFilterOpen.value = !isDayFilterOpen.value
}

const selectTransactionDay = (key) => {
    transactionDayFilter.value = key
    isDayFilterOpen.value = false
}

const clearTransactionDayFilter = () => {
    transactionDayFilter.value = null
    isDayFilterOpen.value = false
}

const handleDayFilterOutsideClick = (event) => {
    if (!isDayFilterOpen.value) {
        return
    }

    const container = dayFilterContainer.value
    if (container && !container.contains(event.target)) {
        isDayFilterOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleDayFilterOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDayFilterOutsideClick)
})

watch(() => props.accountStatementRows, (newRows) => {
    if (newRows) {
        const previousSelectionId = selectedAccountRow.value?.id || null
        localAccountStatementRows.value = newRows.map(row => ({ ...row }))
        if (previousSelectionId) {
            const matchedRow = localAccountStatementRows.value.find(row => row.id === previousSelectionId)
            selectedAccountRow.value = matchedRow || null
        }

        const allowedAccountIds = newRows
            .filter(row => row.type === 'individual_transaction' && row.transaction_id)
            .map(row => Number(row.transaction_id))
            .filter(Number.isFinite)
        const allowedSet = new Set(allowedAccountIds)
        selectedAccountTransactionIds.value = selectedAccountTransactionIds.value.filter(id => allowedSet.has(id))
    }
}, { immediate: true, deep: true })

watch(filteredAccountStatementRows, (rows) => {
    if (!selectedAccountRow.value) {
        return
    }

    const exists = rows.some(row => row.id === selectedAccountRow.value.id)
    if (!exists) {
        selectedAccountRow.value = null
    }
})

watch(selectableAccountRows, (rows) => {
    const allowedSet = new Set(
        rows
            .map(row => Number(row.transaction_id))
            .filter(Number.isFinite)
    )
    selectedAccountTransactionIds.value = selectedAccountTransactionIds.value.filter(id => allowedSet.has(id))
}, { immediate: true })

watch(selectedAccountRow, (row) => {
    if (!row) {
        transactionSort.value = { key: null, direction: null }
        selectedTransactionIds.value = []
        transactionDayFilter.value = null
        isDayFilterOpen.value = false
        transactionSearchQuery.value = ''
    } else {
        selectedTransactionIds.value = []
        transactionDayFilter.value = null
        isDayFilterOpen.value = false
        transactionSearchQuery.value = ''
    }
})

watch(filteredTransactions, (transactions) => {
    const allowedIds = new Set((transactions || []).map(transaction => transaction.id))
    selectedTransactionIds.value = selectedTransactionIds.value.filter(id => allowedIds.has(id))
}, { immediate: true })

watch([hasTransactionSelection, areAllTransactionsSelected], () => {
    if (selectAllTransactionsCheckbox.value) {
        selectAllTransactionsCheckbox.value.indeterminate = hasTransactionSelection.value && !areAllTransactionsSelected.value
    }
})

watch([hasAccountSelection, areAllAccountRowsSelected], () => {
    if (selectAllAccountRowsCheckbox.value) {
        selectAllAccountRowsCheckbox.value.indeterminate = hasAccountSelection.value && !areAllAccountRowsSelected.value
    }
})

watch(availableTransactionDays, (days) => {
    if (transactionDayFilter.value && !days.some(day => day.key === transactionDayFilter.value)) {
        transactionDayFilter.value = null
    }
})

watch(bulkMinDate, (min) => {
    if (!bulkDuplicateDate.value) {
        return
    }

    if (bulkDuplicateDate.value < min) {
        bulkDuplicateDate.value = min
    }
})
</script>

<template>
    <Head title="שורות עו&quot;ש" />

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

        <div class="py-2">
            <div class="mx-auto max-w-screen-2xl sm:px-6 lg:px-8">
                <div class="rounded-lg border border-gray-200 bg-white shadow">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3" dir="rtl">
                        <div class="flex flex-col text-right">
                            <span class="text-sm font-semibold text-gray-900">שורות עו&quot;ש</span>
                            <span class="text-xs text-gray-500">לחץ על שורה כדי לפתוח את פירוט העסקאות.</span>
                        </div>
                        <div class="flex flex-1 min-w-[220px] justify-center">
                            <div class="relative w-full max-w-xs">
                                <label for="account-search-input" class="sr-only">חיפוש בשורות עו&quot;ש</label>
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                                    </svg>
                                </span>
                                <input
                                    id="account-search-input"
                                    v-model="accountSearchQuery"
                                    type="text"
                                    class="w-full rounded-md border border-gray-300 bg-white py-2 pr-3 pl-9 text-right text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="חיפוש בשורות עו&quot;ש"
                                />
                                <button
                                    v-if="accountSearchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-3 flex items-center text-gray-400 transition hover:text-gray-600"
                                    @click="accountSearchQuery = ''"
                                >
                                    <span class="sr-only">נקה חיפוש</span>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                @click="openCreateModal"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                </svg>
                                הוסף תזרים
                            </button>
                        </div>
                    </div>

                    <div v-if="hasAccountSelection" class="flex flex-wrap items-center justify-end gap-2 border-b border-indigo-100 bg-indigo-50 px-4 py-2 text-xs text-gray-700" dir="rtl">
                        <span class="font-semibold text-gray-800">נבחרו {{ selectedAccountCount }} תזרימים בודדים</span>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-indigo-200 bg-white px-3 py-1.5 font-semibold text-indigo-700 transition-colors hover:bg-indigo-50"
                            @click="openAssignCategoryModal('account')"
                        >
                            🗂️ שיוך לקטגוריה
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-green-200 bg-white px-3 py-1.5 font-semibold text-green-700 transition-colors hover:bg-green-50"
                            :disabled="isBulkSubmitting"
                            @click="openBulkDuplicateModal('account')"
                        >
                            📄 שכפל
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-red-200 bg-white px-3 py-1.5 font-semibold text-red-600 transition-colors hover:bg-red-50 disabled:opacity-60"
                            :disabled="isBulkDeleting"
                            @click="confirmBulkDelete('account')"
                        >
                            🗑️ מחק
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-600 transition-colors hover:bg-gray-100"
                            @click="clearAccountRowSelection"
                        >
                            נקה בחירה
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table dir="rtl" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input
                                            ref="selectAllAccountRowsCheckbox"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            :checked="areAllAccountRowsSelected"
                                            @change="toggleSelectAllAccountRows($event.target.checked)"
                                            :disabled="!selectableAccountRows.length"
                                        />
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <button
                                            type="button"
                                            class="flex w-full flex-row-reverse items-center justify-end gap-1 text-right transition hover:text-indigo-500"
                                            :class="isAccountColumnSorted('date') ? 'text-indigo-600' : 'text-gray-600'"
                                            @click="toggleAccountSort('date')"
                                        >
                                            <span>תאריך</span>
                                            <span v-if="accountSortIcon('date')" class="text-xs">{{ accountSortIcon('date') }}</span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <button
                                            type="button"
                                            class="flex w-full flex-row-reverse items-center justify-end gap-1 text-right transition hover:text-indigo-500"
                                            :class="isAccountColumnSorted('description') ? 'text-indigo-600' : 'text-gray-600'"
                                            @click="toggleAccountSort('description')"
                                        >
                                            <span>תיאור</span>
                                            <span v-if="accountSortIcon('description')" class="text-xs">{{ accountSortIcon('description') }}</span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        קטגוריה
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <button
                                            type="button"
                                            class="flex w-full flex-row-reverse items-center justify-end gap-1 text-right transition hover:text-indigo-500"
                                            :class="isAccountColumnSorted('type') ? 'text-indigo-600' : 'text-gray-600'"
                                            @click="toggleAccountSort('type')"
                                        >
                                            <span>סוג</span>
                                            <span v-if="accountSortIcon('type')" class="text-xs">{{ accountSortIcon('type') }}</span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <button
                                            type="button"
                                            class="flex w-full flex-row-reverse items-center justify-end gap-1 text-right transition hover:text-indigo-500"
                                            :class="isAccountColumnSorted('amount') ? 'text-indigo-600' : 'text-gray-600'"
                                            @click="toggleAccountSort('amount')"
                                        >
                                            <span>סכום</span>
                                            <span v-if="accountSortIcon('amount')" class="text-xs">{{ accountSortIcon('amount') }}</span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template v-for="row in filteredAccountStatementRows" :key="row.id">
                                    <tr
                                        class="cursor-pointer transition-colors"
                                        :class="{
                                            'bg-indigo-50': isRowExpanded(row),
                                            'hover:bg-indigo-50': true
                                        }"
                                        @click="selectAccountRow(row)"
                                    >
                                        <td class="px-4 py-4 text-center">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                :disabled="!isAccountRowSelectable(row)"
                                                :checked="isAccountRowSelected(row)"
                                                @click.stop
                                                @change="toggleAccountRowSelection(row, $event.target.checked)"
                                            />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right">
                                            {{ getRowDisplayDate(row) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            <div class="flex items-center gap-2 text-right">
                                                <span v-if="row.type === 'cash_flow_source'" class="text-lg">{{ row.source_icon || '📊' }}</span>
                                                <div class="flex flex-col text-right">
                                                    <span class="font-medium text-gray-900">{{ row.source_name }}</span>
                                                    <span v-if="row.type === 'cash_flow_source'" class="text-xs text-gray-500">
                                                        {{ row.transaction_count }} עסקאות בחודש
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                                <span v-if="row.type === 'individual_transaction'">
                                                    {{ row.source_icon || '📄' }}
                                                </span>
                                                {{ getRowCategoryLabel(row) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getTransactionTypeColor(row.transaction_type)">
                                                {{ getTransactionTypeIcon(row.transaction_type) }}
                                                {{ getTransactionTypeName(row.transaction_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right" :class="row.amount_color">
                                            {{ row.formatted_amount }} ₪
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <template v-if="row.can_add_transactions">
                                                    <button
                                                        class="inline-flex items-center rounded-md border border-green-200 bg-white px-2 py-1 text-xs font-semibold text-green-600 transition-colors hover:bg-green-50 disabled:opacity-60"
                                                        :disabled="isDuplicatingSource(row.cash_flow_source_id)"
                                                        @click.stop="duplicateCashFlowSource(row)"
                                                    >
                                                        📄 שכפל מקור
                                                    </button>
                                                    <button
                                                        class="inline-flex items-center rounded-md border border-indigo-200 bg-white px-2 py-1 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-50"
                                                        @click.stop="openCreateModal"
                                                    >
                                                        ➕ הוסף עסקה
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button
                                                        class="inline-flex items-center rounded-md border border-green-200 bg-white px-2 py-1 text-xs font-semibold text-green-600 transition-colors hover:bg-green-50 disabled:opacity-60"
                                                        :disabled="isDuplicatingTransaction(row.transaction_data?.id)"
                                                        @click.stop="duplicateTransaction(row.transaction_data)"
                                                    >
                                                        📄
                                                    </button>
                                                    <button
                                                        class="inline-flex items-center rounded-md border border-indigo-200 bg-white px-2 py-1 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-50"
                                                        @click.stop="openEditModal(row.transaction_data)"
                                                    >
                                                        ✏️
                                                    </button>
                                                    <button
                                                        class="inline-flex items-center rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                                                        @click.stop="confirmDelete(row.transaction_data)"
                                                    >
                                                        🗑️
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="isRowExpanded(row)" class="bg-indigo-50/40">
                                        <td colspan="7" class="px-4 py-4">
                                            <div class="space-y-4 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm shadow-indigo-100/70">
                                                <div class="flex flex-wrap items-center justify-between gap-3" dir="rtl">
                                                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600">
                                                        <template v-if="row.type === 'cash_flow_source' && row.allows_refunds">
                                                            <span class="flex items-center gap-1">
                                                                <span class="font-semibold text-red-600">הוצאות:</span>
                                                                {{ formatCurrency(row.total_expense_amount || 0) }} ₪
                                                            </span>
                                                            <span class="flex items-center gap-1">
                                                                <span class="font-semibold text-green-600">זיכויים:</span>
                                                                {{ formatCurrency(row.total_income_amount || 0) }} ₪
                                                            </span>
                                                            <span class="flex items-center gap-1">
                                                                <span class="font-semibold text-gray-700">נטו:</span>
                                                                {{ row.formatted_amount }} ₪
                                                            </span>
                                                        </template>
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-2" dir="rtl">
                                                        <div
                                                            v-if="row.type === 'cash_flow_source'"
                                                            class="relative"
                                                        >
                                                            <label :for="`transaction-search-${row.id}`" class="sr-only">
                                                                חיפוש ב-{{ row.source_name || 'מקור התזרים' }}
                                                            </label>
                                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                                                                </svg>
                                                            </span>
                                                            <input
                                                                :id="`transaction-search-${row.id}`"
                                                                v-model="transactionSearchQuery"
                                                                type="text"
                                                                class="w-52 rounded-md border border-gray-300 bg-white py-1.5 pr-2 pl-8 text-right text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                                                                :placeholder="`חיפוש ב-${row.source_name || 'מקור התזרים'}`"
                                                            />
                                                            <button
                                                                v-if="transactionSearchQuery"
                                                                type="button"
                                                                class="absolute inset-y-0 right-2 flex items-center text-gray-400 transition hover:text-gray-600"
                                                                @click.stop="transactionSearchQuery = ''"
                                                            >
                                                                <span class="sr-only">נקה חיפוש</span>
                                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <button
                                                            v-if="hasTransactionSelection"
                                                            type="button"
                                                        class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                                                            @click.stop="openAssignCategoryModal('transactions')"
                                                        >
                                                            🗂️ שיוך לקטגוריה
                                                        </button>
                                                        <button
                                                        v-if="selectedTransactionsCount"
                                                            type="button"
                                                            class="inline-flex items-center rounded-md border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:bg-green-100 disabled:cursor-not-allowed disabled:opacity-60"
                                                            :disabled="!hasTransactionSelection"
                                                            @click.stop="openBulkDuplicateModal('transactions')"
                                                        >
                                                            📄 שכפל נבחרים
                                                            <span class="ml-1">({{ selectedTransactionsCount }})</span>
                                                        </button>
                                                        <button
                                                        v-if="hasTransactionSelection || isBulkDeleting"
                                                            type="button"
                                                        class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                                                            :disabled="!hasTransactionSelection || isBulkDeleting"
                                                            @click.stop="confirmBulkDelete('transactions')"
                                                        >
                                                            <svg
                                                                v-if="!hasTransactionSelection"
                                                                class="-ml-1 mr-1 h-4 w-4 animate-spin text-red-700"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle v-if="isBulkDeleting" class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            🗑️ מחק נבחרים
                                                        </button>
                                                        <button
                                                        v-if="hasTransactionSelection"
                                                            type="button"
                                                            class="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-gray-100"
                                                            :disabled="!hasTransactionSelection"
                                                            @click.stop="clearSelectionForContext('transactions')"
                                                        >
                                                            נקה בחירה
                                                        </button>
                                                        <div ref="dayFilterContainer" class="relative">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                                :class="transactionDayFilter ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-100'"
                                                                :disabled="!availableTransactionDays.length"
                                                                @click.stop="toggleDayFilter"
                                                            >
                                                                📅 {{ transactionDayLabel }}
                                                                <svg class="ml-2 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                                </svg>
                                                            </button>
                                                            <div
                                                                v-if="isDayFilterOpen"
                                                                class="absolute z-20 mt-2 w-48 rounded-md border border-gray-200 bg-white p-2 text-right shadow-lg"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="flex w-full items-center justify-between rounded px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                                                    @click.stop="clearTransactionDayFilter"
                                                                >
                                                                    כל הימים
                                                                    <span v-if="!transactionDayFilter" class="text-xs text-indigo-600">נבחר</span>
                                                                </button>
                                                                <div class="mt-1 max-h-48 overflow-y-auto">
                                                                    <button
                                                                        v-for="day in availableTransactionDays"
                                                                        :key="day.key"
                                                                        type="button"
                                                                        :class="[
                                                                            'flex w-full items-center justify-between rounded px-2 py-1 text-sm',
                                                                            day.hasTransactions ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'
                                                                        ]"
                                                                        :disabled="!day.hasTransactions"
                                                                        @click.stop="selectTransactionDay(day.key)"
                                                                    >
                                                                        {{ day.label }}
                                                                        <span v-if="transactionDayFilter === day.key" class="text-xs text-indigo-600">נבחר</span>
                                                                    </button>
                                                                    <p v-if="!availableTransactionDays.length" class="px-2 py-1 text-xs text-gray-500">אין תזרימים להצגה</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                                                    <table dir="rtl" class="min-w-full divide-y divide-gray-200">
                                                        <thead class="bg-gray-50">
                                                            <tr>
                                                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <input
                                                                        ref="selectAllTransactionsCheckbox"
                                                                        type="checkbox"
                                                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                                        :checked="areAllTransactionsSelected"
                                                                        @change="toggleSelectAllTransactions($event.target.checked)"
                                                                        :disabled="!filteredTransactions.length"
                                                                    />
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <button
                                                                        type="button"
                                                                        class="flex flex-row-reverse items-center justify-between gap-1 text-right transition hover:text-indigo-500"
                                                                        :class="isTransactionColumnSorted('date') ? 'text-indigo-600' : 'text-gray-600'"
                                                                        @click="toggleTransactionSort('date')"
                                                                    >
                                                                        <span>תאריך</span>
                                                                        <span v-if="transactionSortIcon('date')" class="text-xs">{{ transactionSortIcon('date') }}</span>
                                                                    </button>
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <button
                                                                        type="button"
                                                                        class="flex flex-row-reverse items-center justify-between gap-1 text-right transition hover:text-indigo-500"
                                                                        :class="isTransactionColumnSorted('posting_date') ? 'text-indigo-600' : 'text-gray-600'"
                                                                        @click="toggleTransactionSort('posting_date')"
                                                                    >
                                                                        <span>תאריך חיוב</span>
                                                                        <span v-if="transactionSortIcon('posting_date')" class="text-xs">{{ transactionSortIcon('posting_date') }}</span>
                                                                    </button>
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <button
                                                                        type="button"
                                                                        class="flex flex-row-reverse items-center justify-between gap-1 text-right transition hover:text-indigo-500"
                                                                        :class="isTransactionColumnSorted('description') ? 'text-indigo-600' : 'text-gray-600'"
                                                                        @click="toggleTransactionSort('description')"
                                                                    >
                                                                        <span>תיאור</span>
                                                                        <span v-if="transactionSortIcon('description')" class="text-xs">{{ transactionSortIcon('description') }}</span>
                                                                    </button>
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <button
                                                                        type="button"
                                                                        class="flex flex-row-reverse items-center justify-between gap-1 text-right transition hover:text-indigo-500"
                                                                        :class="isTransactionColumnSorted('category') ? 'text-indigo-600' : 'text-gray-600'"
                                                                        @click="toggleTransactionSort('category')"
                                                                    >
                                                                        <span>קטגוריה</span>
                                                                        <span v-if="transactionSortIcon('category')" class="text-xs">{{ transactionSortIcon('category') }}</span>
                                                                    </button>
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <button
                                                                        type="button"
                                                                        class="flex flex-row-reverse items-center justify-between gap-1 text-right transition hover:text-indigo-500"
                                                                        :class="isTransactionColumnSorted('amount') ? 'text-indigo-600' : 'text-gray-600'"
                                                                        @click="toggleTransactionSort('amount')"
                                                                    >
                                                                        <span>סכום</span>
                                                                        <span v-if="transactionSortIcon('amount')" class="text-xs">{{ transactionSortIcon('amount') }}</span>
                                                                    </button>
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    <button
                                                                        type="button"
                                                                        class="flex flex-row-reverse items-center justify-between gap-1 text-right transition hover:text-indigo-500"
                                                                        :class="isTransactionColumnSorted('status') ? 'text-indigo-600' : 'text-gray-600'"
                                                                        @click="toggleTransactionSort('status')"
                                                                    >
                                                                        <span>סטטוס</span>
                                                                        <span v-if="transactionSortIcon('status')" class="text-xs">{{ transactionSortIcon('status') }}</span>
                                                                    </button>
                                                                </th>
                                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-200">
                                                            <tr v-for="transaction in filteredTransactions" :key="transaction.id" class="hover:bg-gray-50">
                                                                <td class="px-4 py-4 text-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                                        :checked="isTransactionSelected(transaction.id)"
                                                                        @change.stop="toggleTransactionSelection(transaction.id, $event.target.checked)"
                                                                    />
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                                    {{
                                                                        transaction.transaction_date
                                                                            ? new Date(transaction.transaction_date).toLocaleDateString('he-IL')
                                                                            : transaction.posting_date
                                                                                ? new Date(transaction.posting_date).toLocaleDateString('he-IL')
                                                                                : '-'
                                                                    }}
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                                    {{ transaction.posting_date ? new Date(transaction.posting_date).toLocaleDateString('he-IL') : '-' }}
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                                    {{ transaction.description }}
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="text-lg">{{ transaction.category?.icon || '📊' }}</span>
                                                                        <span class="font-medium">{{ transaction.category?.name || 'לא מוגדר' }}</span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right" :class="getTransactionTypeColor(transaction.type)">
                                                                    {{ formatCurrency(transaction.amount) }} ₪
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                                                        {{ transaction.status === 'completed' ? 'הושלם' : transaction.status === 'pending' ? 'ממתין' : 'בוטל' }}
                                                                    </span>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                                    <div class="flex items-center justify-end gap-2">
                                                                        <button
                                                                            class="inline-flex items-center rounded-md border border-green-200 bg-white px-2 py-1 text-xs font-semibold text-green-600 transition-colors hover:bg-green-50 disabled:opacity-60"
                                                                            :disabled="isDuplicatingTransaction(transaction.id)"
                                                                            @click.stop="duplicateTransaction(transaction)"
                                                                        >
                                                                            📄
                                                                        </button>
                                                                        <button
                                                                            class="inline-flex items-center rounded-md border border-indigo-200 bg-white px-2 py-1 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-50"
                                                                            @click.stop="openEditModal(transaction)"
                                                                        >
                                                                            ✏️
                                                                        </button>
                                                                        <button
                                                                            class="inline-flex items-center rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                                                                            @click.stop="confirmDelete(transaction)"
                                                                        >
                                                                            🗑️
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="!filteredTransactions.length">
                                                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                                                    אין עסקאות להצגה עבור מקור זה.
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!filteredAccountStatementRows.length">
                                    <td colspan="7" class="px-6 py-6 text-center text-sm text-gray-500">
                                        אין נתוני עו&quot;ש לתקופה זו.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <TransactionAddModal
            :show="isTransactionModalOpen"
            :mode="modalMode"
            :transaction="editingTransaction"
            :categories="props.categoriesWithBudgets"
            :cash-flow-sources="props.cashFlowSources"
            :budgets="[]"
            :current-year="selectedYear"
            :current-month="selectedMonth"
            @close="closeTransactionModal"
            @transaction-added="handleTransactionSaved"
            @transaction-updated="handleTransactionSaved"
            @transaction-deleted="handleTransactionDeleted"
        />

        <BudgetManagerModal
            :show="isCategoryCreateModalOpen"
            mode="create"
            :category="null"
            :year="selectedYear"
            :month="selectedMonth"
            @close="closeCategoryCreateModal"
            @saved="handleCategoryCreateSaved"
        />

        <Modal :show="isAssignCategoryModalOpen" @close="closeAssignCategoryModal">
            <div class="space-y-4 p-6" dir="rtl">
                <h2 class="text-lg font-semibold text-gray-900">שיוך לקטגוריה</h2>
                <p class="text-sm text-gray-600">
                    בחר קטגוריה לשיוך {{ assignCategorySelectionCount }} תזרימים שנבחרו.
                </p>

                <div
                    v-if="!assignCategoryType"
                    class="rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-700"
                >
                    ניתן לשייך קטגוריה רק לתזרימים מאותו סוג, או לקטגוריה משולבת כאשר יש תזרימים משני הסוגים.
                </div>

                <div
                    v-else-if="!assignableCategories.length"
                    class="rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-700"
                >
                    לא נמצאו קטגוריות מתאימות מסוג זה.
                </div>

                <div v-else class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="assign-category" class="text-sm font-medium text-gray-700">קטגוריה</label>
                        <button
                            type="button"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                            @click="openCategoryCreateModal"
                        >
                            ➕ צור קטגוריה חדשה
                        </button>
                    </div>
                    <select
                        id="assign-category"
                        v-model="assignCategorySelectedId"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">בחר קטגוריה</option>
                        <option
                            v-for="category in assignableCategories"
                            :key="getCategoryOptionId(category)"
                            :value="getCategoryOptionId(category)"
                        >
                            {{ getCategoryOptionLabel(category) }}
                        </option>
                    </select>
                    <p v-if="assignCategoryError" class="text-xs text-red-600">{{ assignCategoryError }}</p>
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100"
                        @click="closeAssignCategoryModal"
                    >
                        ביטול
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="!isAssignCategoryReady || isAssignCategorySubmitting"
                        @click="submitAssignCategory"
                    >
                        <svg
                            v-if="isAssignCategorySubmitting"
                            class="-ml-1 mr-2 h-4 w-4 animate-spin text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        שמור
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="isBulkDuplicateModalOpen" @close="closeBulkDuplicateModal">
            <div class="space-y-4 p-6" dir="rtl">
                <h2 class="text-lg font-semibold text-gray-900">שכפול {{ bulkSelectionLabel }}</h2>
                <p class="text-sm text-gray-600">
                    התהליך ישכפל {{ bulkSelectionCount }} {{ bulkSelectionLabel }} לתאריך חדש.
                </p>

                <div class="space-y-2">
                    <label for="bulk-duplicate-date" class="block text-sm font-medium text-gray-700">תאריך חדש</label>
                    <input
                        id="bulk-duplicate-date"
                        v-model="bulkDuplicateDate"
                        type="date"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :min="bulkMinDate"
                    />
                    <p v-if="bulkDuplicateError" class="text-sm text-red-600">{{ bulkDuplicateError }}</p>
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100"
                        @click="closeBulkDuplicateModal"
                    >
                        ביטול
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="!bulkDuplicateDate || isBulkSubmitting"
                        @click="submitBulkDuplicate"
                    >
                        <svg
                            v-if="isBulkSubmitting"
                            class="-ml-1 mr-2 h-4 w-4 animate-spin text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        שכפל
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
