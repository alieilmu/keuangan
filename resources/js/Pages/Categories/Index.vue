<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import FormField from '../../Components/FormField.vue';
import ColorPicker from '../../Components/ColorPicker.vue';

const props = defineProps({
    categories: Array,
    transaction_types: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({ name: '', type: 'expense', color: '#10b981', icon: '' });

const grouped = computed(() => ({
    expense: props.categories.filter((category) => category.type === 'expense'),
    income: props.categories.filter((category) => category.type === 'income'),
}));

watch(
    () => showForm.value,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (editing.value) {
            Object.assign(form, {
                name: editing.value.name,
                type: editing.value.type,
                color: editing.value.color,
                icon: editing.value.icon ?? '',
            });
        } else {
            form.reset();
        }
    },
);

function create(type) {
    editing.value = null;
    showForm.value = true;
    form.type = type;
}

function edit(category) {
    editing.value = category;
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/categories/${editing.value.id}`, options);
    } else {
        form.post('/categories', options);
    }
}

function destroy(category) {
    if (!window.confirm(`Hapus kategori ${category.name}? Transaksi lama menjadi tanpa kategori.`)) {
        return;
    }

    router.delete(`/categories/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Kategori" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Kategori</h1>
                <p class="mt-0.5 text-xs text-slate-500">{{ categories.length }} kategori tersimpan</p>
            </div>

            <button
                type="button"
                class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                @click="create('expense')"
            >
                + Kategori
            </button>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card
                v-for="group in [
                    { key: 'expense', title: 'Pengeluaran' },
                    { key: 'income', title: 'Pemasukan' },
                ]"
                :key="group.key"
                :title="group.title"
                :subtitle="`${grouped[group.key].length} kategori`"
            >
                <template #actions>
                    <button
                        type="button"
                        class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                        @click="create(group.key)"
                    >
                        Tambah
                    </button>
                </template>

                <ul v-if="grouped[group.key].length" class="divide-y divide-slate-100">
                    <li v-for="category in grouped[group.key]" :key="category.id" class="flex items-center gap-3 py-2.5">
                        <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: category.color }" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-800">{{ category.name }}</p>
                            <p class="text-xs text-slate-400">{{ category.transactions_count }} transaksi</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-0.5">
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Ubah"
                                @click="edit(category)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                aria-label="Hapus"
                                @click="destroy(category)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </li>
                </ul>

                <EmptyState v-else title="Belum ada kategori" />
            </Card>
        </div>
    </div>

    <Modal :open="showForm" :title="editing ? 'Ubah Kategori' : 'Tambah Kategori'" @close="showForm = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Nama kategori" required :error="form.errors.name">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="60"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Tipe" required :error="form.errors.type">
                <select
                    v-model="form.type"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option v-for="type in transaction_types" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
            </FormField>

            <FormField label="Warna" :error="form.errors.color">
                <ColorPicker v-model="form.color" />
            </FormField>

            <div class="flex gap-2 pt-1">
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
                    @click="showForm = false"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
