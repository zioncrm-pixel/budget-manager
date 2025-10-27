<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    cashFlowSources: {
        type: Array,
        default: () => [],
    },
    maxUploadSizeMb: {
        type: Number,
        default: 20,
    },
})

const steps = [
    { id: 1, title: 'טעינת נתונים' },
    { id: 2, title: 'ניקוי שורות' },
    { id: 3, title: 'מיפוי שדות' },
    { id: 4, title: 'סקירה וייבוא' },
]

const importMode = ref('file')
const clipboardContent = ref('')

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

const currentStep = ref(1)

const uploadState = reactive({
    file: null,
    source: 'file',
    importId: null,
    columns: [],
    rows: [],
    headerCandidates: [],
    detectedDateRange: { min: null, max: null },
    numericColumns: [],
    meta: {
        total_rows: 0,
        total_columns: 0,
    },
    loading: false,
    error: null,
})

const excludedRows = ref([])
const selectedHeaderRow = ref(null)

const mapping = reactive({
    date: { column: null, format: null },
    description: { column: null },
    amount: {
        mode: 'single',
        column: null,
        debit_column: null,
        credit_column: null,
        negate: false,
    },
    posting_date: {
        mode: 'same_as_transaction',
        column: null,
        format: null,
        value: null,
    },
    type: {
        mode: 'auto_from_amount',
        column: null,
        income_values: ['זכות', 'חיוב+'],
        expense_values: ['חובה', 'חיוב-', 'חיוב'],
        fixed_value: null,
    },
    reference: { column: null },
    notes: { column: null },
})

const incomeValuesInput = computed({
    get: () => (mapping.type.income_values || []).join(', '),
    set: (value) => {
        mapping.type.income_values = value
            .split(',')
            .map(item => item.trim())
            .filter(Boolean)
    },
})

const expenseValuesInput = computed({
    get: () => (mapping.type.expense_values || []).join(', '),
    set: (value) => {
        mapping.type.expense_values = value
            .split(',')
            .map(item => item.trim())
            .filter(Boolean)
    },
})

const defaults = reactive({
    category_id: null,
    cash_flow_source_id: null,
})

const rowAssignments = reactive({})

const previewState = reactive({
    loading: false,
    rows: [],
    errors: [],
    summary: null,
    lastUpdated: null,
    status: null,
    debug: null,
})

const commitState = reactive({
    loading: false,
    success: null,
    error: null,
})

const categoryTypeLabel = (type) => {
    if (type === 'income') {
        return 'הכנסה'
    }

    if (type === 'both') {
        return 'הכנסה והוצאה'
    }

    return 'הוצאה'
}

const headerRowRecord = computed(() => {
    if (selectedHeaderRow.value === null) return null
    return uploadState.rows.find(row => row.index === selectedHeaderRow.value) || null
})

const headerRowValues = computed(() => headerRowRecord.value?.values ?? [])

const columnOptions = computed(() => {
    return uploadState.columns.map(column => {
        const headerValue = headerRowValues.value[column.index]
        const normalizedHeader = typeof headerValue === 'string' ? headerValue.trim() : headerValue
        const labelParts = []

        if (normalizedHeader !== undefined && normalizedHeader !== null && normalizedHeader !== '') {
            labelParts.push(normalizedHeader)
        }

        labelParts.push(column.label)

        return {
            value: column.index,
            label: labelParts.join(' — '),
            samples: column.sample_values || [],
        }
    })
})

const importSourceLabel = computed(() => {
    if (uploadState.source === 'clipboard') {
        return 'הדבקה מלוח אקסל'
    }

    if (uploadState.file?.name) {
        return uploadState.file.name
    }

    return 'קובץ'
})

const headerRowOptions = computed(() => {
    if (!uploadState.rows.length) return []

    const rowsByIndex = new Map(uploadState.rows.map(row => [row.index, row]))
    const optionsInOrder = []

    const addOption = (row) => {
        if (!row) return
        if (optionsInOrder.some(option => option.value === row.index)) {
            return
        }

        const sample = (row.values || [])
            .slice(0, 5)
            .map(cell => (cell ?? '').toString().trim())
            .filter(Boolean)
            .join(' | ')

        optionsInOrder.push({
            value: row.index,
            label: `שורה ${row.original_index} — ${sample || '...'}`,
        })
    }

    (uploadState.headerCandidates || []).forEach(index => addOption(rowsByIndex.get(index)))

    uploadState.rows.slice(0, 10).forEach(addOption)
    if (selectedHeaderRow.value !== null) {
        addOption(rowsByIndex.get(selectedHeaderRow.value))
    }

    return optionsInOrder
})

const excludedRowSet = computed(() => new Set(excludedRows.value))

const normalizeErrors = (errors) => {
    if (!errors) {
        return []
    }

    if (Array.isArray(errors)) {
        return errors
    }

    if (typeof errors === 'object') {
        return Object.entries(errors).map(([field, value]) => ({
            row_index: null,
            field,
            message: Array.isArray(value) ? value.join(' ') : String(value),
        }))
    }

    return [{
        row_index: null,
        field: null,
        message: String(errors),
    }]
}

