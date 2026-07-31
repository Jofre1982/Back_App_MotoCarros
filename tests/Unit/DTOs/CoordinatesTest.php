<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\Coordinates;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoordinatesTest extends TestCase
{
    public function test_accepts_a_valid_point(): void
    {
        $point = new Coordinates(10.3910, -75.4794);

        $this->assertSame(10.3910, $point->latitude);
        $this->assertSame(-75.4794, $point->longitude);
    }

    public function test_accepts_the_range_boundaries(): void
    {
        $point = new Coordinates(-90.0, 180.0);

        $this->assertSame(-90.0, $point->latitude);
        $this->assertSame(180.0, $point->longitude);
    }

    public function test_rejects_latitude_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitud fuera de rango');

        new Coordinates(90.1, 0.0);
    }

    public function test_rejects_longitude_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Longitud fuera de rango');

        new Coordinates(0.0, -180.1);
    }

    public function test_parses_the_lat_lng_string_format(): void
    {
        $point = Coordinates::fromString(' 10.3910 , -75.4794 ');

        $this->assertSame(10.3910, $point->latitude);
        $this->assertSame(-75.4794, $point->longitude);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedStrings(): array
    {
        return [
            'sin coma' => ['10.3910 -75.4794'],
            'un solo valor' => ['10.3910'],
            'tres valores' => ['10.3910,-75.4794,5'],
            'texto' => ['cartagena'],
            'vacío' => [''],
        ];
    }

    #[DataProvider('malformedStrings')]
    public function test_rejects_a_malformed_string(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Coordenada inválida');

        Coordinates::fromString($value);
    }

    public function test_rejects_a_string_whose_values_are_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitud fuera de rango');

        Coordinates::fromString('100,0');
    }
}
