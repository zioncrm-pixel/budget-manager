<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TransactionAddModal from '@/Components/TransactionAddModal.vue'
import PeriodSelector from '@/Components/PeriodSelector.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    user: Object,
    currentYear: Number,
    currentMonth: Number,
    totalIncome: Number,
    totalExpenses: Number,
    balance: Number,
    accountStatementRows: Array,
    allTransactions: Array,
    categoriesWithBudgets: Array,
    cashFlowSources: Array,
})

const selectedYear = ref(Number(props.currentYear) || new Date().getFullYear())
const selectedMonth = ref(Number(props.currentMonth) || new Date().getMonth() + 1)
const selectedAccountRow = ref(null)
const isTransactionModalOpen = ref(false)
const modalMode = ref('create')
const editingTransaction = ref(null)

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

const localAccountStatementRows = ref(props.accountStatementRows?.map(row => ({ ...row })) || [])

const defaultAccountSort = () => ({ key: 'date', direction: 'desc' })
const defaultTransactionSort = () => ({ key: 'date', direction: 'desc' })

const accountSort = ref(defaultAccountSort())
const transactionSort = ref({ key: null, direction: null })
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

const navigateToPeriod = (year, month) => {
    router.visit(`/cashflow?year=${year}&month=${month}`, {
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
    date: (transaction) => toTimestamp(transaction.transaction_date),
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
            transaction.type === selectedAccountRow.value.transaction_type
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
    const items = transactionsForSelectedRow.value

    if (transactionDayFilter.value) {
        return items.filter(transaction => getTransactionDateKey(transaction.transaction_date) === transactionDayFilter.value)
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
        items.map(transaction => getTransactionDateKey(transaction.transaction_date))
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
const bulkMaxDate = computed(() => formatDateForInput(new Date(selectedYear.value, selectedMonth.value, 0)))
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
const selectableAccountRows = computed(() =>
    (sortedAccountStatementRows.value || []).filter(row => row.type === 'individual_transaction' && row.transaction_id)
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

const selectAccountRow = (row) => {
    selectedAccountRow.value = row
    selectedTransactionIds.value = []
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

const getSelectionIdsForContext = (context) => context === 'account'
    ? selectedAccountTransactionIds.value
    : selectedTransactionIds.value

const hasSelectionForContext = (context) => context === 'account'
    ? hasAccountSelection.value
    : hasTransactionSelection.value

const clearSelectionForContext = (context) => {
    if (context === 'account') {
        selectedAccountTransactionIds.value = []
    } else {
        selectedTransactionIds.value = []
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

watch(selectedAccountRow, (row) => {
    if (!row) {
        transactionSort.value = { key: null, direction: null }
        selectedTransactionIds.value = []
        transactionDayFilter.value = null
        isDayFilterOpen.value = false
    } else {
        selectedTransactionIds.value = []
        transactionDayFilter.value = null
        isDayFilterOpen.value = false
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

watch([bulkMinDate, bulkMaxDate], ([min, max]) => {
    if (!bulkDuplicateDate.value) {
        return
    }

    if (bulkDuplicateDate.value < min) {
        bulkDuplicateDate.value = min
    } else if (bulkDuplicateDate.value > max) {
        bulkDuplicateDate.value = max
    }
})
</script>

<template>
    <Head title="ניהול תזרים" />

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
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 text-right">שורות עו"ש</h3>
                            <p class="text-sm text-gray-500">לחץ על שורה כדי לראות את פירוט העסקאות שלה.</p>
                        </div>
                        <div class="flex flex-col items-stretch gap-2 sm:flex-row-reverse sm:items-center">
                            <button 
                                @click="openCreateModal"
                                class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                הוסף תזרים
                            </button>
                            <Link
                                :href="route('cashflow.import.index')"
                                class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-indigo-600 uppercase tracking-widest bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                <svg class="w-4 h-4 ml-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 12v9m0-9l-3 3m3-3l3 3M12 4H8m4-2H8a2 2 0 00-2 2v8m10-8h-4" />
                                </svg>
                                ייבוא מקובץ
                            </Link>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-gray-500">
                                    ניתן לבחור שורות עו"ש מסוג תזרים בודד ולבצע שכפול או מחיקה מרובה.
                                </p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:bg-green-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!hasAccountSelection || isBulkSubmitting"
                                        @click="openBulkDuplicateModal('account')"
                                    >
                                        📄 שכפל נבחרים
                                        <span v-if="selectedAccountCount" class="ml-1">({{ selectedAccountCount }})</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!hasAccountSelection || isBulkDeleting"
                                        @click="confirmBulkDelete('account')"
                                    >
                                        🗑️ מחק נבחרים
                                        <span v-if="selectedAccountCount" class="ml-1">({{ selectedAccountCount }})</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:text-gray-700 hover:bg-gray-100"
                                        :disabled="!hasAccountSelection"
                                        @click="clearAccountRowSelection"
                                    >
                                        נקה בחירה
                                    </button>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <input
                                                    ref="selectAllAccountRowsCheckbox"
                                                    type="checkbox"
                                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                    :checked="areAllAccountRowsSelected"
                                                    @change="toggleSelectAllAccountRows($event.target.checked)"
                                                    :disabled="!selectableAccountRows.length"
                                                />
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <button type="button"
                                                        class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500"
                                                        :class="isAccountColumnSorted('description') ? 'text-indigo-600' : 'text-gray-600'"
                                                        @click="toggleAccountSort('description')">
                                                    <span>תיאור</span>
                                                    <span v-if="accountSortIcon('description')" class="text-xs">{{ accountSortIcon('description') }}</span>
                                                </button>
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <button type="button"
                                                        class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500"
                                                        :class="isAccountColumnSorted('date') ? 'text-indigo-600' : 'text-gray-600'"
                                                        @click="toggleAccountSort('date')">
                                                    <span>תאריך</span>
                                                    <span v-if="accountSortIcon('date')" class="text-xs">{{ accountSortIcon('date') }}</span>
                                                </button>
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <button type="button"
                                                        class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500"
                                                        :class="isAccountColumnSorted('type') ? 'text-indigo-600' : 'text-gray-600'"
                                                        @click="toggleAccountSort('type')">
                                                    <span>סוג</span>
                                                    <span v-if="accountSortIcon('type')" class="text-xs">{{ accountSortIcon('type') }}</span>
                                                </button>
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <button type="button"
                                                        class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500"
                                                        :class="isAccountColumnSorted('amount') ? 'text-indigo-600' : 'text-gray-600'"
                                                        @click="toggleAccountSort('amount')">
                                                    <span>סכום</span>
                                                    <span v-if="accountSortIcon('amount')" class="text-xs">{{ accountSortIcon('amount') }}</span>
                                                </button>
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="row in sortedAccountStatementRows" :key="row.id" 
                                            class="hover:bg-gray-50 cursor-pointer"
                                            :class="{ 
                                                'bg-blue-50': selectedAccountRow && selectedAccountRow.id === row.id,
                                                'bg-green-50': row.type === 'cash_flow_source',
                                                'border-l-4 border-green-500': row.type === 'cash_flow_source'
                                            }"
                                            @click="selectAccountRow(row)">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                    :disabled="!isAccountRowSelectable(row)"
                                                    :checked="isAccountRowSelected(row)"
                                                    @click.stop
                                                    @change="toggleAccountRowSelection(row, $event.target.checked)"
                                                />
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                <div class="flex items-center">
                                                    <span class="mr-2 text-lg">{{ row.source_icon || '📊' }}</span>
                                                    <div>
                                                        <div class="font-medium">{{ row.source_name }}</div>
                                                        <div v-if="row.type === 'individual_transaction' && row.category_name" 
                                                             class="text-xs text-gray-500">
                                                            קטגוריה: {{ row.category_name }}
                                                        </div>
                                                        <div v-if="row.type === 'cash_flow_source'" 
                                                             class="text-xs text-green-600 font-medium">
                                                            מקור תזרים
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                <span v-if="row.type === 'individual_transaction'">
                                                    {{ row.transaction_date }}
                                                </span>
                                                <span v-else class="text-gray-400">
                                                    סיכום
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                      :class="getTransactionTypeColor(row.transaction_type)">
                                                    {{ getTransactionTypeIcon(row.transaction_type) }}
                                                    {{ getTransactionTypeName(row.transaction_type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right"
                                                :class="row.amount_color">
                                                {{ row.formatted_amount }}
                                                <span v-if="row.type === 'cash_flow_source'" class="text-xs text-gray-500 block">
                                                    ({{ row.transaction_count }} עסקאות)
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <template v-if="row.can_add_transactions">
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-green-200 text-green-600 hover:bg-green-50 rounded-md transition-colors duration-200 disabled:opacity-60"
                                                            :disabled="isDuplicatingSource(row.cash_flow_source_id)"
                                                            @click.stop="duplicateCashFlowSource(row)"
                                                        >
                                                            <svg
                                                                v-if="isDuplicatingSource(row.cash_flow_source_id)"
                                                                class="-ml-1 mr-1 h-3 w-3 animate-spin text-green-600"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                                                            </svg>
                                                            <span class="text-xs font-medium">שכפל מקור</span>
                                                        </button>
                                                        <button
                                                            @click.stop="openCreateModal"
                                                            class="inline-flex items-center px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md transition-colors duration-200"
                                                        >
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                            </svg>
                                                            <span class="text-xs font-medium">הוסף</span>
                                                        </button>
                                                    </template>
                                                    <template v-else>
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-green-200 text-green-600 hover:bg-green-50 rounded-md transition-colors duration-200 disabled:opacity-60"
                                                            :disabled="isDuplicatingTransaction(row.transaction_data?.id)"
                                                            @click.stop="duplicateTransaction(row.transaction_data)"
                                                        >
                                                            <svg
                                                                v-if="isDuplicatingTransaction(row.transaction_data?.id)"
                                                                class="-ml-1 mr-1 h-3 w-3 animate-spin text-green-600"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                                                            </svg>
                                                            📄<span class="sr-only">שכפל</span>
                                                        </button>
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors duration-200"
                                                            @click.stop="openEditModal(row.transaction_data)"
                                                        >
                                                            ✏️<span class="sr-only">ערוך</span>
                                                        </button>
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-red-200 text-red-600 hover:bg-red-50 rounded-md transition-colors duration-200"
                                                            @click.stop="confirmDelete(row.transaction_data)"
                                                        >
                                                            🗑️<span class="sr-only">מחק</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!sortedAccountStatementRows || sortedAccountStatementRows.length === 0">
                                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                                אין תזרימים לתקופה זו
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-lg font-medium text-gray-900 text-right">
                                    פירוט עסקאות
                                    <span v-if="selectedAccountRow" class="text-sm text-gray-500 mr-2">
                                        - {{ selectedAccountRow.source_name }}
                                        <span v-if="selectedAccountRow.type === 'cash_flow_source'">(מקור תזרים)</span>
                                        <span v-else>(תזרים בודד)</span>
                                    </span>
                                </h3>
                                <div v-if="selectedAccountRow" class="flex flex-wrap items-center gap-2">
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
                                                        'flex w-full items-center justify-between rounded px-2 py-1 text-sm hover:bg-gray-100',
                                                        day.hasTransactions ? 'text-gray-700' : 'text-gray-400'
                                                    ]"
                                                    @click.stop="selectTransactionDay(day.key)"
                                                >
                                                    {{ day.label }}
                                                    <span v-if="transactionDayFilter === day.key" class="text-xs text-indigo-600">נבחר</span>
                                                </button>
                                                <p v-if="!availableTransactionDays.length" class="px-2 py-1 text-xs text-gray-500">אין תזרימים להצגה</p>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:bg-green-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!hasTransactionSelection"
                                        @click="openBulkDuplicateModal('transactions')"
                                    >
                                        📄 שכפל נבחרים
                                        <span v-if="selectedTransactionsCount" class="ml-1">({{ selectedTransactionsCount }})</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!hasTransactionSelection || isBulkDeleting"
                                        @click="confirmBulkDelete('transactions')"
                                    >
                                        <svg
                                            v-if="isBulkDeleting"
                                            class="-ml-1 mr-1 h-4 w-4 animate-spin text-red-700"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                                        </svg>
                                        🗑️ מחק נבחרים
                                        <span v-if="selectedTransactionsCount" class="ml-1">({{ selectedTransactionsCount }})</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearSelection"
                                        class="inline-flex items-center rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:text-gray-700 hover:bg-gray-100"
                                    >
                                        נקה בחירה
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <div v-if="!selectedAccountRow" class="p-6 text-center text-gray-500">
                                    <p>בחר שורה מעו"ש כדי לראות את פירוט העסקאות.</p>
                                </div>

                                <div v-else>
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <input
                                                        ref="selectAllTransactionsCheckbox"
                                                        type="checkbox"
                                                        class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                        :checked="areAllTransactionsSelected"
                                                        @change="toggleSelectAllTransactions($event.target.checked)"
                                                        :disabled="!selectedAccountRow || !filteredTransactions.length"
                                                    />
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('date') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('date')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>תאריך</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('date')" class="text-xs">{{ transactionSortIcon('date') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('posting_date') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('posting_date')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>תאריך חיוב</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('posting_date')" class="text-xs">{{ transactionSortIcon('posting_date') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('description') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('description')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>תיאור</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('description')" class="text-xs">{{ transactionSortIcon('description') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right.text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse.items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('category') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('category')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>קטגוריה</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('category')" class="text-xs">{{ transactionSortIcon('category') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3.text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('amount') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('amount')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>סכום</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('amount')" class="text-xs">{{ transactionSortIcon('amount') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right.text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse.items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('status') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('status')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>סטטוס</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('status')" class="text-xs">{{ transactionSortIcon('status') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="transaction in filteredTransactions" :key="transaction.id" class="hover:bg-gray-50">
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                                    <input
                                                        type="checkbox"
                                                        class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                        :checked="isTransactionSelected(transaction.id)"
                                                        @change="toggleTransactionSelection(transaction.id, $event.target.checked)"
                                                    />
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {{ new Date(transaction.transaction_date).toLocaleDateString('he-IL') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {{ transaction.posting_date ? new Date(transaction.posting_date).toLocaleDateString('he-IL') : '-' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {{ transaction.description }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    <div class="flex items-center">
                                                        <span class="mr-2 text-lg">{{ transaction.category?.icon || '📊' }}</span>
                                                        <span class="font-medium">{{ transaction.category?.name || 'לא מוגדר' }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right"
                                                    :class="getTransactionTypeColor(transaction.type)">
                                                    {{ formatCurrency(transaction.amount) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        {{ transaction.status === 'completed' ? 'הושלם' : transaction.status === 'pending' ? 'ממתין' : 'בוטל' }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    <div class="flex items-center justify-end space-x-2">
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-green-200 text-green-600 hover:bg-green-50 rounded-md transition-colors duration-200 disabled:opacity-60"
                                                            :disabled="isDuplicatingTransaction(transaction.id)"
                                                            @click.stop="duplicateTransaction(transaction)"
                                                        >
                                                            <svg
                                                                v-if="isDuplicatingTransaction(transaction.id)"
                                                                class="-ml-1 mr-1 h-3 w-3 animate-spin text-green-600"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                                                            </svg>
                                                            📄<span class="sr-only">שכפל</span>
                                                        </button>
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors duration-200"
                                                            @click="openEditModal(transaction)"
                                                        >
                                                            ✏️<span class="sr-only">ערוך</span>
                                                        </button>
                                                        <button
                                                            class="inline-flex items-center px-2 py-1 bg-white border border-red-200 text-red-600 hover:bg-red-50 rounded-md transition-colors duration-200"
                                                            @click="confirmDelete(transaction)"
                                                        >
                                                            🗑️<span class="sr-only">מחק</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="filteredTransactions.length === 0">
                                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                                    אין עסקאות למקור התזרים הזה
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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

        <Modal :show="isBulkDuplicateModalOpen" @close="closeBulkDuplicateModal">
            <div class="space-y-4 p-6">
                <h2 class="text-lg font-semibold text-gray-900">שכפול פריטים נבחרים</h2>
                <p class="text-sm text-gray-600">
                    בחר תאריך יעד לשכפול {{ bulkSelectionCount }} {{ bulkSelectionLabel }} שנבחרו. התזרימים החדשים ישמרו על פרטי המקור, כולל קטגוריות ומקורות תזרים.
                </p>
                <div class="space-y-2">
                    <label for="bulk-duplicate-date" class="block text-sm font-medium text-gray-700">תאריך חדש</label>
                    <input
                        id="bulk-duplicate-date"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        v-model="bulkDuplicateDate"
                        :min="bulkMinDate"
                        :max="bulkMaxDate"
                    />
                    <p v-if="bulkDuplicateError" class="text-sm text-red-600">{{ bulkDuplicateError }}</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
                        @click="closeBulkDuplicateModal"
                    >
                        ביטול
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
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
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938ל3-2.647z"></path>
                        </svg>
                        שכפל
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