const suggestedExcludedCount = computed(() => uploadState.rows.filter(row => row.auto_skip).length)

const includedRowCount = computed(() => {
    const total = uploadState.rows.length
    return total - excludedRows.value.length
})

const canProceedToCleanup = computed(() => Boolean(uploadState.importId))
const canProceedToMapping = computed(() => canProceedToCleanup.value && includedRowCount.value > 0)
const canProceedToReview = computed(() => !!previewState.rows.length && !previewState.errors.length)
const hasAmountSelection = computed(() => {
    if (mapping.amount.mode === 'single') {
        return mapping.amount.column !== null && mapping.amount.column !== undefined
    }

    return mapping.amount.credit_column !== null || mapping.amount.debit_column !== null
})
const isPostingDateSelectionValid = computed(() => {
    if (mapping.posting_date.mode === 'column') {
        return mapping.posting_date.column !== null && mapping.posting_date.column !== undefined
    }

    if (mapping.posting_date.mode === 'fixed') {
        return Boolean(mapping.posting_date.value && String(mapping.posting_date.value).trim() !== '')
    }

    return true
})
const isTypeSelectionValid = computed(() => {
    if (mapping.amount.mode === 'split') {
        return true
    }

    if (mapping.type.mode === 'column') {
        return mapping.type.column !== null && mapping.type.column !== undefined
    }

    if (mapping.type.mode === 'fixed') {
        return !!mapping.type.fixed_value
    }

    return true
})
const canRequestPreview = computed(() =>
    mapping.date.column !== null &&
    mapping.description.column !== null &&
    hasAmountSelection.value &&
    isPostingDateSelectionValid.value &&
    isTypeSelectionValid.value &&
    includedRowCount.value > 0
)

watch(
    () => mapping.amount.mode,
    (mode) => {
        if (mode === 'single') {
            mapping.amount.debit_column = null
            mapping.amount.credit_column = null
        } else {
            mapping.amount.column = null
            mapping.type.mode = 'auto_from_amount'
            mapping.type.column = null
        }
    }
)

watch(
    () => mapping.posting_date.mode,
    (mode) => {
        if (mode === 'same_as_transaction') {
            mapping.posting_date.column = null
            mapping.posting_date.value = null
            mapping.posting_date.format = null
        } else if (mode === 'column') {
            mapping.posting_date.value = null
        } else if (mode === 'fixed') {
            mapping.posting_date.column = null
        }
    }
)

watch(selectedHeaderRow, (newValue, oldValue) => {
    const set = new Set(excludedRows.value)

    if (oldValue !== null && oldValue !== undefined) {
        set.delete(oldValue)
    }

    if (newValue !== null && newValue !== undefined) {
        set.add(newValue)
    }

    excludedRows.value = Array.from(set).sort((a, b) => a - b)
})

function resetAllState(options = {}) {
    const { preserveMode = true } = options

    if (!preserveMode) {
        importMode.value = 'file'
    }

    uploadState.source = importMode.value
    uploadState.file = null
    uploadState.importId = null
    uploadState.columns = []
    uploadState.rows = []
    uploadState.headerCandidates = []
    uploadState.detectedDateRange = { min: null, max: null }
    uploadState.numericColumns = []
    uploadState.meta = { total_rows: 0, total_columns: 0 }
    uploadState.error = null
    excludedRows.value = []
    selectedHeaderRow.value = null
    mapping.date.column = null
    mapping.description.column = null
    mapping.amount.mode = 'single'
    mapping.amount.column = null
    mapping.amount.debit_column = null
    mapping.amount.credit_column = null
    mapping.amount.negate = false
    mapping.posting_date.mode = 'same_as_transaction'
    mapping.posting_date.column = null
    mapping.posting_date.value = null
    mapping.posting_date.format = null
    mapping.type.mode = 'auto_from_amount'
    mapping.type.column = null
    mapping.type.income_values = ['זכות', 'חיוב+']
    mapping.type.expense_values = ['חובה', 'חיוב-', 'חיוב']
    mapping.type.fixed_value = null
    defaults.category_id = null
    defaults.cash_flow_source_id = null
    Object.keys(rowAssignments).forEach(key => delete rowAssignments[key])
    previewState.rows = []
    previewState.errors = []
    previewState.summary = null
    previewState.lastUpdated = null
    commitState.success = null
    commitState.error = null
    clipboardContent.value = ''
    currentStep.value = 1
}

function setStep(stepId) {
    if (stepId === currentStep.value) return

    if (stepId === 2 && !canProceedToCleanup.value) return
    if (stepId === 3 && !canProceedToMapping.value) return
    if (stepId === 4 && !canProceedToReview.value) return

    currentStep.value = stepId
}

function setImportMode(mode) {
    if (importMode.value === mode) return

    importMode.value = mode
    resetAllState()
}

function setRowExcluded(rowIndex, shouldExclude) {
    const next = new Set(excludedRows.value)
    if (shouldExclude) {
        next.add(rowIndex)
    } else {
        next.delete(rowIndex)
    }
    excludedRows.value = Array.from(next).sort((a, b) => a - b)
}

