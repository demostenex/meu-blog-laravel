<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BackupReport extends Mailable
{
    public function __construct(
        public readonly string $dbDumpPath,
        public readonly string $imagesZipPath,
        public readonly array $videoUrls,
        public readonly string $timestamp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔒 Backup do Blog — ' . now()->format('d/m/Y H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->dbDumpPath)
                ->as('banco_' . $this->timestamp . '.sql.gz')
                ->withMime('application/gzip'),
            Attachment::fromPath($this->imagesZipPath)
                ->as('imagens_' . $this->timestamp . '.zip')
                ->withMime('application/zip'),
        ];
    }
}
