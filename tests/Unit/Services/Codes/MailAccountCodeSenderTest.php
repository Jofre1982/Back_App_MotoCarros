<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Codes;

use App\Models\User;
use App\Services\Codes\MailAccountCodeSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class MailAccountCodeSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_si_el_correo_falla_lo_registra_sin_romper_la_request(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));
        Log::spy();

        (new MailAccountCodeSender)->send(User::factory()->create(), '123456', 'verificar tu cuenta', 15);

        Log::shouldHaveReceived('error')->once();
    }
}