function excludeSuggestedRows() {
    const autoExcluded = uploadState.rows.filter(row => row.auto_skip).map(row => row.index)
    const set = new Set(excludedRows.value)
    autoExcluded.forEach(index => set.add(index))
    excludedRows.value = Array.from(set).sort((a, b) => a - b)
}

function includeAllRows() {
    const set = new Set()
    if (selectedHeaderRow.value !== null) {
        set.add(selectedHeaderRow.value)
    }
    excludedRows.value = Array.from(set).sort((a, b) => a - b)
}

function excludeAllRows() {
    excludedRows.value = uploadState.rows.map(row => row.index)
}

function autoMapColumns() {
    if (!uploadState.columns.length) return

    const dateColumn = uploadState.columns.find(column => column.detected_types?.includes('date'))
    const amountColumn = uploadState.columns.find(column => column.detected_types?.includes('number'))
    const descriptionColumn = uploadState.columns.find(column => column.index !== dateColumn?.index && column.index !== amountColumn?.index)

    mapping.date.column = dateColumn?.index ?? 0
    mapping.description.column = descriptionColumn?.index ?? (uploadState.columns[0]?.index ?? null)
    mapping.amount.column = amountColumn?.index ?? (uploadState.columns[uploadState.columns.length - 1]?.index ?? null)
}

function handleRowAssignmentChange(rowIndex, field, value) {
    const key = String(rowIndex)
    if (!rowAssignments[key]) {
        rowAssignments[key] = {
            category_id: null,
            cash_flow_source_id: null,
            notes: null,
        }
    }

    rowAssignments[key][field] = value || null
}

function buildPayload() {
    const payloadMapping = JSON.parse(JSON.stringify(mapping))
    const payloadDefaults = JSON.parse(JSON.stringify(defaults))
    const payloadAssignments = JSON.parse(JSON.stringify(rowAssignments))
    const headerRowIndex = selectedHeaderRow.value === null
        ? null
        : Number(selectedHeaderRow.value)

    return {
        import_id: uploadState.importId,
        excluded_rows: Array.from(new Set(excludedRows.value)),
        mapping: payloadMapping,
        defaults: payloadDefaults,
        row_assignments: payloadAssignments,
        header_row_index: headerRowIndex,
    }
}

async function requestPreview() {
    if (!uploadState.importId || !canRequestPreview.value) return

    previewState.loading = true
    previewState.errors = []
    commitState.success = null
    commitState.error = null
    previewState.debug = null
    previewState.status = null

    try {
        const response = await fetch(route('cashflow.import.transform'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(buildPayload()),
        })

        const data = await response.json()
        previewState.status = response.status

        if (!response.ok) {
            previewState.debug = data
            previewState.errors = normalizeErrors(data.errors)
            previewState.rows = data.rows ?? []
            previewState.summary = data.summary ?? null
            console.warn('Import preview failed', {
                status: response.status,
                data,
            })
            return
        }

        previewState.rows = data.rows || []
        previewState.summary = data.summary || null
        previewState.errors = normalizeErrors(data.errors)
        previewState.lastUpdated = new Date().toISOString()
        previewState.debug = null

        if (!previewState.errors.length) {
            currentStep.value = 4
        }
    } catch (error) {
        previewState.errors = [{ row_index: null, field: 'general', message: 'שגיאה בעת יצירת התצוגה המקדימה. נסה שוב.' }]
        previewState.debug = { message: error?.message ?? String(error) }
    } finally {
        previewState.loading = false
    }
}

async function commitImport() {
    if (!uploadState.importId || commitState.loading || !canRequestPreview.value) return

    commitState.loading = true
    commitState.success = null
    commitState.error = null

    try {
        const response = await fetch(route('cashflow.import.commit'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(buildPayload()),
        })

        const data = await response.json()

        if (!response.ok) {
            commitState.error = data.errors?.[0]?.message || 'הייבוא נכשל. בדוק את השגיאות ונסה שוב.'
            previewState.errors = normalizeErrors(data.errors)
            return
        }

        commitState.success = `ייבאנו בהצלחה ${data.summary?.count ?? 0} שורות.`
        previewState.summary = data.summary

        setTimeout(() => {
            router.visit(route('cashflow.index'))
        }, 1500)
    } catch (error) {
        commitState.error = 'אירעה שגיאה בייבוא. נסה שוב מאוחר יותר.'
    } finally {
        commitState.loading = false
    }
}

