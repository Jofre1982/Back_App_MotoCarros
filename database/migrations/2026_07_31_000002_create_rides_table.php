<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            // Quien pide el viaje. Se borra con la cuenta: un viaje sin
            // pasajero no es nada que se pueda consultar ni cobrar.
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();

            // El conductor llega después: el viaje nace sin asignar y son los
            // conductores quienes aceptan la solicitud disponible (historia
            // #18). Es `nullOnDelete` y no `cascade` porque borrar al conductor
            // no puede llevarse el viaje del pasajero, que ya ocurrió y tiene
            // un cobro asociado.
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->index();

            // Coordenadas del trayecto pedido. `decimal` y no `float`: la
            // posición de recogida es la que el conductor tiene que encontrar,
            // y siete decimales dan poco más de un centímetro de resolución.
            $table->decimal('origin_latitude', 9, 7);
            $table->decimal('origin_longitude', 10, 7);
            $table->decimal('destination_latitude', 9, 7);
            $table->decimal('destination_longitude', 10, 7);

            // Lo que el proveedor de mapas y el motor de tarifa dijeron al
            // solicitar el viaje. Se guarda con el viaje y no se recalcula al
            // consultarlo: el pasajero aceptó *este* número, y volver a pedirle
            // una ruta al proveedor por cada lectura se paga por consulta.
            $table->unsignedInteger('estimated_distance_meters');
            $table->unsignedInteger('estimated_duration_seconds');
            $table->string('currency', 3);
            // Entero en la unidad mínima de la moneda, nunca float: ver la nota
            // sobre dinero en .claude/STANDARDS.md.
            $table->unsignedInteger('estimated_fare');

            $table->timestamps();

            // "Un viaje activo por pasajero", garantizado por el motor y no por
            // la validación. El Form Request lo adelanta para poder responder
            // 422 en vez de 500, pero entre su consulta y el INSERT caben dos
            // solicitudes simultáneas —un doble toque en el móvil alcanza—, y
            // ahí lo único que queda en pie es el índice.
            //
            // Es una columna generada y no un índice parcial (`WHERE ...`)
            // porque MySQL no los tiene: así la misma definición vale en SQLite
            // y en MySQL. Vale `passenger_id` mientras el viaje está activo y
            // NULL cuando terminó, y los índices únicos ignoran los NULL en
            // ambos motores, así que un pasajero acumula viajes terminados sin
            // límite y solo puede tener uno vivo.
            //
            // La lista de estados se escribe literal en vez de leerla de
            // `App\Enums\RideStatus`: una migración ya corrida no se vuelve a
            // ejecutar, así que depender del enum haría que una base creada hoy
            // y otra creada después de renombrar un caso quedaran distintas.
            // `RideSchemaTest` recorre el enum contra esta tabla para que la
            // divergencia falle en la suite y no en producción.
            $table->unsignedBigInteger('active_passenger_id')
                ->nullable()
                ->storedAs("case when status in ('requested', 'accepted', 'in_progress') then passenger_id end");

            $table->unique('active_passenger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
