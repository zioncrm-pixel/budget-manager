<template>
    <Modal :show="show" @close="handleClose">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-1">
                {{ modalTitle }}
            </h2>
            <p class="text-sm text-gray-500 mb-4">
                {{ modalSubtitle }}
            </p>

            <form @submit.prevent="submitForm" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="source_name" value="שם מקור" />
                        <TextInput
                            id="source_name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="למשל: משכורת"
                            required
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="source_type" value="סוג" />
                        <select
                            id="source_type"
                            v-model="form.type"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="income">הכנסה</option>
                            <option value="expense">הוצאה</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel value="אפשר זיכויים" />
                    <label class="mt-3 inline-flex items-start gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            v-model="form.allows_refunds"
                        />
                        <span>
                            אפשר שיוך של עסקאות הפוכות (לדוגמה זיכויים) למקור זה. הסכומים ייקוזזו מהסך הכולל.
                        </span>
                    </label>
                    <InputError :message="form.errors.allows_refunds" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="source_color_hex" value="צבע" />
                        <div class="mt-1 flex items-center gap-3">
                            <input
                                id="source_color_picker"
                                v-model="form.color"
                                type="color"
                                class="h-10 w-12 rounded-md border border-gray-300 bg-white p-0"
                                aria-label="בחר צבע"
                            />
                            <TextInput
                                id="source_color_hex"
                                v-model="form.color"
                                type="text"
                                class="block w-full"
                                placeholder="#3B82F6"
                                required
                            />
                        </div>
                        <InputError :message="form.errors.color" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="source_icon_input" value="אייקון" />
                        <IconPicker
                            input-id="source_icon_input"
                            v-model="form.icon"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.icon" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="budget_year" value="שנה" />
                        <select
                            id="budget_year"
                            v-model="form.year"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="yearOption in yearOptions" :key="yearOption" :value="yearOption">
                                {{ yearOption }}
                            </option>
                        </select>
                        <InputError :message="form.errors.year" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="budget_month" value="חודש" />
                        <select
                            id="budget_month"
                            v-model="form.month"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="option in monthOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.month" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="planned_amount" value="סכום תקציב מתוכנן" />
                        <TextInput
                            id="planned_amount"
                            v-model="form.planned_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                            placeholder="0.00"
                        />
                        <InputError :message="form.errors.planned_amount" class="mt-2" />
                    </div>

                    <div v-if="budgetSummary" class="flex flex-col justify-end gap-1 text-sm text-gray-600">
                        <div class="flex items-center justify-between">
                            <span>בוצע:</span>
                            <span class="font-semibold" :class="budgetSummaryColor">{{ formatCurrency(budgetSummary.spent_amount) }} ₪</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>נותר:</span>
                            <span class="font-semibold" :class="budgetSummary.remaining_amount >= 0 ? 'text-green-600' : 'text-red-600'">
                                {{ formatCurrency(budgetSummary.remaining_amount) }} ₪
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="budgetProgressLabel" class="mt-2 rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-600">
                    ניצול תקציב: <span :class="budgetSummaryColor">{{ budgetProgressLabel }}</span>
                </div>

                <div>
                    <InputLabel for="source_description" value="תיאור (אופציונלי)" />
                    <textarea
                        id="source_description"
                        v-model="form.description"
                        rows="2"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="תיאור קצר למקור התזרים"
                    ></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="source_status" value="סטטוס" />
                        <select
                            id="source_status"
                            v-model="form.is_active"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="true">פעיל</option>
                            <option :value="false">לא פעיל</option>
                        </select>
                        <InputError :message="form.errors.is_active" class="mt-2" />
                    </div>

                    <div v-if="isEditMode" class="text-sm text-gray-500 flex flex-col gap-1 justify-end">
                        <span>
                            נוצר: {{ formattedCreatedAt }}
                        </span>
                        <span>
                            עודכן לאחרונה: {{ formattedUpdatedAt }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex-1"></div>
                    <div class="flex justify-end space-x-3 rtl:space-x-reverse">
                        <SecondaryButton type="button" @click="handleClose">
                            ביטול
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="isEditMode && budgetSummary"
                            type="button"
                            class="bg-white text-red-600 border border-red-200 hover:bg-red-50"
                            :disabled="isRemovingBudget"
                            @click="deleteBudget"
                        >
                            <svg v-if="isRemovingBudget" class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            מחק תקציב
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="isEditMode"
                            type="button"
                            class="bg-red-50 text-red-600 hover:bg-red-100 border border-red-200"
                            :disabled="deleteForm.processing"
                            @click="deleteSource"
                        >
                            <svg v-if="deleteForm.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            מחק מקור
                        </SecondaryButton>
                        <PrimaryButton :disabled="form.processing" type="submit">
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isEditMode ? 'עדכן מקור' : 'הוסף מקור' }}
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
import IconPicker from './IconPicker.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'create',
    },
    source: {
        type: Object,
        default: null,
    },
    year: {
        type: Number,
        required: true,
    },
    month: {
        type: Number,
        required: true,
    },
})

