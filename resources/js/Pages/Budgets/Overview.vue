<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PeriodSelector from '@/Components/PeriodSelector.vue'
import BudgetManagerModal from '@/Components/BudgetManagerModal.vue'
import CategoryTransactionsModal from '@/Components/CategoryTransactionsModal.vue'

const props = defineProps({
    user: Object,
    currentYear: Number,
    currentMonth: Number,
    totalIncome: Number,
    totalExpenses: Number,
    balance: Number,
    categoriesWithBudgets: Array,
    cashFlowSources: Array,
    allCategories: Array,
    budgetsForMonth: Array,
})

const selectedYear = ref(Number(props.currentYear) || new Date().getFullYear())
const selectedMonth = ref(Number(props.currentMonth) || new Date().getMonth() + 1)
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

const navigateToPeriod = (year, month) => {
    router.visit(`/budgets/overview?year=${year}&month=${month}`, {
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
                <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        קטגוריות ותקציבים
                    </h2>
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

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
                                        <span class="text-3xl">{{ category.category_icon || '📁' }}</span>
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
