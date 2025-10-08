<template>
    <Modal :show="show" @close="handleClose">
        <div class="p-6">
            <div class="flex flex-col gap-2 mb-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    עסקאות עבור {{ category?.category_name || category?.name }}
                </h2>
                <p class="text-sm text-gray-500">
                    חודש {{ monthLabel }} {{ year }}
                </p>
            </div>

            <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    סך הכול עסקאות: <span class="font-semibold text-gray-900">{{ transactions.length }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="!selectionMode"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        @click="enterSelectionMode"
                    >
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        הוסף עסקאות קיימות
                    </button>
                    <template v-else>
                        <button
                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-100"
                            @click="exitSelectionMode"
                        >
                            ביטול
                        </button>
                        <button
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            :disabled="selectedAvailableIds.length === 0 || isAssigning"
                            @click="assignSelected"
                        >
                            <svg v-if="isAssigning" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            הוסף {{ selectedAvailableIds.length }}
                        </button>
                    </template>
                </div>
            </div>

            <div v-if="selectionMode">
                <div v-if="isLoadingAvailable" class="py-8 text-center text-gray-500">טוען עסקאות זמינות...</div>
                <div v-else>
                    <div v-if="availableTransactions.length === 0" class="py-8 text-center text-gray-500">
                        אין עסקאות פנויות המתאימות לקטגוריה זו בחודש {{ monthLabel }}.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" @change="toggleSelectAll($event.target.checked)" :checked="allSelected" />
                                    </th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תאריך</th>
                                    <th class="px-4 py-2 text-right.text-xs.font-medium text-gray-500 uppercase tracking-wider">תיאור</th>
                                    <th class="px-4.py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סכום</th>
                                    <th class="px-4.py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">מקור</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="transaction in availableTransactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-right">
                                        <input type="checkbox" :value="transaction.id" v-model="selectedAvailableIds" />
                                    </td>
                                    <td class="px-4 py-3 text-sm.text-gray-900 text-right">
                                        {{ new Date(transaction.transaction_date).toLocaleDateString('he-IL') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                        {{ transaction.description }}
                                    </td>
                                    <td class="px-4.py-3 text-sm font-medium text-right" :class="transaction.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(transaction.amount) }}
                                    </td>
                                    <td class="px-4.py-3 text-sm text-gray-900 text-right">
                                        <span v-if="transaction.cash_flow_source">
                                            {{ transaction.cash_flow_source.icon || '💳' }} {{ transaction.cash_flow_source.name }}
                                        </span>
                                        <span v-else class="text-gray-400">ללא מקור</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-else>
                <div v-if="isLoading" class="py-8 text-center text-gray-500">טוען עסקאות...</div>
                <div v-else>
                    <div v-if="transactions.length === 0" class="py-8 text-center.text-gray-500">
                        אין עסקאות לקטגוריה זו בחודש הנבחר.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תאריך</th>
                                    <th class="px-4.py-2 text-right.text-xs.font-medium text-gray-500 uppercase tracking-wider">תיאור</th>
                                    <th class="px-4.py-2 text-right text-xs.font-medium text-gray-500 uppercase tracking-wider">סכום</th>
                                    <th class="px-4.py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">מקור</th>
                                    <th class="px-4.py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סטטוס</th>
                                    <th class="px-4.py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                        {{ new Date(transaction.transaction_date).toLocaleDateString('he-IL') }}
                                    </td>
                                    <td class="px-4.py-3 text-sm text-gray-900 text-right">
                                        {{ transaction.description }}
                                    </td>
                                    <td class="px-4.py-3 text-sm font-medium text-right" :class="transaction.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(transaction.amount) }}
                                    </td>
                                    <td class="px-4.py-3 text-sm text-gray-900 text-right">
                                        <span v-if="transaction.cash_flow_source">
                                            {{ transaction.cash_flow_source.icon || '💳' }} {{ transaction.cash_flow_source.name }}
                                        </span>
                                        <span v-else class="text-gray-400">ללא מקור</span>
                                    </td>
                                    <td class="px-4.py-3 text-sm text-gray-900.text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ translateStatus(transaction.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4.py-3 text-sm text-gray-900 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                class="inline-flex items-center px-2 py-1 bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors duration-200"
                                                @click="openEditModal(transaction)"
                                            >
                                                ✏️
                                            </button>
                                            <button
                                                class="inline-flex items-center px-2 py-1 bg-white border border-red-200 text-red-600 hover:bg-red-50 rounded-md transition-colors duration-200"
                                                @click="confirmUnassign(transaction)"
                                            >
                                                🔄
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <TransactionAddModal
            :show="showTransactionModal"
            :mode="transactionModalMode"
            :transaction="editingTransaction"
            :categories="categories"
            :cash-flow-sources="cashFlowSources"
            :budgets="budgets"
            @close="closeTransactionModal"
            @transaction-added="reloadTransactions"
            @transaction-updated="reloadTransactions"
            @transaction-deleted="reloadTransactions"
        />
    </Modal>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import Modal from './Modal.vue'
import TransactionAddModal from './TransactionAddModal.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    category: { type: Object, default: null },
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    categories: { type: Array, default: () => [] },
    cashFlowSources: { type: Array, default: () => [] },
    budgets: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const transactions = ref([])
const isLoading = ref(false)
const showTransactionModal = ref(false)
const transactionModalMode = ref('create')
const editingTransaction = ref(null)

const selectionMode = ref(false)
const isLoadingAvailable = ref(false)
const isAssigning = ref(false)
const availableTransactions = ref([])
const selectedAvailableIds = ref([])

const monthLabel = computed(() => {
    const months = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר']
    return months[props.month - 1] || props.month
})

const allSelected = computed(() => {
    if (!availableTransactions.value.length) return false
    return selectedAvailableIds.value.length === availableTransactions.value.length
})

watch(
    () => props.show,
    (value) => {
        if (value && props.category) {
            fetchTransactions()
        } else {
            exitSelectionMode()
        }
    }
)

watch(
    () => props.category,
    (category) => {
        if (props.show && category) {
            fetchTransactions()
            exitSelectionMode()
        } else {
            transactions.value = []
        }
    }
)

watch(
    () => [props.year, props.month],
    () => {
        if (props.show && props.category) {
            fetchTransactions()
            if (selectionMode.value) {
                fetchAvailableTransactions()
            }
        }
    }
)

const fetchTransactions = async () => {
    if (!props.category) {
        transactions.value = []
        return
    }

    isLoading.value = true

    try {
        const response = await fetch(route('budgets.manage.transactions', props.category.category_id || props.category.id) + `?year=${props.year}&month=${props.month}`)
        const data = await response.json()
        transactions.value = data.transactions || []
    } catch (error) {
        console.error('Failed to fetch transactions', error)
        transactions.value = []
    } finally {
        isLoading.value = false
    }
}

const fetchAvailableTransactions = async () => {
    if (!props.category) {
        availableTransactions.value = []
        return
    }

    isLoadingAvailable.value = true
    selectedAvailableIds.value = []

    try {
        const response = await fetch(route('budgets.manage.transactions.available', props.category.category_id || props.category.id) + `?year=${props.year}&month=${props.month}`)
        const data = await response.json()
        availableTransactions.value = data.transactions || []
    } catch (error) {
        console.error('Failed to load available transactions', error)
        availableTransactions.value = []
    } finally {
        isLoadingAvailable.value = false
    }
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}

const translateStatus = (status) => {
    switch (status) {
        case 'completed':
            return 'הושלם'
        case 'pending':
            return 'ממתין'
        case 'cancelled':
            return 'בוטל'
        default:
            return status
    }
}

const enterSelectionMode = () => {
    selectionMode.value = true
    fetchAvailableTransactions()
}

const exitSelectionMode = () => {
    selectionMode.value = false
    isLoadingAvailable.value = false
    availableTransactions.value = []
    selectedAvailableIds.value = []
}

const toggleSelectAll = (checked) => {
    if (checked) {
        selectedAvailableIds.value = availableTransactions.value.map((transaction) => transaction.id)
    } else {
        selectedAvailableIds.value = []
    }
}

const assignSelected = async () => {
    if (!selectedAvailableIds.value.length || !props.category) {
        return
    }

    isAssigning.value = true

    try {
        const response = await fetch(route('budgets.manage.transactions.assign', props.category.category_id || props.category.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ transaction_ids: selectedAvailableIds.value }),
        })

        if (!response.ok) {
            throw new Error('Failed to assign transactions')
        }

        exitSelectionMode()
        fetchTransactions()
    } catch (error) {
        console.error(error)
    } finally {
        isAssigning.value = false
    }
}

const openEditModal = (transaction) => {
    transactionModalMode.value = 'edit'
    editingTransaction.value = {
        ...transaction,
    }
    showTransactionModal.value = true
}

const closeTransactionModal = () => {
    showTransactionModal.value = false
    editingTransaction.value = null
}

const reloadTransactions = () => {
    closeTransactionModal()
    fetchTransactions()
    if (selectionMode.value) {
        fetchAvailableTransactions()
    }
}

const confirmUnassign = async (transaction) => {
    if (!transaction || !props.category) {
        return
    }

    if (!confirm('להסיר את העסקה מהקטגוריה?')) {
        return
    }

    try {
        const response = await fetch(route('budgets.manage.transactions.unassign', [props.category.category_id || props.category.id, transaction.id]), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        })

        if (!response.ok) {
            throw new Error('Failed to unassign transaction')
        }

        fetchTransactions()
    } catch (error) {
        console.error(error)
    }
}

const handleClose = () => {
    closeTransactionModal()
    exitSelectionMode()
    emit('close')
}
</script>
