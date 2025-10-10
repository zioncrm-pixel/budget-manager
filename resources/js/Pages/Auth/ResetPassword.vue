<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="הגדרת סיסמה חדשה" />

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

            <div class="mt-4">
                <InputLabel for="password" value="סיסמה חדשה" class="text-right" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full text-right"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="אישור סיסמה"
                    class="text-right"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full text-right"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    שמירת סיסמה חדשה
                </PrimaryButton>
            </div>

            <div class="mt-6 flex justify-between text-sm">
                <Link
                    :href="route('login')"
                    class="text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    חזרה להתחברות
                </Link>
                <Link
                    :href="route('password.request')"
                    class="text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    לשלוח קישור חדש
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
