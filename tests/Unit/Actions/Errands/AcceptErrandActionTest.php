<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Errands;

use App\Actions\Errands\AcceptErrandAction;
use App\Enums\ErrandStatus;
use App\Exceptions\Errands\ErrandNoLongerAvailableException;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class AcceptErrandActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_asigna_el_conductor_y_el_precio_negociado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $resultado = $this->app->make(AcceptErrandAction::class)->handle($mandado, $conductor, 15000);

        $this->assertSame(ErrandStatus::Accepted, $resultado->status);
        $this->assertSame($conductor->getKey(), $resultado->driver_id);
        $this->assertSame(15000, $resultado->agreed_price);
        $this->assertSame(ErrandStatus::Accepted, $mandado->refresh()->status);
    }

    public function test_rechaza_un_mandado_que_ya_no_esta_requested(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Accepted]);

        $this->expectException(ErrandNoLongerAvailableException::class);

        try {
            $this->app->make(AcceptErrandAction::class)->handle($mandado, $conductor, 15000);
        } finally {
            $this->assertNull($mandado->refresh()->driver_id);
        }
    }
}
