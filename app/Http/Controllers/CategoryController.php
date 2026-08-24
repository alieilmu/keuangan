<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount('transactions')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'type' => $category->type->value,
                'type_label' => $category->type->label(),
                'color' => $category->color,
                'icon' => $category->icon,
                'transactions_count' => $category->transactions_count,
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'transaction_types' => collect(TransactionType::cases())
                ->map(fn (TransactionType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $request->user()->categories()->create($request->validated());

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // Transaksi lama tetap dipertahankan (category_id -> null) agar histori
        // arus kas dan saldo akun tidak berubah.
        $category->delete();

        return back()->with('success', 'Kategori dihapus. Transaksi lama menjadi tanpa kategori.');
    }
}
