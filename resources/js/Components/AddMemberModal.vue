<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';

/**
 * Menambah anggota ke kas bersama, dibuka dari menu profil.
 * Batas jumlah anggota mengikuti kuota paket langganan grup.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const group = computed(() => page.props.group ?? null);

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

// Bersihkan form tiap kali modal dibuka, supaya sisa isian atau pesan
// kesalahan dari percobaan sebelumnya tidak ikut tampil.
watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

const quotaLabel = computed(() => {
    if (!group.value) {
        return '';
    }

    return group.value.member_quota === null
        ? `${group.value.member_count} anggota (tanpa batas)`
        : `${group.value.member_count} dari ${group.value.member_quota} anggota terpakai`;
});

const quotaTone = computed(() => {
    if (!group.value || group.value.member_quota === null) {
        return 'bg-slate-50 text-slate-600 ring-slate-200';
    }

    return group.value.quota_full
        ? 'bg-amber-50 text-amber-800 ring-amber-600/20'
        : 'bg-emerald-50 text-emerald-800 ring-emerald-600/20';
});

function submit() {
    form.post('/group/members', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :open="open" title="Tambah Anggota Grup" @close="emit('close')">
        <div v-if="group" class="space-y-4">
            <!-- Ringkasan kuota paket -->
            <div class="rounded-xl px-3 py-2.5 ring-1 ring-inset" :class="quotaTone">
                <p class="text-xs font-semibold">{{ group.name }}</p>
                <p class="mt-0.5 text-[11px]">
                    Paket <strong>{{ group.plan_name ?? 'tanpa langganan' }}</strong> &mdash; {{ quotaLabel }}
                </p>
            </div>

            <!-- Anggota yang sudah ada -->
            <div>
                <p class="mb-1.5 text-xs font-medium text-slate-600">Anggota saat ini</p>
                <ul class="space-y-1">
                    <li
                        v-for="member in group.members"
                        :key="member.id"
                        class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                    >
                        <span class="min-w-0 truncate text-sm text-slate-700">
                            {{ member.name }}
                            <span v-if="member.is_self" class="text-[11px] text-slate-400">(Anda)</span>
                        </span>
                        <span class="ml-2 shrink-0 truncate text-[11px] text-slate-400">{{ member.email }}</span>
                    </li>
                </ul>
            </div>

            <!-- Kuota penuh: form disembunyikan, alasannya dijelaskan -->
            <p
                v-if="group.quota_full"
                class="rounded-xl bg-amber-50 px-3 py-2.5 text-[11px] leading-relaxed text-amber-800 ring-1 ring-inset ring-amber-600/20"
            >
                Kuota paket <strong>{{ group.plan_name }}</strong> sudah penuh. Hubungi admin untuk menaikkan paket
                sebelum menambah anggota baru.
            </p>

            <form v-else class="space-y-3 border-t border-slate-100 pt-4" @submit.prevent="submit">
                <p class="text-xs font-medium text-slate-600">
                    Akun anggota dibuat langsung di sini &mdash; ia tidak perlu mendaftar sendiri.
                </p>

                <FormField label="Nama" required :error="form.errors.name">
                    <input
                        v-model="form.name"
                        type="text"
                        autocomplete="off"
                        class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Email" required :error="form.errors.email">
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField
                    label="Kata Sandi"
                    required
                    hint="Minimal 8 karakter. Sampaikan kepada anggota tersebut untuk login."
                    :error="form.errors.password"
                >
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Ulangi Kata Sandi" required>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <div class="flex justify-end gap-2 pt-1">
                    <button
                        type="button"
                        class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500"
                        @click="emit('close')"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Tambah Anggota' }}
                    </button>
                </div>
            </form>
        </div>

        <p v-else class="text-sm text-slate-500">Akun Anda belum tergabung dalam grup mana pun.</p>
    </Modal>
</template>
