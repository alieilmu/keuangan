<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\DefaultDataProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $body): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($path, $body);

        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }

    public function test_baris_valid_diimpor_dan_saldo_dihitung_ulang(): void
    {
        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $account = $user->accounts()->where('name', 'Dompet Tunai')->firstOrFail();
        $account->forceFill(['opening_balance' => 1_000_000, 'balance' => 1_000_000])->save();

        $file = $this->csv(<<<'CSV'
        tanggal,tipe,kategori,akun,nominal,keterangan
        2026-03-01,expense,Makanan,Dompet Tunai,"1.250.000",Belanja bulanan
        01/03/2026,pemasukan,Gaji,Dompet Tunai,2000000,Gaji
        CSV);

        $this->actingAs($user)->post('/transactions/import', ['file' => $file])->assertRedirect();

        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame('1750000.00', $account->fresh()->balance);
        $this->assertSame('2026-03-01', Transaction::query()->first()->transaction_date->toDateString());
    }

    public function test_baris_tidak_valid_dilewati_dan_dilaporkan(): void
    {
        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $file = $this->csv(<<<'CSV'
        tanggal,tipe,kategori,akun,nominal,keterangan
        2026-03-01,expense,Makanan,Dompet Tunai,50000,Valid
        2026-03-99,expense,Makanan,Dompet Tunai,50000,Tanggal ngawur
        2026-03-02,transfer,Makanan,Dompet Tunai,50000,Tipe ngawur
        2026-03-03,expense,Makanan,Akun Ghaib,50000,Akun tidak ada
        2026-03-04,expense,Makanan,Dompet Tunai,-100,Nominal negatif
        CSV);

        $response = $this->actingAs($user)->post('/transactions/import', ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertCount(4, session('import_failures'));
    }

    public function test_kategori_baru_dibuat_otomatis_mengikuti_tipe_baris(): void
    {
        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $file = $this->csv(<<<'CSV'
        tanggal,tipe,kategori,akun,nominal,keterangan
        2026-03-01,expense,Kopi Susu,Dompet Tunai,25000,Ngopi
        CSV);

        $this->actingAs($user)->post('/transactions/import', ['file' => $file]);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Kopi Susu',
            'type' => 'expense',
        ]);
    }

    public function test_template_bisa_diunduh(): void
    {
        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $this->actingAs($user)
            ->get('/transactions/template')
            ->assertOk()
            ->assertDownload('template-import-transaksi.xlsx');
    }
}
