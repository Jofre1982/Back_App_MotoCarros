<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Errands;

use App\Actions\Errands\CompleteErrandAction;
use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class CompleteErrandActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_el_mandado_completado_conservando_el_precio_acordado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create([
            'status' => ErrandStatus::Accepted,
            'driver_id' => $conductor->id,
            'agreed_price' => 15000,
        ]);

        $resultado = $this->app->make(CompleteErrandAction::class)->handle($mandado);

        $this->assertSame(ErrandStatus::Completed, $resultado->status);
        $this->assertSame(15000, $resultado->agreed_price);
        $this->assertNotNull($resultado->completed_at);
        $this->assertSame(ErrandStatus::Completed, $mandado->refresh()->status);
    }
}
