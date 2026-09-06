<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // El destino pasa de coordenadas libres a un sitio del catalogo
            // con precio fijo (historia #87): el pasajero ya no marca un
            // punto en el mapa, elige de la lista que administra el admin
            // (historia #85). Nullable porque `Schema::table` no puede darle
            // un valor por defecto util a las filas que ya existan en una
            // base de desarrollo (no hay datos reales todavia, la app no
            // esta en produccion) — la ausencia de un sitio en un viaje
            // nuevo la impide `CreateRideRequest`, no la columna.
            //
            // `restrictOnDelete`: un sitio con viajes asociados no se puede
            // borrar sin dejar historial huerfano.
            $table->foreignId('destination_site_id')
                ->nullable()
                ->after('driver_id')
                ->constrained('sites')
                ->restrictOnDelete();

            // Cuantos pasajeros va el viaje (1 a 3, la capacidad de un
            // motocarro): determina el cobro cuando el sitio cobra
            // "per_person" (ver SiteFare::pricing_unit), y de todos modos es
            // informacion util para el conductor sin importar el modo de
            // cobro.
            $table->unsignedTinyInteger('passenger_count')->default(1)->after('destination_site_id');

            // El trayecto ya no se calcula contra el proveedor de mapas: el
            // cobro sale del precio fijo del sitio, no de la distancia/tiempo
            // recorridos (ver CalculateFareAction, que este cambio deja de
            // usar en el flujo de viajes).
            $table->dropColumn([
                'destination_latitude',
                'destination_longitude',
                'estimated_distance_meters',
                'estimated_duration_seconds',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_site_id');
            $table->dropColumn('passenger_count');

            $table->decimal('destination_latitude', 9, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();
            $table->unsignedInteger('estimated_distance_meters')->nullable();
            $table->unsignedInteger('estimated_duration_seconds')->nullable();
        });
    }
};