const emit = defineEmits(['close', 'saved', 'deleted'])

const form = useForm({
    name: '',
    type: 'income',
    color: '#3B82F6',
    icon: '',
    description: '',
    is_active: true,
    allows_refunds: false,
    planned_amount: '',
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
})

const deleteForm = useForm({})
const budgetDeleteForm = useForm({})
const isRemovingBudget = ref(false)

const isEditMode = computed(() => props.mode === 'edit' && props.source)

const modalTitle = computed(() => (isEditMode.value ? 'עריכת מקור תזרים' : 'הוספת מקור תזרים חדש'))
const modalSubtitle = computed(() => (isEditMode.value ? 'עדכון פרטי המקור הנבחר' : 'מלא את הפרטים ליצירת מקור תזרים חדש'))

const formattedCreatedAt = computed(() => {
    if (!props.source?.created_at) return '-'
    return new Date(props.source.created_at).toLocaleString('he-IL')
})

const formattedUpdatedAt = computed(() => {
    if (!props.source?.updated_at) return '-'
    return new Date(props.source.updated_at).toLocaleString('he-IL')
})

const initializeForm = () => {
    if (props.source) {
        form.defaults({
            name: props.source.name || '',
            type: props.source.type || 'income',
            color: props.source.color || '#3B82F6',
            icon: props.source.icon || '',
            description: props.source.description || '',
            is_active: props.source.is_active ?? true,
            allows_refunds: props.source.allows_refunds ?? false,
            planned_amount: props.source.budget?.planned_amount ? Number(props.source.budget.planned_amount).toFixed(2) : '',
            year: props.source.budget?.year || props.year,
            month: props.source.budget?.month || props.month,
        })
    } else {
        form.defaults({
            name: '',
            type: 'income',
            color: '#3B82F6',
            icon: '',
            description: '',
            is_active: true,
            allows_refunds: false,
            planned_amount: '',
            year: props.year,
            month: props.month,
        })
    }

    form.reset()
    form.clearErrors()
}

watch(
    () => props.show,
    (value) => {
        if (value) {
            initializeForm()
        }
    }
)

watch(
    () => props.source,
    () => {
        if (props.show) {
            initializeForm()
        }
    }
)

watch(
    () => [props.year, props.month],
    ([newYear, newMonth]) => {
        if (!props.show || isEditMode.value) {
            return
        }

        form.year = newYear
        form.month = newMonth
    }
)

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear()
    const years = []
    for (let offset = -2; offset <= 3; offset += 1) {
        years.push(currentYear + offset)
    }
    return years
})

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

const budgetSummary = computed(() => props.source?.budget || null)

const budgetSummaryColor = computed(() => {
    if (!budgetSummary.value) {
        return 'text-gray-600'
    }

    return budgetSummary.value.spent_amount >= budgetSummary.value.planned_amount
        ? 'text-red-600'
        : 'text-indigo-600'
})

const budgetProgressLabel = computed(() => {
    if (!budgetSummary.value) {
        return ''
    }

    return `${budgetSummary.value.progress_percentage}% נוצל`
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0)
}

const submitForm = () => {
    if (isEditMode.value && props.source) {
        form.put(route('cashflow.sources.update', props.source.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved')
            },
        })
    } else {
        form.post(route('cashflow.sources.store'), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved')
            },
        })
    }
}

const deleteBudget = () => {
    if (!props.source) {
        return
    }

    if (!confirm('למחוק את תקציב המקור לחודש הנבחר?')) {
        return
    }

    isRemovingBudget.value = true

    budgetDeleteForm.delete(route('cashflow.sources.budget.destroy', props.source.id), {
        preserveScroll: true,
        data: {
            year: form.year,
            month: form.month,
        },
        onSuccess: () => {
            emit('saved')
        },
        onFinish: () => {
            isRemovingBudget.value = false
        },
    })
}

const deleteSource = () => {
    if (!props.source) {
        return
    }

    if (!confirm('למחוק את המקור הזה? העסקאות ישויכו ללא מקור.')) {
        return
    }

    deleteForm.delete(route('cashflow.sources.destroy', props.source.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('deleted')
        },
    })
}

const handleClose = () => {
    emit('close')
}
</script>