async function handleFileChange(event) {
    const file = event.target.files?.[0]
    if (!file) return

    uploadState.source = 'file'
    const maxBytes = props.maxUploadSizeMb * 1024 * 1024
    if (file.size > maxBytes) {
        uploadState.error = `גודל הקובץ גדול מהמקסימום (${props.maxUploadSizeMb}MB).`
        return
    }

    uploadState.loading = true
    uploadState.error = null
    commitState.success = null

    const formData = new FormData()
    formData.append('file', file)

    try {
        const response = await fetch(route('cashflow.import.upload'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: formData,
        })

        const data = await response.json()

        if (!response.ok) {
            uploadState.error = data.message || 'טעינת הקובץ נכשלה. בדוק את פורמט הקובץ ונסה שוב.'
            return
        }

        uploadState.file = data.file
        uploadState.importId = data.import_id
        uploadState.columns = data.columns || []
        uploadState.rows = data.rows || []
        uploadState.headerCandidates = data.header_candidates || []
        uploadState.detectedDateRange = data.detected_date_range || { min: null, max: null }
        uploadState.numericColumns = data.numeric_columns || []
        uploadState.meta = data.meta || uploadState.meta

        const autoExcluded = uploadState.rows.filter(row => row.auto_skip).map(row => row.index)
        excludedRows.value = autoExcluded.sort((a, b) => a - b)

        selectedHeaderRow.value = uploadState.headerCandidates[0] ?? null

        autoMapColumns()
        currentStep.value = 2
    } catch (error) {
        uploadState.error = 'אירעה שגיאה בטעינת הקובץ. נסה שוב מאוחר יותר.'
    } finally {
        uploadState.loading = false
        event.target.value = ''
    }
}

async function handleClipboardAnalyze() {
    if (importMode.value !== 'clipboard') {
        return
    }

    const content = clipboardContent.value

    if (!content || !content.trim()) {
        uploadState.error = 'לא זוהו נתונים להדבקה. הדבק טבלה מגיליון ונסה שוב.'
        return
    }

    uploadState.source = 'clipboard'
    uploadState.loading = true
    uploadState.error = null
    commitState.success = null

    try {
        const response = await fetch(route('cashflow.import.paste'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ content }),
        })

        const data = await response.json()

        if (!response.ok) {
            uploadState.error = data.message || 'ההדבקה נכשלה. וודא שהטבלה תקינה ונסה שוב.'
            return
        }

        uploadState.file = null
        uploadState.importId = data.import_id
        uploadState.columns = data.columns || []
        uploadState.rows = data.rows || []
        uploadState.headerCandidates = data.header_candidates || []
        uploadState.detectedDateRange = data.detected_date_range || { min: null, max: null }
        uploadState.numericColumns = data.numeric_columns || []
        uploadState.meta = data.meta || uploadState.meta

        const autoExcluded = uploadState.rows.filter(row => row.auto_skip).map(row => row.index)
        excludedRows.value = autoExcluded.sort((a, b) => a - b)

        selectedHeaderRow.value = uploadState.headerCandidates[0] ?? null

        autoMapColumns()
        currentStep.value = 2
    } catch (error) {
        uploadState.error = 'אירעה שגיאה בעת עיבוד ההדבקה. נסה שוב מאוחר יותר.'
    } finally {
        uploadState.loading = false
    }
}
</script>

