<template>
    <Modal :show="show" @close="handleClose">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-1">
                {{ modalTitle }}
            </h2>
            <p class="text-sm text-gray-500 mb-4" v-if="isEditMode">
                ניתן לערוך את כל שדות התזרים ולשנות האם הוא משויך למקור תזרים (כרטיס אשראי) או כתזרים בודד.
            </p>

            <form @submit.prevent="submitForm" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="type" value="סוג התזרים" />
                        <select
                            id="type"
                            v-model="form.type"
                            @change="onTypeChange"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="">בחר סוג</option>
                            <option value="income">הכנסה</option>
                            <option value="expense">הוצאה</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="category" value="קטגוריה" />
                        <select
                            id="category"
                            v-model="form.category_id"
                            @change="onCategoryChange"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">בחר קטגוריה</option>
                            <option
                                v-for="category in filteredCategories"
                                :key="category.category_id || category.id"
                                :value="String(category.category_id || category.id)"
                            >
                                {{ category.category_icon || category.icon }} {{ category.category_name || category.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category_id" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel for="cash_flow_source" value="מקור תזרים (אופציונלי)" />
                    <select
                        id="cash_flow_source"
                        v-model="form.cash_flow_source_id"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :disabled="!form.type"
                    >
                        <option value="">ללא מקור תזרים (תזרים בודד)</option>
                        <option
                            v-for="source in filteredCashFlowSources"
                            :key="source.id"
                            :value="String(source.id)"
                        >
                            {{ source.icon }} {{ source.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.cash_flow_source_id" class="mt-2" />
                    <p class="mt-1 text-sm text-gray-500">
                        בחירה במקור תזרים (למשל כרטיס אשראי) תאחד את התזרימים תחת אותו מקור בעו"ש. השאר ריק כדי להשאיר כתזרים בודד.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="amount" value="סכום" />
                        <TextInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                            placeholder="0.00"
                            required
                        />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="date" value="תאריך" />
                        <TextInput
                            id="date"
                            v-model="form.transaction_date"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="form.errors.transaction_date" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel for="posting_date" value="תאריך חיוב (אופציונלי)" />
                    <TextInput
                        id="posting_date"
                        v-model="form.posting_date"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        ישמש להצגה בלבד (למשל תאריך חיוב בכרטיס אשראי). אם השדה ריק, יוצג '-' בפירוט העסקאות.
                    </p>
                    <InputError :message="form.errors.posting_date" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="description" value="תיאור" />
                    <TextInput
                        id="description"
                        v-model="form.description"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="תיאור העסקה"
                        required
                    />
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="notes" value="הערות (אופציונלי)" />
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="הערות נוספות..."
                    ></textarea>
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="reference" value="מספר הפניה (אופציונלי)" />
                    <TextInput
                        id="reference"
                        v-model="form.reference_number"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="מספר צ'ק, חשבונית וכו'"
                    />
                    <InputError :message="form.errors.reference_number" class="mt-2" />
                </div>

                <div v-if="budgetWarning" class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">
                                אזהרת תקציב
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>{{ budgetWarning }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div v-if="isEditMode" class="flex items-center gap-2 text-sm text-gray-500">
                        <span>נוצר:</span>
                        <span>{{ formattedCreatedAt }}</span>
                        <span class="mx-2">|</span>
                        <span>עודכן לאחרונה:</span>
                        <span>{{ formattedUpdatedAt }}</span>
                    </div>
                    <div class="flex justify-end space-x-3 rtl:space-x-reverse">
                        <SecondaryButton @click="handleClose" type="button">
                            ביטול
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="isEditMode"
                            type="button"
                            class="bg-red-50 text-red-600 hover:bg-red-100 border border-red-200"
                            :disabled="isDeleting"
                            @click="deleteTransaction"
                        >
                            <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            מחק תזרים
                        </SecondaryButton>
                        <PrimaryButton :disabled="isLoading" type="submit">
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isEditMode ? 'עדכן תזרים' : 'הוסף תזרים' }}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from './Modal.vue'
import InputLabel from './InputLabel.vue'
import TextInput from './TextInput.vue'
import InputError from './InputError.vue'
import PrimaryButton from './PrimaryButton.vue'
import SecondaryButton from './SecondaryButton.vue'

const formatDateForInput = (value) => {
    if (!value) return new Date().toISOString().split('T')[0]
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) {
        const parts = String(value).split('-')
        if (parts.length === 3) {
            return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`
        }
        return new Date().toISOString().split('T')[0]
    }
    return date.toISOString().split('T')[0]
}

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'create',
    },
    transaction: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    cashFlowSources: {
        type: Array,
        default: () => [],
    },
    budgets: {
        type: Array,
        default: () => [],
    },
    currentYear: {
        type: [Number, String],
        default: null,
    },
    currentMonth: {
        type: [Number, String],
        default: null,
    },
})

const emit = defineEmits(['close', 'transaction-added', 'transaction-updated', 'transaction-deleted'])

const isLoading = ref(false)
const isDeleting = ref(false)
const budgetWarning = ref('')

const getContextYear = () => {
    const value = Number(props.currentYear)
    return Number.isFinite(value) && value > 0 ? value : new Date().getFullYear()
}

const getContextMonth = () => {
    const value = Number(props.currentMonth)
    return Number.isFinite(value) && value >= 1 && value <= 12 ? value : (new Date().getMonth() + 1)
}

const getDefaultContextDate = () => {
    const year = getContextYear()
    const month = getContextMonth()
    const today = new Date()
    const lastDay = new Date(year, month, 0).getDate()
    const day = Math.min(today.getDate(), lastDay)
    return formatDateForInput(new Date(year, month - 1, day))
}

const isEditMode = computed(() => props.mode === 'edit' && props.transaction)

const form = useForm({
    type: '',
    category_id: '',
    cash_flow_source_id: '',
    amount: '',
    transaction_date: getDefaultContextDate(),
    posting_date: getDefaultContextDate(),
    description: '',
    notes: '',
    reference_number: '',
})

const filteredCategories = computed(() => {
    if (!form.type) return []
    return props.categories.filter((category) => {
        const categoryType = (category.type || category.category_type || '').toString()
        return categoryType === form.type || categoryType === 'both'
    })
})

const filteredCashFlowSources = computed(() => {
    if (!form.type) return []
    return props.cashFlowSources.filter((source) => source.type === form.type)
})

const currentBudget = computed(() => {
    if (!form.category_id || !form.amount) return null

    const year = getContextYear()
    const month = getContextMonth()

    return props.budgets.find(
        (budget) =>
            String(budget.category_id) === String(form.category_id) &&
            budget.year === year &&
            budget.month === month
    )
})

const formattedCreatedAt = computed(() => {
    if (!props.transaction?.created_at) return '-'
    return new Date(props.transaction.created_at).toLocaleString('he-IL')
})

const formattedUpdatedAt = computed(() => {
    if (!props.transaction?.updated_at) return '-'
    return new Date(props.transaction.updated_at).toLocaleString('he-IL')
})

const modalTitle = computed(() => (isEditMode.value ? 'עריכת תזרים' : 'הוספת תזרים חדש'))

const initializeForm = () => {
    if (props.transaction) {
        const transaction = props.transaction
        form.defaults({
            type: transaction.type || '',
            category_id: transaction.category_id ? String(transaction.category_id) : '',
            cash_flow_source_id: transaction.cash_flow_source_id ? String(transaction.cash_flow_source_id) : '',
            amount: transaction.amount ? Number(transaction.amount).toFixed(2) : '',
            transaction_date: formatDateForInput(transaction.transaction_date),
            posting_date: formatDateForInput(transaction.posting_date || transaction.transaction_date),
            description: transaction.description || '',
            notes: transaction.notes || '',
            reference_number: transaction.reference_number || '',
        })
    } else {
        form.defaults({
            type: '',
            category_id: '',
            cash_flow_source_id: '',
            amount: '',
            transaction_date: getDefaultContextDate(),
            posting_date: getDefaultContextDate(),
            description: '',
            notes: '',
            reference_number: '',
        })
    }

    form.reset()
    budgetWarning.value = ''
    checkBudgetWarning()
}

const checkBudgetWarning = () => {
    if (!currentBudget.value || form.type !== 'expense') {
        budgetWarning.value = ''
        return
    }

    const amount = parseFloat(form.amount) || 0
    const remaining = parseFloat(currentBudget.value.remaining_amount)

    if (amount > remaining) {
        budgetWarning.value = `סכום זה יעלה על התקציב הנותר (${remaining.toFixed(2)} ₪)`
    } else if (amount > remaining * 0.8) {
        budgetWarning.value = `סכום זה קרוב לתקציב הנותר (${remaining.toFixed(2)} ₪)`
    } else {
        budgetWarning.value = ''
    }
}

const onTypeChange = () => {
    form.category_id = ''
    form.cash_flow_source_id = ''
    budgetWarning.value = ''
}

const onCategoryChange = () => {
    checkBudgetWarning()
}

watch(
    () => form.amount,
    () => {
        checkBudgetWarning()
    }
)

watch(
    () => props.show,
    (value) => {
        if (value) {
            initializeForm()
        }
    }
)

watch(
    () => props.transaction,
    () => {
        if (props.show && isEditMode.value) {
            initializeForm()
        }
    }
)

const submitForm = async () => {
    isLoading.value = true

    form.transform((data) => ({
        ...data,
        amount: data.amount ? parseFloat(data.amount) : data.amount,
        category_id: data.category_id || null,
        cash_flow_source_id: data.cash_flow_source_id || null,
        posting_date: data.posting_date || null,
    }))

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isLoading.value = false
            budgetWarning.value = ''
            emit(isEditMode.value ? 'transaction-updated' : 'transaction-added')
            form.reset()
            emit('close')
        },
        onError: () => {
            isLoading.value = false
        },
    }

    if (isEditMode.value) {
        await form.put(route('transactions.update', props.transaction.id), options)
    } else {
        await form.post(route('transactions.store'), options)
    }
}

const deleteTransaction = async () => {
    if (!isEditMode.value || !props.transaction) return

    if (!confirm('האם למחוק את התזרים הזה? הפעולה בלתי הפיכה.')) {
        return
    }

    isDeleting.value = true

    await form.delete(route('transactions.destroy', props.transaction.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleting.value = false
            emit('transaction-deleted')
            emit('close')
        },
        onError: () => {
            isDeleting.value = false
        },
    })
}

const handleClose = () => {
    form.reset()
    budgetWarning.value = ''
    isLoading.value = false
    isDeleting.value = false
    emit('close')
}
</script>
