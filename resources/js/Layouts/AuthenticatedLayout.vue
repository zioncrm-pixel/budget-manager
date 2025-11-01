<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { loadPeriod, PERIOD_STORAGE_KEY } from '@/utils/periodStorage';

const showingNavigationDropdown = ref(false);
const storedPeriod = ref(typeof window !== 'undefined' ? loadPeriod() : null);

const navigationItems = [
    {
        name: 'דשבורד',
        routeName: 'dashboard',
        iconPath: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001 1h4a1 1 0 001-1m-6 0V9a1 1 0 011-1h2a1 1 0 011 1v11',
    },
    {
        name: 'ניהול תזרים',
        routeName: 'cashflow.index',
        iconPath: 'M9 12h6m-6 4h6m-7 4h8a2 2 0 002-2V6a2 2 0 00-2-2h-3l-2-2H9L7 4H4a2 2 0 00-2 2v12a2 2 0 002 2h3',
    },
    {
        name: 'קטגוריות ותקציבים',
        routeName: 'budgets.overview',
        iconPath: 'M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m4 0V9a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.414-1.414a1 1 0 00-.707-.293H9a2 2 0 00-2 2v9m12 0H5',
    },
    {
        name: 'מקורות תזרים',
        routeName: 'cashflow.sources.index',
        iconPath: 'M12 8c1.657 0 3-1.79 3-4s-1.343-4-3-4-3 1.79-3 4 1.343 4 3 4zM6 22a6 6 0 0112 0H6z',
    },
    {
        name: 'ייבוא תזרים',
        routeName: 'cashflow.import.index',
        iconPath: 'M4 4v12a4 4 0 004 4h8a4 4 0 004-4V4m-8 0v8m0 0l-3-3m3 3l3-3',
    },
];

const updateStoredPeriod = (event) => {
    storedPeriod.value = event?.detail ?? (typeof window !== 'undefined' ? loadPeriod() : null);
};

const handleStorageEvent = (event) => {
    if (event.key === PERIOD_STORAGE_KEY) {
        storedPeriod.value = typeof window !== 'undefined' ? loadPeriod() : null;
    }
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('bm:period-changed', updateStoredPeriod);
    window.addEventListener('storage', handleStorageEvent);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('bm:period-changed', updateStoredPeriod);
    window.removeEventListener('storage', handleStorageEvent);
});

const linkWithPeriod = (name, params = {}) => {
    if (storedPeriod.value?.year && storedPeriod.value?.month) {
        params = {
            ...params,
            year: storedPeriod.value.year,
            month: storedPeriod.value.month,
        };
    }

    return route(name, params);
};
</script>

<template>
    <div class="min-h-screen bg-gray-100" dir="rtl">
        <div class="flex min-h-screen">
            <aside
                class="sticky top-0 hidden h-screen w-16 flex-col items-center border-l border-gray-200 bg-white py-6 sm:flex lg:w-20"
            >
                <div class="flex w-full flex-col items-center" >
                    <Link
                        :href="linkWithPeriod('dashboard')"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50"
                    >
                        <ApplicationLogo class="h-6 w-6 fill-current text-indigo-600" />
                        <span class="sr-only">Budget Manager</span>
                    </Link>
                    <nav class="mt-8 flex flex-1 flex-col items-center space-y-4 overflow-y-auto">
                        <Link
                            v-for="item in navigationItems"
                            :key="item.routeName"
                            :href="linkWithPeriod(item.routeName)"
                            :title="item.name"
                            class="group flex h-11 w-11 items-center justify-center rounded-full border border-transparent transition hover:border-indigo-200 hover:bg-indigo-50"
                            :class="route().current(item.routeName) ? 'bg-indigo-50 text-indigo-600 border-indigo-200' : 'text-gray-400'"
                        >
                            <svg
                                class="h-5 w-5"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :d="item.iconPath"
                                />
                            </svg>
                            <span class="sr-only">{{ item.name }}</span>
                        </Link>
                    </nav>
                </div>
                <div class="mt-auto flex flex-col items-center">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center rounded-full border border-transparent bg-gray-50 text-sm font-semibold text-gray-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-600 focus:outline-none"
                                :title="$page.props.auth.user.name"
                            >
                                {{ ($page.props.auth.user.name || '').charAt(0) || 'א' }}
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">
                                Profile
                            </DropdownLink>
                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </aside>

            <div class="flex min-h-screen flex-1 flex-col">
                <div class="sticky top-0 z-30 flex items-center justify-between bg-white px-4 py-3 shadow sm:hidden">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-gray-200 p-2 text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none"
                        @click="showingNavigationDropdown = true"
                    >
                        <span class="sr-only">פתח תפריט</span>
                        <svg
                            class="h-6 w-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <Link :href="linkWithPeriod('dashboard')" class="flex items-center gap-2">
                        <ApplicationLogo class="h-8 w-8 fill-current text-indigo-600" />
                        <span class="text-lg font-semibold text-gray-700">Budget Manager</span>
                    </Link>
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-600 transition hover:text-indigo-600 focus:outline-none"
                            >
                                {{ $page.props.auth.user.name }}
                                <svg
                                    class="-me-0.5 ms-2 h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">
                                Profile
                            </DropdownLink>
                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>

                <div
                    v-if="$slots.header"
                    class="sticky top-16 z-20 border-b border-gray-200 bg-white sm:top-0"
                >
                    <div class="px-4 py-6 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </div>

                <main class="flex-1">
                    <slot />
                </main>
            </div>
        </div>

        <transition name="fade">
            <div
                v-if="showingNavigationDropdown"
                class="fixed inset-0 z-50 flex flex-col bg-white/90 backdrop-blur-sm sm:hidden"
            >
                <div class="flex items-center justify-between bg-white px-4 py-3 shadow">
                    <Link :href="linkWithPeriod('dashboard')" class="flex items-center gap-2">
                        <ApplicationLogo class="h-8 w-8 fill-current text-indigo-600" />
                        <span class="text-lg font-semibold text-gray-700">Budget Manager</span>
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-gray-200 p-2 text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none"
                        @click="showingNavigationDropdown = false"
                    >
                        <span class="sr-only">סגור תפריט</span>
                        <svg
                            class="h-6 w-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-6">
                    <div class="space-y-3">
                        <ResponsiveNavLink
                            v-for="item in navigationItems"
                            :key="item.routeName"
                            :href="linkWithPeriod(item.routeName)"
                            :active="route().current(item.routeName)"
                            @click="showingNavigationDropdown = false"
                        >
                            {{ item.name }}
                        </ResponsiveNavLink>
                    </div>
                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <div class="text-base font-medium text-gray-800">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="text-sm font-medium text-gray-500">
                            {{ $page.props.auth.user.email }}
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')" @click="showingNavigationDropdown = false">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                @click="showingNavigationDropdown = false"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