<template>
    <Head title="ייבוא תזרים" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">ייבוא תזרים</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            תהליך הדרגתי: העלאת קובץ או הדבקת טבלה, ניקוי, מיפוי שדות ולבסוף ייבוא לתוך המערכת.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-indigo-600 hover:text-indigo-500"
                        @click="resetAllState"
                    >
                        התחל מהתחלה
                    </button>
                </div>

                <nav aria-label="Steps" class="mb-6">
                    <ol class="flex items-center">
                        <li
                            v-for="step in steps"
                            :key="step.id"
                            class="flex items-center"
                        >
                            <div
                                class="flex items-center cursor-pointer"
                                :class="{
                                    'text-indigo-600': currentStep >= step.id,
                                    'text-gray-400': currentStep < step.id,
                                }"
                                @click="setStep(step.id)"
                            >
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full border"
                                    :class="currentStep >= step.id ? 'border-indigo-600' : 'border-gray-300'"
                                >
                                    {{ step.id }}
                                </span>
                                <span class="ml-3 text-sm font-medium">{{ step.title }}</span>
                            </div>
                            <div
                                v-if="step.id !== steps.length"
                                class="mx-4 h-0.5 w-8"
                                :class="currentStep > step.id ? 'bg-indigo-600' : 'bg-gray-200'"
                            />
                        </li>
                    </ol>
                </nav>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <!-- Step 1: Upload -->
                    <div v-if="currentStep === 1">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">שלב 1: טעינת נתונים</h2>
                        <p class="text-sm text-gray-500 mb-4">
                            בחר אם להעלות קובץ או להדביק טבלה שהועתקה מאקסל או Google Sheets. ננתח את הנתונים ונזהה תאריכים, סכומים ותיאור תנועה.
                        </p>

                        <div class="flex flex-wrap items-center justify-end gap-3 mb-5">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium border transition"
                                :class="importMode === 'file' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                @click="setImportMode('file')"
                            >
                                העלאת קובץ
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium border transition"
                                :class="importMode === 'clipboard' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                @click="setImportMode('clipboard')"
                            >
                                הדבקה מהלוח
                            </button>
                        </div>

                        <div v-if="importMode === 'file'">
                            <label
                                class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100"
                            >
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg
                                        class="w-8 h-8 mb-3 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0ל-3 3m3-3v12"
                                        ></path>
                                    </svg>
                                    <p class="mb-2 text-sm text-gray-500">
                                        <span class="font-semibold">לחץ להעלאה</span> או גרור קובץ לכאן
                                    </p>
                                    <p class="text-xs text-gray-500">עד {{ maxUploadSizeMb }}MB</p>
                                </div>
                                <input
                                    class="hidden"
                                    type="file"
                                    accept=".csv,.xls,.xlsx,.xlsm,.txt"
                                    @change="handleFileChange"
                                />
                            </label>
                        </div>
                        <div v-else class="space-y-3">
                            <label class="block text-sm font-semibold text-gray-700 text-right">
                                הדבק כאן טבלה שהעתקת מגיליון
                            </label>
                            <textarea
                                v-model="clipboardContent"
                                rows="8"
                                class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-right"
                                placeholder="הדבק כאן את הנתונים (Ctrl+V / Cmd+V)"
                            ></textarea>
                            <p class="text-xs text-gray-500 text-right">
                                טיפ: בחר טווח תאים באקסל והשתמש בקיצור Ctrl+C / Cmd+C ולאחר מכן הדבק כאן.
                            </p>
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 transition"
                                    @click="clipboardContent = ''"
                                    :disabled="uploadState.loading || !clipboardContent.trim()"
                                >
                                    נקה טקסט
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
                                    :disabled="uploadState.loading"
                                    @click="handleClipboardAnalyze"
                                >
                                    נתח נתונים
                                </button>
                            </div>
                        </div>

                        <div v-if="uploadState.loading" class="mt-4 text-sm text-gray-500">
                            מנתח את הנתונים... זה עשוי להימשך מספר שניות.
                        </div>
                        <div v-if="uploadState.error" class="mt-4 text-sm text-red-600">
                            {{ uploadState.error }}
                        </div>
                        <div
                            v-if="uploadState.importId && !uploadState.loading && !uploadState.error"
                            class="mt-6 border rounded-lg p-4 bg-gray-50"
                        >
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">פרטי הייבוא</h3>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><span class="font-medium text-gray-800">מקור:</span> {{ importSourceLabel }}</li>
                                <li v-if="uploadState.file?.name && uploadState.source === 'file'">
                                    <span class="font-medium text-gray-800">שם קובץ:</span> {{ uploadState.file.name }}
                                </li>
                                <li><span class="font-medium text-gray-800">שורות:</span> {{ uploadState.meta.total_rows }}</li>
                                <li><span class="font-medium text-gray-800">טורים:</span> {{ uploadState.meta.total_columns }}</li>
                                <li v-if="uploadState.detectedDateRange?.min">
                                    <span class="font-medium text-gray-800">טווח תאריכים משוער:</span>
                                    {{ uploadState.detectedDateRange.min }} - {{ uploadState.detectedDateRange.max }}
                                </li>
                            </ul>
                            <button
                                type="button"
                                class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                                @click="currentStep = 2"
                            >
                                המשך לניקוי שורות
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Cleanup -->
                    <div v-else-if="currentStep === 2">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">שלב 2: ניקוי שורות</h2>

                        <div class="mb-6 rounded-lg border border-indigo-100 bg-indigo-50/60 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800">בחר שורת כותרות</h3>
                                    <p class="text-xs text-gray-600 mt-1">
                                        נשתמש בשורה זו כדי לזהות את שמות העמודות. השורה תסומן אוטומטית ולא תיובא לנתונים.
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <select
                                        v-model="selectedHeaderRow"
                                        class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option :value="null">ללא שורת כותרת</option>
                                        <option
                                            v-for="option in headerRowOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-white px-3 py-2 text-xs font-medium text-indigo-600 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="selectedHeaderRow = headerRowOptions[0]?.value ?? null"
                                    >
                                        בחירה אוטומטית
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <span class="text-sm text-gray-600">
                                נמצאו {{ uploadState.rows.length }} שורות בקובץ. מסומנות אוטומטית להסרה: {{ suggestedExcludedCount }}.
                            </span>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="px-3 py-1 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100"
                                    @click="excludeSuggestedRows"
                                >
                                    סמן שורות חשודות
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100"
                                    @click="includeAllRows"
                                >
                                    כל השורות רלוונטיות
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100"
                                    @click="excludeAllRows"
                                >
                                    הסר את כל השורות
                                </button>
                            </div>
                            <span class="text-sm text-gray-600">
                                ייבוא בפועל: {{ includedRowCount }} שורות
                            </span>
                        </div>

                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ייבוא</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">מס׳ מקורי</th>
                                        <th
                                            v-for="column in uploadState.columns"
                                            :key="column.index"
                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        >
                                            {{ column.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="row in uploadState.rows"
                                        :key="row.index"
                                        :class="{
                                            'bg-yellow-50': row.auto_skip,
                                            'opacity-60': excludedRowSet.has(row.index),
                                            'border-2 border-indigo-200': selectedHeaderRow === row.index,
                                        }"
                                    >
                                        <td class="px-3 py-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                :checked="!excludedRowSet.has(row.index)"
                                                :disabled="selectedHeaderRow === row.index"
                                                @change="setRowExcluded(row.index, !$event.target.checked)"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-700">
                                            {{ row.original_index }}
                                        </td>
                                        <td
                                            v-for="column in uploadState.columns"
                                            :key="`${row.index}-${column.index}`"
                                            class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap"
                                        >
                                            {{ row.values[column.index] ?? '' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex items-center justify-between">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                @click="currentStep = 1"
                            >
                                חזרה לטעינת קובץ
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="!canProceedToMapping"
                                @click="currentStep = 3"
                            >
                                המשך למיפוי שדות
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Mapping -->
                    <div v-else-if="currentStep === 3">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">שלב 3: מיפוי שדות לתוך המערכת</h2>
                        <p class="text-sm text-gray-500 mb-6">
                            בחר עבור כל שדה במערכת את הטור המתאים מתוך הקובץ. ניתן להיעזר בדוגמאות שמופיעות ליד שם הטור.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">תאריך עסקה *</label>
                                <select
                                    v-model="mapping.date.column"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option :value="null" disabled>בחר עמודה</option>
                                    <option
                                        v-for="column in columnOptions"
                                        :key="column.value"
                                        :value="column.value"
                                    >
                                        {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                    </option>
                                </select>
                            </div>

                            <div class="md:col-span-2 border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700">תאריך חיוב</h3>
                                        <p class="text-xs text-gray-500 mt-1">
                                            ברירת המחדל משתמשת בתאריך העסקה. ניתן לבחור טור המכיל תאריך חיוב או להגדיר תאריך קבוע (למשל תאריך החיוב בעו"ש).
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.posting_date.mode"
                                                type="radio"
                                                value="same_as_transaction"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">זהה לתאריך העסקה</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.posting_date.mode"
                                                type="radio"
                                                value="column"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">טור ייעודי</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.posting_date.mode"
                                                type="radio"
                                                value="fixed"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">תאריך קבוע</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="mapping.posting_date.mode === 'column'" class="mt-3">
                                    <select
                                        v-model="mapping.posting_date.column"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option :value="null" disabled>בחר עמודה</option>
                                        <option
                                            v-for="column in columnOptions"
                                            :key="column.value"
                                            :value="column.value"
                                        >
                                            {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                        </option>
                                    </select>
                                </div>
                                <div v-else-if="mapping.posting_date.mode === 'fixed'" class="mt-3 flex flex-wrap gap-3">
                                    <input
                                        v-model="mapping.posting_date.value"
                                        type="date"
                                        class="block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                    <input
                                        v-model="mapping.posting_date.format"
                                        type="text"
                                        class="block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="פורמט מותאם (אופציונלי)"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">תיאור *</label>
                                <select
                                    v-model="mapping.description.column"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option :value="null" disabled>בחר עמודה</option>
                                    <option
                                        v-for="column in columnOptions"
                                        :key="column.value"
                                        :value="column.value"
                                    >
                                        {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                    </option>
                                </select>
                            </div>

                            <div class="md:col-span-2 border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700">סכום *</h3>
                                        <p class="text-xs text-gray-500 mt-1">
                                            ברוב הקבצים קיים טור סכום יחיד. אם קיימים טורי זכות/חובה נפרדים, בחר במצב "פיצול".
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.amount.mode"
                                                type="radio"
                                                value="single"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">טור יחיד</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.amount.mode"
                                                type="radio"
                                                value="split"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">טורי זכות/חובה</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="mapping.amount.mode === 'single'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">בחר עמודה</label>
                                        <select
                                            v-model="mapping.amount.column"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option :value="null" disabled>בחר עמודה</option>
                                            <option
                                                v-for="column in columnOptions"
                                                :key="column.value"
                                                :value="column.value"
                                            >
                                                {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex items-center mt-6">
                                        <label class="inline-flex items-center text-sm text-gray-600">
                                            <input
                                                v-model="mapping.amount.negate"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">הפוך סימן (לאזן חיוב/זכות)</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">טור זכות</label>
                                        <select
                                            v-model="mapping.amount.credit_column"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option :value="null" disabled>בחר עמודה</option>
                                            <option
                                                v-for="column in columnOptions"
                                                :key="column.value"
                                                :value="column.value"
                                            >
                                                {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">טור חובה</label>
                                        <select
                                            v-model="mapping.amount.debit_column"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option :value="null" disabled>בחר עמודה</option>
                                            <option
                                                v-for="column in columnOptions"
                                                :key="column.value"
                                                :value="column.value"
                                            >
                                                {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-2 border rounded-lg p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700">סוג תזרים</h3>
                                        <p v-if="mapping.amount.mode !== 'split'" class="text-xs text-gray-500 mt-1">
                                            ניתן לקבוע האם ההכנסה/הוצאה נקבעים אוטומטית לפי הסימן של הסכום, לפי טור ייעודי או לקבוע באופן קבוע.
                                        </p>
                                        <p v-else class="text-xs text-indigo-600 mt-1">
                                            בחרת טורי זכות/חובה, לכן הסוג מזוהה אוטומטית: ערך בטור הזכות ייחשב להכנסה וערך בטור החובה להוצאה.
                                        </p>
                                    </div>
                                    <div v-if="mapping.amount.mode !== 'split'" class="flex items-center gap-4 text-sm text-gray-600">
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.type.mode"
                                                type="radio"
                                                value="auto_from_amount"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">זיהוי אוטומטי</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.type.mode"
                                                type="radio"
                                                value="column"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">טור ייעודי</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input
                                                v-model="mapping.type.mode"
                                                type="radio"
                                                value="fixed"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="mr-2">קבוע</span>
                                        </label>
                                    </div>
                                </div>

                                <template v-if="mapping.amount.mode === 'split'">
                                    <div class="rounded-md border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                                        אין צורך לבחור טור נוסף – נזהה הכנסה/הוצאה לפי טורי הזכות והחובה שבחרת למעלה.
                                    </div>
                                </template>

                                <template v-else>
                                    <div v-if="mapping.type.mode === 'column'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">טור סוג תזרים</label>
                                            <select
                                                v-model="mapping.type.column"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option :value="null" disabled>בחר עמודה</option>
                                                <option
                                                    v-for="column in columnOptions"
                                                    :key="column.value"
                                                    :value="column.value"
                                                >
                                                    {{ column.label }} — {{ column.samples?.[0] ?? 'ללא דוגמה' }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">ערכים המקושרים להכנסה</label>
                                            <input
                                                v-model="incomeValuesInput"
                                                type="text"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="לדוגמה: זכות, אשראי"
                                            />
                                            <p class="text-xs text-gray-500 mt-1">הפרד ערכים באמצעות פסיק</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">ערכים המקושרים להוצאה</label>
                                            <input
                                                v-model="expenseValuesInput"
                                                type="text"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="לדוגמה: חובה, חיוב"
                                            />
                                            <p class="text-xs text-gray-500 mt-1">הפרד ערכים באמצעות פסיק</p>
                                        </div>
                                    </div>

                                    <div v-else-if="mapping.type.mode === 'fixed'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">בחר סוג</label>
                                            <select
                                                v-model="mapping.type.fixed_value"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option :value="null" disabled>בחר אפשרות</option>
                                                <option value="income">הכנסה</option>
                                                <option value="expense">הוצאה</option>
                                            </select>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">מספר אסמכתא (אופציונלי)</label>
                                <select
                                    v-model="mapping.reference.column"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option :value="null">ללא</option>
                                    <option
                                        v-for="column in columnOptions"
                                        :key="column.value"
                                        :value="column.value"
                                    >
                                        {{ column.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">הערות (אופציונלי)</label>
                                <select
                                    v-model="mapping.notes.column"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option :value="null">ללא</option>
                                    <option
                                        v-for="column in columnOptions"
                                        :key="column.value"
                                        :value="column.value"
                                    >
                                        {{ column.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="md:col-span-2 border rounded-lg p-4 bg-gray-50">
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">שיוך ברירת מחדל</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">קטגוריה</label>
                                        <select
                                            v-model="defaults.category_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option :value="null">ללא שיוך אוטומטי</option>
                                            <option
                                                v-for="category in props.categories"
                                                :key="category.id"
                                                :value="category.id"
                                            >
                                                {{ category.name }} ({{ categoryTypeLabel(category.type) }})
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">מקור תזרים</label>
                                        <select
                                            v-model="defaults.cash_flow_source_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option :value="null">ללא שיוך אוטומטי</option>
                                            <option
                                                v-for="source in props.cashFlowSources"
                                                :key="source.id"
                                                :value="source.id"
                                            >
                                                {{ source.name }} ({{ source.type === 'income' ? 'הכנסה' : 'הוצאה' }})
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-gray-500">
                                    ניתן יהיה לשנות שיוכים ברמת שורה בשלב הבא. ברירת המחדל הזו תחול רק במידה ואין זיהוי אוטומטי לפי היסטוריית התנועות שלך.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-between">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                @click="currentStep = 2"
                            >
                                חזרה לניקוי שורות
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="!canRequestPreview || previewState.loading"
                                @click="requestPreview"
                            >
                                המשך לתצוגה מקדימה
                            </button>
                        </div>

                        <div v-if="previewState.errors.length" class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <p class="font-semibold">קיימות שגיאות בתצוגה המקדימה, תקן את המיפוי ונסה שוב.</p>
                            <ul class="mt-2 space-y-1 list-disc pr-5">
                                <li
                                    v-for="(error, index) in previewState.errors"
                                    :key="`${index}-${error.field ?? ''}-${error.row_index ?? ''}`"
                                >
                                    <span v-if="error.row_index !== null">שורה {{ error.row_index + 1 }}:</span>
                                    <span v-if="error.field && error.field !== 'general'"> {{ error.field }} -</span>
                                    {{ error.message }}
                                </li>
                            </ul>
                            <details
                                v-if="previewState.debug"
                                class="mt-3 text-xs text-red-600"
                            >
                                <summary class="cursor-pointer font-medium underline">תשובת שרת מלאה</summary>
                                <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded bg-white/80 p-3 text-xs text-gray-800">
{{ JSON.stringify(previewState.debug, null, 2) }}
                                </pre>
                            </details>
                        </div>
                    </div>

                    <!-- Step 4: Review & Commit -->
                    <div v-else-if="currentStep === 4">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">שלב 4: סקירה וייבוא</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <p class="text-sm text-gray-500">סה״כ שורות בייבוא</p>
                                <p class="text-2xl font-semibold text-gray-900">
                                    {{ previewState.summary?.count ?? 0 }}
                                </p>
                            </div>
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <p class="text-sm text-gray-500">היקף הכנסות</p>
                                <p class="text-2xl font-semibold text-green-600">
                                    {{ previewState.summary?.income_total?.toLocaleString?.('he-IL', { minimumFractionDigits: 2 }) ?? '0.00' }} ₪
                                </p>
                            </div>
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <p class="text-sm text-gray-500">היקף הוצאות</p>
                                <p class="text-2xl font-semibold text-red-600">
                                    {{ previewState.summary?.expense_total?.toLocaleString?.('he-IL', { minimumFractionDigits: 2 }) ?? '0.00' }} ₪
                                </p>
                            </div>
                        </div>

                        <div v-if="previewState.errors.length" class="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <h3 class="text-sm font-semibold mb-2">לא ניתן להמשיך – יש שורות שדורשות טיפול:</h3>
                            <ul class="text-sm space-y-1 max-h-40 overflow-y-auto list-disc pr-5">
                                <li
                                    v-for="(error, index) in previewState.errors"
                                    :key="`${index}-${error.field ?? ''}-${error.row_index ?? ''}`"
                                >
                                    <span v-if="error.row_index !== null">שורה {{ error.row_index + 1 }}:</span>
                                    <span v-if="error.field && error.field !== 'general'"> {{ error.field }} -</span>
                                    {{ error.message }}
                                </li>
                            </ul>
                            <details
                                v-if="previewState.debug"
                                class="mt-3 text-xs text-red-600"
                            >
                                <summary class="cursor-pointer font-medium underline">תשובת שרת מלאה</summary>
                                <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded bg-white/80 p-3 text-xs text-gray-800">
{{ JSON.stringify(previewState.debug, null, 2) }}
                                </pre>
                            </details>
                            <button
                                type="button"
                                class="mt-3 inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"
                                @click="currentStep = 3"
                            >
                                חזרה למיפוי
                            </button>
                        </div>

                        <div class="border rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">תאריך</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">תאריך חיוב</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">תיאור</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">סכום</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">סוג</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">קטגוריה</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">מקור תזרים</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">הערות</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="row in previewState.rows"
                                        :key="row.row_index"
                                        class="hover:bg-gray-50"
                                    >
                                        <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">{{ row.transaction_date }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">{{ row.posting_date ?? row.transaction_date }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-700">{{ row.description }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">
                                            <span :class="row.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                                {{ row.amount.toLocaleString?.('he-IL', { minimumFractionDigits: 2 }) ?? row.amount }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">
                                            {{ row.type === 'income' ? 'הכנסה' : 'הוצאה' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-700">
                                            <select
                                                :value="rowAssignments[String(row.row_index)]?.category_id ?? row.category_id ?? defaults.category_id ?? ''"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                @change="handleRowAssignmentChange(row.row_index, 'category_id', $event.target.value ? Number($event.target.value) : null)"
                                            >
                                                <option :value="null">ללא</option>
                                                <option
                                                    v-for="category in props.categories.filter(cat => cat.type === row.type)"
                                                    :key="category.id"
                                                    :value="category.id"
                                                >
                                                    {{ category.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-700">
                                            <select
                                                :value="rowAssignments[String(row.row_index)]?.cash_flow_source_id ?? row.cash_flow_source_id ?? defaults.cash_flow_source_id ?? ''"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                @change="handleRowAssignmentChange(row.row_index, 'cash_flow_source_id', $event.target.value ? Number($event.target.value) : null)"
                                            >
                                                <option :value="null">ללא</option>
                                                <option
                                                    v-for="source in props.cashFlowSources.filter(src => src.type === row.type)"
                                                    :key="source.id"
                                                    :value="source.id"
                                                >
                                                    {{ source.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-700">
                                            <input
                                                :value="rowAssignments[String(row.row_index)]?.notes ?? row.notes ?? ''"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="הערות"
                                                @change="handleRowAssignmentChange(row.row_index, 'notes', $event.target.value)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex items-center justify-between">
                            <div class="space-x-3 space-x-reverse">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    @click="currentStep = 3"
                                >
                                    חזרה למיפוי
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200"
                                    @click="requestPreview"
                                >
                                    עדכן תצוגה מקדימה
                                </button>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 disabled:opacity-50"
                                :disabled="previewState.loading || commitState.loading || previewState.errors.length > 0 || !canRequestPreview"
                                @click="commitImport"
                            >
                                אשר ייבוא
                            </button>
                        </div>

                        <div v-if="commitState.success" class="mt-4 text-sm text-green-600">
                            {{ commitState.success }}
                        </div>
                        <div v-if="commitState.error" class="mt-4 text-sm text-red-600">
                            {{ commitState.error }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
