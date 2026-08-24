<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../Components/FormField.vue';

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="grid min-h-dvh place-items-center bg-gray-50 px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-2.5 text-center">
                <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5">
                        <path d="M5 19V10M12 19V5M19 19v-6" stroke-linecap="round" />
                    </svg>
                </span>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Dashboard Keuangan</h1>
                <p class="text-xs text-slate-500">Masuk untuk melihat ringkasan arus kas Anda.</p>
            </div>

            <form class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70" @submit.prevent="submit">
                <FormField label="Email" required :error="form.errors.email">
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Kata sandi" required :error="form.errors.password">
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <label class="flex items-center gap-2 text-xs text-slate-500">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                    />
                    Ingat saya
                </label>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Memproses...' : 'Masuk' }}
                </button>

                <p class="text-center text-xs text-slate-500">
                    Belum punya akun?
                    <Link href="/register" class="font-semibold text-emerald-600 hover:text-emerald-700">Daftar</Link>
                </p>
            </form>
        </div>
    </div>
</template>
