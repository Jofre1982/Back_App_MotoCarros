<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverDocument>
 */
class DriverDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'type' => fake()->randomElement(DocumentType::cases()),
            'path' => 'driver-documents/fake/'.fake()->uuid().'.jpg',
            'status' => DocumentStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Foto ilegible'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }
}
