<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PeriodSelector from '@/Components/PeriodSelector.vue'
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

watch(
    () => props.currentYear,
    (value) => {
        selectedYear.value = Number(value) || new Date().getFullYear()
    }
)

watch(
    () => props.currentMonth,
    (value) => {
        selectedMonth.value = Number(value) || new Date().getMonth() + 1
    }
)

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}

const categoryTypeBadgeClass = (type) => (type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')

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

const persistPeriod = (year, month) => {
    if (typeof window === 'undefined') return
    savePeriod(year, month)
}

const navigateToPeriod = (year, month, options = {}) => {
    persistPeriod(year, month)
    router.visit(`/budgets/overview?year=${year}&month=${month}`, {
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
        persistPeriod(queryYear, queryMonth)
        return
    }

    if (!stored) {
        persistPeriod(selectedYear.value, selectedMonth.value)
        return
    }

    if (stored.year !== selectedYear.value || stored.month !== selectedMonth.value) {
        selectedYear.value = stored.year
        selectedMonth.value = stored.month
        navigateToPeriod(stored.year, stored.month)
    } else {
        persistPeriod(stored.year, stored.month)
    }
}

const handleYearUpdate = (value) => {
    selectedYear.value = value
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleMonthUpdate = (value) => {
    selectedMonth.value = value
    navigateToPeriod(selectedYear.value, selectedMonth.value)
}

const handleToday = () => {
    const now = new Date()
    const year = now.getFullYear()
    const month = now.getMonth() + 1

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
            <div class="flex flex-col gap-4 text-right">
                <div class="flex w-full flex-row items-start gap-6 text-right">
                    <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2.5 text-right">
                            <p class="text-xs text-gray-500">מצב העו"ש</p>
                            <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(props.accountStatus) }} ₪</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2.5 text-right">
                            <p class="text-xs text-gray-500">יתרה</p>
                            <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(props.balance) }} ₪</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2.5 text-right">
                            <p class="text-xs text-gray-500">סה"כ הכנסות</p>
                            <p class="text-lg font-semibold text-green-600">{{ formatCurrency(props.totalIncome) }} ₪</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2.5 text-right">
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
                                @today="handleToday"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 text-right">תקציבי קטגוריות</h3>
                            <p class="text-sm text-gray-500">נהל תקציבים לחודש הנבחר, ערוך, מחק וצפה בעסקאות.</p>
                        </div>
                        <button
                            @click="openNewCategoryModal"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            הוסף קטגוריה
                        </button>
                    </div>

                    <div class="p-6">
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
                                                    {{ category.type === 'income' ? 'הכנסה' : 'הוצאה' }}
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
                                        <span>הוצא</span>
                                        <span class="font-semibold text-red-600">{{ formatCurrency(category.budget.spent_amount) }} ₪</span>
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
                                    {{ category.type === 'income' ? 'קטגוריית הכנסה ללא תקציב' : 'טרם נקבע תקציב לקטגוריה' }}
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
