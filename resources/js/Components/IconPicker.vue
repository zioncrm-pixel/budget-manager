<script setup>
import { computed, ref, watch } from 'vue'
import TextInput from './TextInput.vue'

const defaultIconOptions = [
    { value: '💰', label: 'כסף', keywords: ['כסף', 'money', 'income', 'salary', 'משכורת'] },
    { value: '💸', label: 'הוצאה', keywords: ['expense', 'shopping', 'תשלום', 'הוצאה', 'חשבונית'] },
    { value: '🏠', label: 'בית', keywords: ['בית', 'דיור', 'שכירות', 'mortgage', 'חשמל'] },
    { value: '🚗', label: 'רכב', keywords: ['רכב', 'car', 'fuel', 'דלק', 'ביטוח'] },
    { value: '🛒', label: 'קניות', keywords: ['קניות', 'groceries', 'סופר', 'מכולת'] },
    { value: '🍽️', label: 'מסעדה', keywords: ['מסעדה', 'אוכל', 'restaurant', 'eating out'] },
    { value: '☕', label: 'קפה', keywords: ['קפה', 'coffee', 'drinks'] },
    { value: '🎉', label: 'בילוי', keywords: ['בילוי', 'fun', 'party', 'event'] },
    { value: '🎁', label: 'מתנות', keywords: ['מתנה', 'gift', 'shopping'] },
    { value: '🎓', label: 'לימודים', keywords: ['לימודים', 'education', 'school', 'קורס', 'college'] },
    { value: '📚', label: 'ספרים', keywords: ['ספר', 'ספרים', 'books', 'study'] },
    { value: '🧸', label: 'ילדים', keywords: ['ילד', 'ילדים', 'toys', 'family'] },
    { value: '🏥', label: 'בריאות', keywords: ['בריאות', 'health', 'doctor', 'medicine'] },
    { value: '💊', label: 'תרופות', keywords: ['תרופה', 'medicine', 'health'] },
    { value: '💼', label: 'עבודה', keywords: ['עבודה', 'work', 'business'] },
    { value: '🧾', label: 'חשבוניות', keywords: ['חשבונית', 'invoice', 'קבלה', 'bill'] },
    { value: '💳', label: 'כרטיס אשראי', keywords: ['כרטיס', 'אשראי', 'credit', 'card'] },
    { value: '🏖️', label: 'חופשה', keywords: ['חופשה', 'vacation', 'travel', 'נופש'] },
    { value: '✈️', label: 'טיסה', keywords: ['טיסה', 'flight', 'נסיעה', 'travel'] },
    { value: '🧳', label: 'נסיעות', keywords: ['נסיעות', 'travel', 'business trip'] },
    { value: '🚆', label: 'תחבורה', keywords: ['תחבורה', 'transportation', 'train', 'bus'] },
    { value: '🛠️', label: 'תיקונים', keywords: ['תיקון', 'maintenance', 'tools', 'service'] },
    { value: '🧹', label: 'ניקיון', keywords: ['ניקיון', 'cleaning', 'home'] },
    { value: '🪙', label: 'חיסכון', keywords: ['חיסכון', 'savings', 'investment', 'השקעה'] },
    { value: '📈', label: 'השקעות', keywords: ['השקעות', 'investment', 'stocks'] },
    { value: '📄', label: 'חשבונות', keywords: ['חשבונות', 'bill', 'utilities', 'חשבונית'] },
    { value: '⚽', label: 'ספורט', keywords: ['ספורט', 'sport', 'gym', 'fitness'] },
    { value: '🎮', label: 'גיימינג', keywords: ['גיימינג', 'gaming', 'videogame'] },
    { value: '🎬', label: 'בידור', keywords: ['בידור', 'movies', 'cinema', 'tv'] },
    { value: '🎧', label: 'מוזיקה', keywords: ['music', 'מוזיקה', 'concert'] },
    { value: '🎨', label: 'אמנות', keywords: ['art', 'אמנות', 'creative'] },
    { value: '🎯', label: 'מטרות', keywords: ['מטרה', 'goal', 'יעד'] },
    { value: '💡', label: 'חשמל', keywords: ['חשמל', 'electricity', 'bill'] },
    { value: '🔥', label: 'חימום', keywords: ['חימום', 'gas', 'אש', 'winter'] },
    { value: '💧', label: 'מים', keywords: ['מים', 'water', 'utility'] },
    { value: '📞', label: 'תקשורת', keywords: ['טלפון', 'אינטרנט', 'communication', 'phone'] },
    { value: '📱', label: 'נייד', keywords: ['נייד', 'cell', 'mobile', 'smartphone'] },
    { value: '💻', label: 'מחשב', keywords: ['מחשב', 'computer', 'laptop'] },
    { value: '🖥️', label: 'טכנולוגיה', keywords: ['טכנולוגיה', 'tech', 'electronics'] },
    { value: '📦', label: 'משלוחים', keywords: ['משלוח', 'delivery', 'package'] },
    { value: '🧮', label: 'מיסוי', keywords: ['מס', 'tax', 'מיסוי', 'חישוב'] },
    { value: '🏦', label: 'בנק', keywords: ['בנק', 'bank', 'finance'] },
    { value: '🛏️', label: 'שינה', keywords: ['שינה', 'bed', 'furniture'] },
    { value: '🪑', label: 'רהיטים', keywords: ['רהיט', 'furniture', 'home'] },
    { value: '🧺', label: 'כביסה', keywords: ['כביסה', 'laundry', 'clothes'] },
    { value: '🧴', label: 'טיפוח', keywords: ['טיפוח', 'care', 'cosmetics'] },
    { value: '🍼', label: 'תינוקות', keywords: ['תינוק', 'baby', 'family'] },
    { value: '🐾', label: 'חיות', keywords: ['חיות', 'pet', 'dog', 'cat'] },
    { value: '🎒', label: 'בית ספר', keywords: ['בית ספר', 'school', 'kids'] },
]

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    iconOptions: {
        type: Array,
        default: () => [],
    },
    inputId: {
        type: String,
        default: null,
    },
    searchPlaceholder: {
        type: String,
        default: 'חיפוש אייקון...',
    },
    inputPlaceholder: {
        type: String,
        default: 'בחר או הדבק אייקון',
    },
})

