<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    /**
     * Tampilkan dokumen. Berkas ada di disk privat, jadi harus lewat sini
     * supaya kepemilikannya dicek dulu.
     */
    public function show(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        // PDF & gambar ditampilkan inline; sisanya diunduh.
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return Storage::disk($document->disk)->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($document->original_name).'"',
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documents->delete($document);

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }
}
