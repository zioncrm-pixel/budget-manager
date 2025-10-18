const STORAGE_KEY = 'bm:selected-period'

const isValidPeriod = (value) => {
    if (!value) {
        return false
    }

    const year = Number(value.year)
    const month = Number(value.month)

    if (!Number.isInteger(year) || !Number.isInteger(month)) {
        return false
    }

    if (month < 1 || month > 12) {
        return false
    }

    if (year < 1900 || year > 2100) {
        return false
    }

    return true
}

const readRaw = () => {
    if (typeof window === 'undefined') {
        return null
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY)
        return raw ? JSON.parse(raw) : null
    } catch (error) {
        console.warn('Failed to read persisted period', error)
        return null
    }
}

const dispatchChange = (detail) => {
    if (typeof window === 'undefined') {
        return
    }

    window.dispatchEvent(new CustomEvent('bm:period-changed', { detail }))
}

export const loadPeriod = () => {
    const parsed = readRaw()
    if (!isValidPeriod(parsed)) {
        return null
    }

    return {
        year: Number(parsed.year),
        month: Number(parsed.month),
    }
}

export const savePeriod = (year, month) => {
    if (typeof window === 'undefined') {
        return
    }

    const normalized = {
        year: Number(year),
        month: Number(month),
    }

    if (!isValidPeriod(normalized)) {
        return
    }

    const current = loadPeriod()
    if (current && current.year === normalized.year && current.month === normalized.month) {
        return
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized))
        dispatchChange(normalized)
    } catch (error) {
        console.warn('Failed to persist period', error)
    }
}

export const clearPeriod = () => {
    if (typeof window === 'undefined') {
        return
    }

    window.localStorage.removeItem(STORAGE_KEY)
    dispatchChange(null)
}

export const PERIOD_STORAGE_KEY = STORAGE_KEY