const emit = defineEmits(['update:modelValue'])

const searchTerm = ref('')
const internalValue = ref(props.modelValue || '')
const isActive = ref(false)
const containerRef = ref(null)

watch(
    () => props.modelValue,
    (value) => {
        if (value !== internalValue.value) {
            internalValue.value = value || ''
        }
    }
)

watch(internalValue, (value) => {
    emit('update:modelValue', value)
})

const normalizedSearch = computed(() => searchTerm.value.trim().toLowerCase())

const availableIcons = computed(() => {
    return props.iconOptions.length ? props.iconOptions : defaultIconOptions
})

const filteredIcons = computed(() => {
    if (!normalizedSearch.value) {
        return availableIcons.value
    }

    return availableIcons.value.filter((option) => {
        const keywords = [
            option.value,
            option.label || '',
            ...(option.keywords || []),
        ]
            .join(' ')
            .toLowerCase()

        return keywords.includes(normalizedSearch.value)
    })
})

const activatePicker = () => {
    isActive.value = true
}

const closePicker = () => {
    isActive.value = false
    searchTerm.value = ''
}

const selectIcon = (icon) => {
    internalValue.value = icon.value
    closePicker()
}

const clearIcon = () => {
    internalValue.value = ''
}

const handleBlur = () => {
    requestAnimationFrame(() => {
        const activeElement = document.activeElement
        if (!containerRef.value?.contains(activeElement)) {
            closePicker()
        }
    })
}
</script>

<template>
    <div ref="containerRef" class="space-y-2">
        <div class="flex items-center gap-2">
            <div class="flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-white text-2xl">
                <span aria-hidden="true">{{ internalValue || '🔖' }}</span>
            </div>
            <TextInput
                :id="inputId"
                v-model="internalValue"
                type="text"
                :placeholder="inputPlaceholder"
                class="block w-full"
                @focus="activatePicker"
                @blur="handleBlur"
            />
            <button
                v-if="internalValue"
                type="button"
                class="rounded-md border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-100"
                @click="clearIcon"
            >
                נקה
            </button>
        </div>
        <div v-if="isActive" class="space-y-2">
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    v-model="searchTerm"
                    :placeholder="searchPlaceholder"
                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    @focus="activatePicker"
                    @blur="handleBlur"
                />
            </div>
            <div class="max-h-40 overflow-y-auto rounded-md border border-gray-200 bg-white p-2">
                <div
                    v-if="filteredIcons.length"
                    class="grid grid-cols-8 gap-2 sm:grid-cols-10"
                >
                    <button
                        v-for="icon in filteredIcons"
                        :key="icon.value"
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-md border text-lg transition focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        :class="icon.value === internalValue ? 'border-indigo-500 bg-indigo-50' : 'border-transparent hover:border-gray-300 hover:bg-gray-50'"
                        @click="selectIcon(icon)"
                    >
                        <span aria-hidden="true">{{ icon.value }}</span>
                        <span class="sr-only">{{ icon.label || icon.value }}</span>
                    </button>
                </div>
                <div v-else class="py-4 text-center text-sm text-gray-500">
                    לא נמצאו אייקונים מתאימים.
                </div>
            </div>
        </div>
    </div>
</template>
