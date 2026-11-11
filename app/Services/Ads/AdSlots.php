<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Models\Ad;
use Illuminate\Support\Facades\Config;

/**
 * Cupos de anuncios: cuántos pueden salir el mismo día (`ads.max_active`).
 * Se mira el día más lleno del rango y no el total de anuncios que lo
 * tocan, para que uno de la primera semana y otro de la última no se
 * cuenten como si salieran juntos.
 */
final class AdSlots
{
    public static function max(): int
    {
        return Config::integer('ads.max_active');
    }

    /**
     * El mayor número de anuncios (sin pausar) que salen un mismo día entre
     * `$from` y `$to`, sin contar `$exceptId`.
     */
    public static function busiestDay(string $from, string $to, ?int $exceptId = null): int
    {
        $ads = Ad::query()
            ->where('is_paused', false)
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get(['id', 'starts_on', 'ends_on']);

        // El día más lleno siempre es el inicio del rango o el día en que
        // empieza alguno de los anuncios que se cruzan con él.
        $days = $ads->toBase()->map(fn (Ad $ad) => $ad->starts_on->toDateString())
            ->filter(fn (string $day) => $day > $from)
            ->push($from)
            ->unique();

        return (int) $days->map(fn (string $day) => $ads->filter(
            fn (Ad $ad) => $ad->starts_on->toDateString() <= $day && $ad->ends_on->toDateString() >= $day,
        )->count())->max();
    }
}
