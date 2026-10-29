<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un celular colombiano escrito sin indicativo (10 dígitos que empiezan en
 * 3) se guarda con +57. Sin esto quedaba `+3108838480`, que WhatsApp lee
 * como un número de Holanda (+31) y no abre el chat.
 */
class ColombianPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(string $celular, string $email): void
    {
        $this->postJson('/api/v1/auth/register/passenger', [
            'name' => 'Ana',
            'email' => $email,
            'phone' => $celular,
            'password' => 'motoya2026',
        ])->assertCreated();
    }

    public function test_sin_indicativo_se_guarda_con_57(): void
    {
        $this->registrar('3108838480', 'a@example.com');
        $this->registrar('+3121234567', 'b@example.com');
        $this->registrar('310 123 4567', 'c@example.com');

        $this->assertDatabaseHas('users', ['email' => 'a@example.com', 'phone' => '+573108838480']);
        $this->assertDatabaseHas('users', ['email' => 'b@example.com', 'phone' => '+573121234567']);
        $this->assertDatabaseHas('users', ['email' => 'c@example.com', 'phone' => '+573101234567']);
    }

    public function test_con_indicativo_queda_igual(): void
    {
        $this->registrar('+573108838480', 'a@example.com');
        $this->registrar('573151234567', 'b@example.com');

        $this->assertDatabaseHas('users', ['email' => 'a@example.com', 'phone' => '+573108838480']);
        $this->assertDatabaseHas('users', ['email' => 'b@example.com', 'phone' => '+573151234567']);
    }

    public function test_el_mismo_celular_con_y_sin_57_choca(): void
    {
        $this->registrar('3108838480', 'a@example.com');

        $this->postJson('/api/v1/auth/register/passenger', [
            'name' => 'Otra',
            'email' => 'b@example.com',
            'phone' => '+573108838480',
            'password' => 'motoya2026',
        ])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_la_migracion_corrige_los_celulares_viejos(): void
    {
        $viejo = User::factory()->create(['phone' => '+3108838480']);
        $bien = User::factory()->create(['phone' => '+573001112233']);

        (require database_path('migrations/2026_10_29_000003_add_colombia_code_to_phones.php'))->up();

        $this->assertSame('+573108838480', $viejo->refresh()->phone);
        $this->assertSame('+573001112233', $bien->refresh()->phone);
    }
}
