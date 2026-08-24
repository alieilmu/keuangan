<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../Components/FormField.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Daftar" />

    <div class="grid min-h-dvh place-items-center bg-gray-50 px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-2.5 text-center">
                <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5">
                        <path d="M5 19V10M12 19V5M19 19v-6" stroke-linecap="round" />
                    </svg>
                </span>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Buat Akun</h1>
                <p class="text-xs text-slate-500">Akun, kategori, dan struktur dasar disiapkan otomatis.</p>
            </div>

            <form class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70" @submit.prevent="submit">
                <FormField label="Nama" required :error="form.errors.name">
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Email" required :error="form.errors.email">
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Kata sandi" required :error="form.errors.password" hint="Minimal 8 karakter.">
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Ulangi kata sandi" required>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Memproses...' : 'Daftar' }}
                </button>

                <p class="text-center text-xs text-slate-500">
                    Sudah punya akun?
                    <Link href="/login" class="font-semibold text-emerald-600 hover:text-emerald-700">Masuk</Link>
                </p>
            </form>
        </div>
    </div>
</template>
