<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Akun Uji',
            'type' => AccountType::Bank->value,
            'opening_balance' => 0,
            'color' => '#10b981',
            'is_active' => true,
        ], $overrides);
    }

    public function test_nomor_rekening_wajib_untuk_akun_bank(): void
    {
        $this->actingAs($this->user)
            ->post('/accounts', $this->payload(['type' => AccountType::Bank->value]))
            ->assertSessionHasErrors('account_number');

        $this->assertSame(0, Account::query()->count());
    }

    public function test_nomor_rekening_wajib_untuk_e_wallet(): void
    {
        $this->actingAs($this->user)
            ->post('/accounts', $this->payload(['type' => AccountType::EWallet->value]))
            ->assertSessionHasErrors('account_number');
    }

    public function test_akun_tunai_tidak_memerlukan_nomor_rekening(): void
    {
        $this->actingAs($this->user)
            ->post('/accounts', $this->payload(['name' => 'Dompet Tunai', 'type' => AccountType::Cash->value]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Account::query()->sole()->account_number);
    }

    public function test_nomor_rekening_diabaikan_untuk_akun_tunai(): void
    {
        $this->actingAs($this->user)->post('/accounts', $this->payload([
            'name' => 'Dompet Tunai',
            'type' => AccountType::Cash->value,
            'account_number' => '1234567890',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Account::query()->sole()->account_number, 'akun fisik tidak menyimpan nomor rekening');
    }

    public function test_akun_bank_dengan_nomor_rekening_tersimpan(): void
    {
        $this->actingAs($this->user)->post('/accounts', $this->payload([
            'name' => 'BCA Utama',
            'type' => AccountType::Bank->value,
            'account_number' => '1234567890',
        ]))->assertSessionHasNoErrors();

        $account = Account::query()->sole();

        $this->assertSame('1234567890', $account->account_number);
        $this->assertTrue($account->requiresAccountNumber());
        $this->assertSame('BCA Utama - 1234567890', $account->displayName());
    }

    public function test_nomor_rekening_boleh_sama_antar_akun(): void
    {
        // Satu rekening bisa dicatat lebih dari sekali, mis. rekening bersama.
        foreach (['Mandiri Suami', 'Mandiri Istri'] as $name) {
            $this->actingAs($this->user)->post('/accounts', $this->payload([
                'name' => $name,
                'type' => AccountType::Bank->value,
                'account_number' => '1370011223344',
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Account::query()->where('account_number', '1370011223344')->count());
    }
}
