<template>
    <!-- מודל עריכת תקציב -->
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- רקע כהה -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

        <!-- תוכן המודל -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-right shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                
                <!-- כותרת המודל -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-3 sm:px-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium leading-6 text-white" id="modal-title">
                            עריכת תקציב
                        </h3>
                        <button @click="closeModal" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- תוכן המודל -->
                <div class="px-4 py-5 sm:p-6">
                    <!-- מידע על הקטגוריה -->
                    <div class="mb-6 text-center">
                        <div class="text-4xl mb-2">{{ budgetData.category_icon || '📊' }}</div>
                        <h4 class="text-xl font-semibold text-gray-900">{{ budgetData.category_name }}</h4>
                        <p class="text-sm text-gray-500">{{ getMonthYearText(budgetData.year, budgetData.month) }}</p>
                    </div>

                    <!-- טופס עריכת תקציב -->
                    <form @submit.prevent="updateBudget" class="space-y-6">
                        <!-- סכום מתוכנן -->
                        <div>
                            <label for="planned_amount" class="block text-sm font-medium text-gray-700 text-right mb-2">
                                סכום מתוכנן (ש"ח)
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    id="planned_amount"
                                    v-model="form.planned_amount"
                                    step="0.01"
                                    min="0"
                                    max="999999.99"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-right text-lg"
                                    :class="{ 'border-red-500': errors.planned_amount }"
                                    placeholder="0.00"
                                    required
                                />
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">₪</span>
                                </div>
                            </div>
                            <p v-if="errors.planned_amount" class="mt-1 text-sm text-red-600 text-right">
                                {{ errors.planned_amount }}
                            </p>
                        </div>

                        <!-- מידע על התקציב הנוכחי -->
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <h5 class="font-medium text-gray-900 text-center">מידע על התקציב</h5>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="text-center">
                                    <div class="text-gray-500">הוצא עד כה</div>
                                    <div class="font-semibold text-red-600">{{ formatCurrency(budgetData.spent_amount || 0) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-gray-500">נותר</div>
                                    <div class="font-semibold" :class="getRemainingAmountColor">
                                        {{ formatCurrency(calculateRemainingAmount) }}
                                    </div>
                                </div>
                            </div>

                            <!-- בר התקדמות -->
                            <div class="mt-3">
                                <div class="flex justify-between text-xs text-gray-600 mb-1">
                                    <span>0 ש"ח</span>
                                    <span>{{ formatCurrency(form.planned_amount || 0) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-300 bg-blue-500"
                                         :style="{ width: form.planned_amount > 0 ? Math.min((budgetData.spent_amount / form.planned_amount) * 100, 100) + '%' : '0%' }">
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 text-center mt-1">
                                    {{ form.planned_amount > 0 ? Math.round((budgetData.spent_amount / form.planned_amount) * 100) : 0 }}% נוצל
                                </div>
                            </div>
                        </div>

                        <!-- כפתורים -->
                        <div class="flex justify-end space-x-3 space-x-reverse">
                            <button
                                type="button"
                                @click="closeModal"
                                class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                            >
                                ביטול
                            </button>
                            <button
                                type="submit"
                                :disabled="isLoading"
                                class="inline-flex justify-center rounded-md border border-transparent bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                            >
                                <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isLoading ? 'מעדכן...' : 'עדכן תקציב' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

// Props
const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    budgetData: {
        type: Object,
        default: () => ({})
    }
})

// Emits
const emit = defineEmits(['close', 'updated'])

// Reactive data
const isLoading = ref(false)
const errors = ref({})

// Form data
const form = ref({
    planned_amount: 0
})

// Computed
const calculateRemainingAmount = computed(() => {
    return Math.max(0, form.value.planned_amount - props.budgetData.spent_amount)
})

// Methods
const closeModal = () => {
    emit('close')
    resetForm()
}

const resetForm = () => {
    form.value.planned_amount = 0
    errors.value = {}
}

const getRemainingAmountColor = () => {
    const remaining = calculateRemainingAmount.value
    const spent = props.budgetData.spent_amount
    const planned = form.value.planned_amount
    
    if (planned <= 0) return 'text-gray-600'
    if (remaining <= 0) return 'text-red-600'
    if (remaining < planned * 0.1) return 'text-orange-600'
    if (remaining < planned * 0.25) return 'text-yellow-600'
    return 'text-green-600'
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount || 0)
}

const getMonthYearText = (year, month) => {
    const months = [
        'ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני',
        'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'
    ]
    return `${months[month - 1]} ${year}`
}

const updateBudget = async () => {
    if (!props.budgetData.id) return
    
    isLoading.value = true
    errors.value = {}

    try {
        const response = await fetch(`/budgets/${props.budgetData.id}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                budget_id: props.budgetData.id,
                planned_amount: parseFloat(form.value.planned_amount)
            })
        })

        const data = await response.json()

        if (data.success) {
            // עדכון מוצלח
            emit('updated', data.budget)
            closeModal()
            
            // הצגת הודעת הצלחה
            showSuccessMessage(data.message)
        } else {
            // שגיאות ולידציה
            if (data.errors) {
                errors.value = data.errors
            } else {
                showErrorMessage(data.message || 'שגיאה בעדכון התקציב')
            }
        }
    } catch (error) {
        console.error('Error updating budget:', error)
        showErrorMessage('שגיאה בתקשורת עם השרת')
    } finally {
        isLoading.value = false
    }
}

const showSuccessMessage = (message) => {
    // כאן אפשר להוסיף הודעת הצלחה יפה
    console.log('Success:', message)
}

const showErrorMessage = (message) => {
    // כאן אפשר להוסיף הודעת שגיאה יפה
    console.error('Error:', message)
}

// Watch for budget data changes
watch(() => props.budgetData, (newData) => {
    if (newData.planned_amount) {
        form.value.planned_amount = newData.planned_amount
    }
}, { immediate: true })
</script>
