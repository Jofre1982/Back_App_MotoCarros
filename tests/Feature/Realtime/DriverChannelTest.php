<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Broadcasting\DriverChannel;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorización del canal privado `driver.{driverId}` (issue #5).
 *
 * Por este canal viajan las solicitudes de viaje cercanas: quien lo escucha ve
 * en vivo dónde se está pidiendo transporte. Cada caso de acá es una forma de
 * colarse en él.
 */
class DriverChannelTest extends TestCase
{
    use RefreshDatabase;

    private DriverChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new DriverChannel;
    }

    public function test_un_conductor_operativo_entra_a_su_propio_canal(): void
    {
        $conductor = $this->conductorOperativo();

        $this->assertTrue($this->channel->join($conductor, (string) $conductor->getKey()));
    }

    public function test_un_conductor_no_entra_al_canal_de_otro_conductor(): void
    {
        $conductor = $this->conductorOperativo();
        $otro = $this->conductorOperativo();

        $this->assertFalse($this->channel->join($conductor, (string) $otro->getKey()));
    }

    /**
     * Un conductor sin perfil todavía no puede recibir viajes (decisión de #1
     * en .claude/STANDARDS.md), así que tampoco tiene por qué ver las
     * solicitudes que circulan.
     */
    public function test_un_conductor_sin_perfil_no_entra_a_su_canal(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->assertFalse($this->channel->join($conductor, (string) $conductor->getKey()));
    }

    public function test_un_pasajero_no_entra_al_canal_de_conductor_con_su_propio_id(): void
    {
        $pasajero = User::factory()->create();

        $this->assertFalse($this->channel->join($pasajero, (string) $pasajero->getKey()));
    }

    /**
     * El nombre del canal lo elige el cliente, así que no basta con que el id
     * se parezca al propio: tiene que ser idéntico.
     */
    public function test_un_id_de_canal_que_no_es_exactamente_el_propio_no_entra(): void
    {
        $conductor = $this->conductorOperativo();

        $this->assertFalse($this->channel->join($conductor, $conductor->getKey().'abc'));
        $this->assertFalse($this->channel->join($conductor, '0'.$conductor->getKey()));
        $this->assertFalse($this->channel->join($conductor, ''));
    }

    private function conductorOperativo(): User
    {
        $conductor = User::factory()->driver()->create();

        DriverProfile::factory()->for($conductor)->create();

        return $conductor;
    }
}
