<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // "Un viaje activo por conductor", el mismo mecanismo que
            // `active_passenger_id` y por el mismo motivo: el Form Request de
            // aceptar (historia #18) adelanta la regla para responder 422 en
            // vez de 500, pero entre esa consulta y el UPDATE caben dos
            // aceptaciones simultáneas del mismo conductor sobre dos viajes
            // distintos, y ahí lo único que queda en pie es el índice.
            //
            // Vale `driver_id` mientras el viaje está activo y NULL en
            // cualquier otro caso (incluido mientras nadie lo ha aceptado
            // todavía), así que un conductor acumula viajes terminados sin
            // límite y solo puede tener uno vivo a la vez. La lista de estados
            // se escribe literal y no se lee del enum, por el mismo motivo que
            // `active_passenger_id`: una migración ya corrida no vuelve a
            // ejecutarse. `RideSchemaTest` recorre el enum contra esta columna
            // para que la divergencia falle en la suite y no en producción.
            //
            // VIRTUAL y no STORED (a diferencia de `active_passenger_id`, que
            // sí puede serlo): esta columna se agrega con `ALTER TABLE` sobre
            // una tabla `rides` que ya puede tener filas, y SQLite no admite
            // añadir una columna generada STORED a una tabla con datos
            // (`cannot add a STORED column`). `active_passenger_id` no tiene
            // este problema porque se declara dentro del `CREATE TABLE`
            // original. VIRTUAL sí se puede agregar con datos existentes y
            // sigue admitiendo índice único tanto en SQLite como en MySQL.
            $table->unsignedBigInteger('active_driver_id')
                ->nullable()
                ->virtualAs("case when status in ('requested', 'accepted', 'in_progress') then driver_id end");

            $table->unique('active_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropUnique(['active_driver_id']);
            $table->dropColumn('active_driver_id');
        });
    }
};
