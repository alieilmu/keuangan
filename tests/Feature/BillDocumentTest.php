<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Document;
use App\Models\User;
use App\Services\DefaultDataProvisioner;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DocumentService::DISK);

        $this->user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Listrik PLN',
            'amount' => 425_000,
            'due_date' => now()->addDays(5)->toDateString(),
            'remind_days_before' => 3,
            'document' => UploadedFile::fake()->create('tagihan-pln.pdf', 120, 'application/pdf'),
        ], $overrides);
    }

    public function test_tagihan_tidak_bisa_dibuat_tanpa_dokumen(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/bills', $this->payload(['document' => null]));

        $response->assertSessionHasErrors('document');
        $this->assertSame(0, Bill::query()->count());
    }

    public function test_format_dokumen_selain_pdf_atau_gambar_ditolak(): void
    {
        $this->actingAs($this->user)
            ->post('/bills', $this->payload([
                'document' => UploadedFile::fake()->create('tagihan.docx', 40),
            ]))
            ->assertSessionHasErrors('document');

        $this->assertSame(0, Bill::query()->count());
    }

    public function test_dokumen_lebih_dari_5mb_ditolak(): void
    {
        $this->actingAs($this->user)
            ->post('/bills', $this->payload([
                'document' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('document');
    }

    public function test_membuat_tagihan_dengan_dokumen_menyimpan_berkas(): void
    {
        $this->actingAs($this->user)->post('/bills', $this->payload())->assertRedirect();

        $bill = Bill::query()->sole();
        $document = $bill->documents()->sole();

        $this->assertSame('invoice', $document->kind->value);
        $this->assertSame('tagihan-pln.pdf', $document->original_name);
        Storage::disk(DocumentService::DISK)->assertExists($document->path);
    }

    public function test_gambar_juga_diterima_sebagai_dokumen_tagihan(): void
    {
        $this->actingAs($this->user)
            ->post('/bills', $this->payload([
                'document' => UploadedFile::fake()->image('foto-tagihan.jpg'),
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Bill::query()->sole()->documents()->sole()->isImage());
    }

    public function test_nota_pembayaran_menempel_saat_tagihan_dibayar(): void
    {
        $this->actingAs($this->user)->post('/bills', $this->payload());

        $bill = Bill::query()->sole();
        $account = $this->user->accounts()->firstOrFail();
        $account->forceFill(['opening_balance' => 1_000_000, 'balance' => 1_000_000])->save();

        $this->actingAs($this->user)
            ->post("/bills/{$bill->getKey()}/pay", [
                'account_id' => $account->getKey(),
                'receipt' => UploadedFile::fake()->image('struk.png'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $bill->refresh()->load('documents');

        $this->assertNotNull($bill->invoiceDocument(), 'dokumen tagihan tetap ada');
        $this->assertNotNull($bill->receiptDocument(), 'nota pembayaran tersimpan');
        $this->assertSame('struk.png', $bill->receiptDocument()->original_name);
    }

    public function test_membatalkan_pembayaran_menghapus_nota_tapi_menyimpan_dokumen_tagihan(): void
    {
        $this->actingAs($this->user)->post('/bills', $this->payload());

        $bill = Bill::query()->sole();
        $account = $this->user->accounts()->firstOrFail();

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $account->getKey(),
            'receipt' => UploadedFile::fake()->image('struk.png'),
        ]);

        $receiptPath = $bill->refresh()->load('documents')->receiptDocument()->path;

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/unpay")->assertRedirect();

        $bill->refresh()->load('documents');

        $this->assertNull($bill->receiptDocument());
        $this->assertNotNull($bill->invoiceDocument());
        Storage::disk(DocumentService::DISK)->assertMissing($receiptPath);
    }

    public function test_dokumen_hanya_bisa_diakses_pemiliknya(): void
    {
        $this->actingAs($this->user)->post('/bills', $this->payload());

        $document = Document::query()->sole();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get("/documents/{$document->getKey()}")->assertForbidden();
        $this->actingAs($this->user)->get("/documents/{$document->getKey()}")->assertOk();
    }

    public function test_menghapus_tagihan_ikut_menghapus_berkas_fisiknya(): void
    {
        $this->actingAs($this->user)->post('/bills', $this->payload());

        $bill = Bill::query()->sole();
        $path = $bill->load('documents')->invoiceDocument()->path;

        $this->actingAs($this->user)->delete("/bills/{$bill->getKey()}")->assertRedirect();

        $this->assertSame(0, Document::query()->count());
        Storage::disk(DocumentService::DISK)->assertMissing($path);
    }
}
