<?php

namespace App\Services;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan dokumen tagihan & nota pembayaran.
 *
 * Berkas disimpan di disk privat (storage/app/private), bukan public, karena
 * isinya dokumen keuangan. Akses hanya lewat DocumentController yang mengecek
 * kepemilikan lebih dulu.
 */
class DocumentService
{
    /** Format yang diterima: PDF atau gambar. */
    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];

    /** Batas ukuran berkas dalam kilobyte. */
    public const MAX_KB = 5120;

    public const DISK = 'local';

    /**
     * Aturan validasi berkas unggahan.
     *
     * @return array<int, string>
     */
    public static function rules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::MIMES),
            'max:'.self::MAX_KB,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute): array
    {
        return [
            $attribute.'.required' => 'Dokumen tagihan wajib diunggah (PDF atau gambar).',
            $attribute.'.mimes' => 'Format dokumen harus PDF, JPG, PNG, WEBP, atau HEIC.',
            $attribute.'.max' => 'Ukuran dokumen maksimal 5 MB.',
        ];
    }

    /**
     * Simpan berkas dan tautkan ke model pemiliknya.
     * Dokumen lama dengan jenis yang sama otomatis digantikan.
     */
    public function attach(User $user, Model $owner, UploadedFile $file, DocumentKind $kind): Document
    {
        $this->detach($owner, $kind);

        $path = $file->storeAs(
            'documents/'.$user->getKey().'/'.$kind->value,
            Str::ulid().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            ['disk' => self::DISK]
        );

        /** @var Document $document */
        $document = $owner->morphMany(Document::class, 'documentable')->create([
            'user_id' => $user->getKey(),
            'kind' => $kind->value,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        return $document;
    }

    /**
     * Hapus dokumen milik sebuah model, sekaligus berkas fisiknya.
     */
    public function detach(Model $owner, ?DocumentKind $kind = null): void
    {
        $owner->morphMany(Document::class, 'documentable')
            ->when($kind, fn ($query) => $query->where('kind', $kind->value))
            ->get()
            ->each(fn (Document $document) => $this->delete($document));
    }

    public function delete(Document $document): void
    {
        Storage::disk($document->disk)->delete($document->path);

        $document->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function present(?Document $document): ?array
    {
        if (! $document instanceof Document) {
            return null;
        }

        return [
            'id' => $document->getKey(),
            'kind' => $document->kind->value,
            'kind_label' => $document->kind->label(),
            'name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'is_image' => $document->isImage(),
            'size_label' => $document->humanSize(),
            'url' => '/documents/'.$document->getKey(),
            'uploaded_at' => $document->created_at?->translatedFormat('d M Y H:i'),
        ];
    }
}
