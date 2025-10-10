<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TransactionAddModal from '@/Components/TransactionAddModal.vue'
import PeriodSelector from '@/Components/PeriodSelector.vue'

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

const localAccountStatementRows = ref(props.accountStatementRows?.map(row => ({ ...row })) || [])

const defaultAccountSort = () => ({ key: 'date', direction: 'desc' })
const defaultTransactionSort = () => ({ key: 'date', direction: 'desc' })

const accountSort = ref(defaultAccountSort())
const transactionSort = ref({ key: null, direction: null })

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
    description: (transaction) => (transaction.description || '').toString().toLowerCase(),
    category: (transaction) => (transaction.category?.name || '').toString().toLowerCase(),
    amount: (transaction) => Number(transaction.amount || 0),
    status: (transaction) => (transaction.status || '').toString().toLowerCase(),
}

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

const filteredTransactions = computed(() => {
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
    if (!transactionSort.value.key || !transactionSort.value.direction) {
        transactionSort.value = defaultTransactionSort()
    }
}

const clearSelection = () => {
    selectedAccountRow.value = null
    transactionSort.value = { key: null, direction: null }
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

watch(() => props.accountStatementRows, (newRows) => {
    if (newRows) {
        const previousSelectionId = selectedAccountRow.value?.id || null
        localAccountStatementRows.value = newRows.map(row => ({ ...row }))
        if (previousSelectionId) {
            const matchedRow = localAccountStatementRows.value.find(row => row.id === previousSelectionId)
            selectedAccountRow.value = matchedRow || null
        }
    }
}, { immediate: true, deep: true })

watch(selectedAccountRow, (row) => {
    if (!row) {
        transactionSort.value = { key: null, direction: null }
    }
})
</script>

<template>
    <Head title="ניהול תזרים" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 text-right">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="bg-white border border-gray-200 rounded-md px-4 py-3 text-right">
                        <p class="text-xs text-gray-500">יתרה</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(props.balance) }} ₪</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-md px-4 py-3 text-right">
                        <p class="text-xs text-gray-500">סה"כ הכנסות</p>
                        <p class="text-lg font-semibold text-green-600">{{ formatCurrency(props.totalIncome) }} ₪</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-md px-4 py-3 text-right">
                        <p class="text-xs text-gray-500">סה"כ הוצאות</p>
                        <p class="text-lg font-semibold text-red-600">{{ formatCurrency(props.totalExpenses) }} ₪</p>
                    </div>
                </div>
            </div>
        </template>
        <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <!-- <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        ניהול תזרים
                    </h2> -->
                    <div class="flex flex-col items-end gap-1 text-sm text-gray-500">
                        <span>
                            בחירת תקופה:
                            <span class="font-semibold text-gray-900">
                                {{ selectedYear }} - {{ monthOptions.find(m => m.value === selectedMonth)?.label || selectedMonth }}
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
        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 text-right">שורות עו"ש</h3>
                            <p class="text-sm text-gray-500">לחץ על שורה כדי לראות את פירוט העסקאות שלה.</p>
                        </div>
                        <button 
                            @click="openCreateModal"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            הוסף תזרים
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
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
                                                    <button v-if="row.can_add_transactions" 
                                                            @click.stop="openCreateModal"
                                                            class="inline-flex items-center px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md transition-colors duration-200">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                        </svg>
                                                        <span class="text-xs font-medium">הוסף</span>
                                                    </button>
                                                    <template v-else>
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
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                                אין תזרימים לתקופה זו
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900 text-right">
                                    פירוט עסקאות
                                    <span v-if="selectedAccountRow" class="text-sm text-gray-500 mr-2">
                                        - {{ selectedAccountRow.source_name }}
                                        <span v-if="selectedAccountRow.type === 'cash_flow_source'">(מקור תזרים)</span>
                                        <span v-else>(תזרים בודד)</span>
                                    </span>
                                </h3>
                                <button 
                                    v-if="selectedAccountRow"
                                    @click="clearSelection"
                                    class="text-sm text-gray-500 hover:text-gray-700 underline"
                                >
                                    נקה בחירה
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <div v-if="!selectedAccountRow" class="p-6 text-center text-gray-500">
                                    <p>בחר שורה מעו"ש כדי לראות את פירוט העסקאות.</p>
                                </div>

                                <div v-else>
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
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
                                                            :class="isTransactionColumnSorted('description') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('description')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>תיאור</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('description')" class="text-xs">{{ transactionSortIcon('description') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('category') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('category')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>קטגוריה</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('category')" class="text-xs">{{ transactionSortIcon('category') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
                                                            :class="isTransactionColumnSorted('amount') ? 'text-indigo-600' : 'text-gray-600'"
                                                            @click="toggleTransactionSort('amount')"
                                                            :disabled="!selectedAccountRow">
                                                        <span>סכום</span>
                                                        <span v-if="selectedAccountRow && transactionSortIcon('amount')" class="text-xs">{{ transactionSortIcon('amount') }}</span>
                                                    </button>
                                                </th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <button type="button"
                                                            class="w-full flex flex-row-reverse items-center justify-between gap-1 text-right hover:text-indigo-500 disabled:cursor-not-allowed"
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
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {{ new Date(transaction.transaction_date).toLocaleDateString('he-IL') }}
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
                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
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
            @close="closeTransactionModal"
            @transaction-added="handleTransactionSaved"
            @transaction-updated="handleTransactionSaved"
            @transaction-deleted="handleTransactionDeleted"
        />
    </AuthenticatedLayout>
</template>
