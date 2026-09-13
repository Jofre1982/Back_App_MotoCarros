<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('errands', function (Blueprint $table) {
            $table->id();

            // Quien pide el mandado. Se borra con la cuenta, mismo criterio
            // que `rides.passenger_id`: un mandado sin pasajero no es nada
            // que se pueda consultar.
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();

            // El conductor llega después, al aceptar (historia #92): no hay
            // matching automático ni aviso push, el conductor lo busca en
            // `GET /errands`. `nullOnDelete` por el mismo motivo que
            // `rides.driver_id`: borrar al conductor no puede llevarse el
            // mandado del pasajero.
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->index();

            // Qué hay que recoger. Descripción libre, no un catálogo: cada
            // mandado es distinto (ver #92, fuera de alcance un catálogo de
            // tipos de mandado).
            $table->text('description');

            // Foto opcional del ítem a recoger (historia #92: no bloquea el
            // pedido). Ruta en el disco `local` (privado), mismo criterio que
            // `driver_documents.path`; nullable porque el pasajero puede no
            // adjuntarla.
            $table->string('photo_path')->nullable();

            // Punto de recogida libre (historia #92): a diferencia del
            // destino, acá no hay catálogo — el conductor va exactamente
            // adonde se le indique, igual que el origen de un viaje.
            $table->decimal('origin_latitude', 9, 7);
            $table->decimal('origin_longitude', 10, 7);

            // La entrega sí es un sitio del catálogo (historia #85):
            // `restrictOnDelete` porque un sitio con mandados asociados no
            // se puede borrar sin dejar historial huérfano, mismo criterio
            // que `rides.destination_site_id`.
            $table->foreignId('destination_site_id')->constrained('sites')->restrictOnDelete();

            // Sin tarifa fija (historia #92): el precio lo negocian pasajero
            // y conductor por fuera, y el conductor lo registra al aceptar.
            // Nullable hasta ese momento; entero en la unidad mínima de la
            // moneda, nunca float (ver .claude/STANDARDS.md).
            $table->unsignedInteger('agreed_price')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errands');
    }
};
