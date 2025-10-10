<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
    resetLink: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="אפס סיסמה" />

        <div class="mb-4 text-sm text-gray-600 text-right leading-relaxed">
            שכחת את הסיסמה? אין בעיה. הזן את כתובת הדוא&quot;ל שלך ונשלח אליך קישור לאיפוס סיסמה,
            דרכו תוכל להגדיר סיסמה חדשה ולעבור למערכת.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600 text-right"
        >
            {{ status }}
        </div>

        <div
            v-if="resetLink"
            class="mb-4 rounded border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-700 text-right"
        >
            קישור ישיר לאיפוס: 
            <a
                :href="resetLink"
                class="font-semibold underline"
            >
                לחץ כאן
            </a>
            או העתק את הכתובת: {{ resetLink }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="דוא&quot;ל" class="text-right" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full text-right"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    חזרה להתחברות
                </Link>
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    שליחת קישור לאיפוס
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
