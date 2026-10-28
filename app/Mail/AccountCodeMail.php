<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo con un código de un solo uso — ver `MailAccountCodeSender`.
 *
 * Sin vista Blade a propósito (este backend no sirve vistas, ver
 * `.claude/CLAUDE.md`): el cuerpo es texto plano armado acá.
 */
class AccountCodeMail extends Mailable
{
    public function __construct(
        public readonly string $recipientName,
        public readonly string $code,
        public readonly string $purpose,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tu código de MotoYa: {$this->code}");
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body());
    }

    private function body(): string
    {
        $nombre = e($this->recipientName);
        $proposito = e($this->purpose);
        $codigo = e($this->code);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto; color: #22272b;">
              <h2 style="color: #e8590c;">MotoYa</h2>
              <p>Hola {$nombre},</p>
              <p>Este es tu código para {$proposito}:</p>
              <p style="font-size: 32px; font-weight: bold; letter-spacing: 6px; text-align: center; background: #fff0e6; padding: 16px; border-radius: 10px;">{$codigo}</p>
              <p>Vence en {$this->expiresInMinutes} minutos. Si no lo pediste tú, ignora este correo.</p>
            </div>
            HTML;
    }
}
