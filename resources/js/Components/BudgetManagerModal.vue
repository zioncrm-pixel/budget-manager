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
                        <InputLabel for="category_name" value="שם קטגוריה" />
                        <TextInput
                            id="category_name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="למשל: בילויים"
                            required
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="category_type" value="סוג" />
                        <select
                            id="category_type"
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

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="category_color" value="צבע (HEX)" />
                        <TextInput
                            id="category_color"
                            v-model="form.color"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="#3B82F6"
                            required
                        />
                        <InputError :message="form.errors.color" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="category_icon" value="אייקון" />
                        <TextInput
                            id="category_icon"
                            v-model="form.icon"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="🎉 או שם אייקון"
                        />
                        <InputError :message="form.errors.icon" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel for="category_description" value="תיאור (אופציונלי)" />
                    <textarea
                        id="category_description"
                        v-model="form.description"
                        rows="2"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="תיאור קצר לקטגוריה"
                    ></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="budget_planned" value="סכום תקציב מתוכנן" />
                        <TextInput
                            id="budget_planned"
                            v-model="form.planned_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                            placeholder="0.00"
                        />
                        <InputError :message="form.errors.planned_amount" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="budget_status" value="האם הקטגוריה פעילה?" />
                        <select
                            id="budget_status"
                            v-model="form.is_active"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="true">פעילה</option>
                            <option :value="false">לא פעילה</option>
                        </select>
                        <InputError :message="form.errors.is_active" class="mt-2" />
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
                        <SecondaryButton type="button" @click="handleClose">
                            ביטול
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="isEditMode && props.category?.budget?.id"
                            type="button"
                            class="bg-white text-red-600 border border-red-200 hover:bg-red-50"
                            :disabled="isDeleting"
                            @click="deleteBudget"
                        >
                            <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            מחק תקציב
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="props.category"
                            type="button"
                            class="bg-red-50 text-red-600 hover:bg-red-100 border border-red-200"
                            :disabled="isDeleting"
                            @click="deleteCategory"
                        >
                            <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            מחק קטגוריה
                        </SecondaryButton>
                        <PrimaryButton :disabled="isLoading" type="submit">
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isEditMode ? 'עדכן קטגוריה/תקציב' : 'הוסף קטגוריה' }}
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

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'create',
    },
    category: {
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

const isEditMode = computed(() => props.mode === 'edit' && props.category)

const form = useForm({
    name: '',
    type: 'expense',
    color: '#3B82F6',
    icon: '',
    description: '',
    is_active: true,
    planned_amount: '',
})

const isLoading = ref(false)
const isDeleting = ref(false)

const formattedCreatedAt = computed(() => {
    if (!props.category?.created_at) return '-'
    return new Date(props.category.created_at).toLocaleString('he-IL')
})

const formattedUpdatedAt = computed(() => {
    if (!props.category?.updated_at) return '-'
    return new Date(props.category.updated_at).toLocaleString('he-IL')
})

const modalTitle = computed(() => {
    if (isEditMode.value) {
        return 'עריכת קטגוריה ותקציב'
    }

    if (props.category) {
        return 'הגדרת תקציב לקטגוריה קיימת'
    }

    return 'הוספת קטגוריה חדשה'
})
const modalSubtitle = computed(() => {
    if (isEditMode.value) {
        return 'עדכן את פרטי הקטגוריה והתקציב שלה.'
    }

    if (props.category) {
        return 'הגדר תקציב וחידד את פרטי הקטגוריה הקיימת.'
    }

    return 'צור קטגוריה חדשה והגדר לה תקציב לחודש הנבחר.'
})

const initializeForm = () => {
    if (props.category) {
        const category = props.category
        form.defaults({
            name: category.category_name || category.name || '',
            type: category.type || 'expense',
            color: category.category_color || category.color || '#3B82F6',
            icon: category.category_icon || category.icon || '',
            description: category.description || '',
            is_active: category.is_active === undefined ? true : Boolean(category.is_active),
            planned_amount: category.budget ? Number(category.budget.planned_amount).toFixed(2) : '',
        })
    } else {
        form.defaults({
            name: '',
            type: 'expense',
            color: '#3B82F6',
            icon: '',
            description: '',
            is_active: true,
            planned_amount: '',
        })
    }

    form.reset()
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
    () => props.category,
    () => {
        if (props.show && isEditMode.value) {
            initializeForm()
        }
    }
)

const submitForm = async () => {
    isLoading.value = true

    form.transform((data) => {
        const payload = {
            ...data,
            planned_amount: data.planned_amount ? parseFloat(data.planned_amount) : null,
            is_active: data.is_active === true || data.is_active === 'true',
            year: props.year,
            month: props.month,
        }

        if (props.category) {
            payload.category_id = props.category.category_id || props.category.id
        }

        return payload
    })

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isLoading.value = false
            emit('saved')
            handleClose()
        },
        onError: () => {
            isLoading.value = false
        },
    }

    try {
        if (isEditMode.value && props.category?.budget?.id) {
            await form.put(route('budgets.manage.update', props.category.budget.id), options)
        } else {
            await form.post(route('budgets.manage.store'), options)
        }
    } finally {
        isLoading.value = false
    }
}

const deleteBudget = async () => {
    if (!props.category?.budget?.id) {
        return
    }

    if (!confirm('למחוק את התקציב עבור קטגוריה זו? הקטגוריה תישאר ללא תקציב.')) {
        return
    }

    isDeleting.value = true

    await form.delete(route('budgets.manage.destroy', props.category.budget.id), {
        preserveScroll: true,
        data: { remove_category: false },
        onSuccess: () => {
            isDeleting.value = false
            emit('deleted')
            handleClose()
        },
        onError: () => {
            isDeleting.value = false
        },
    })
}

const deleteCategory = async () => {
    if (!props.category) {
        return
    }

    if (!confirm('למחוק את הקטגוריה והתקציב שלה? העסקאות יישארו ללא שיוך.')) {
        return
    }

    isDeleting.value = true

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isDeleting.value = false
            emit('deleted')
            handleClose()
        },
        onError: () => {
            isDeleting.value = false
        },
    }

    if (props.category?.budget?.id) {
        await form.delete(route('budgets.manage.destroy', props.category.budget.id), {
            ...options,
            data: { remove_category: true },
        })
    } else {
        await form.delete(route('budgets.manage.destroy_category', props.category.category_id || props.category.id), options)
    }
}

const handleClose = () => {
    form.transform((data) => data)
    form.reset()
    isLoading.value = false
    isDeleting.value = false
    emit('close')
}
</script>
