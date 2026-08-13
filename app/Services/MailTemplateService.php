<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailTemplate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

final class MailTemplateService
{
    public function ensureDefaults(): void
    {
        if (! Schema::hasTable('mail_templates')) {
            return;
        }

        MailTemplate::query()->firstOrCreate(
            ['key' => MailTemplate::KEY_PAYMENT_REMINDER],
            [
                'name' => 'Przypomnienie o płatności',
                'subject' => 'Przypomnienie: zaległość {{ event_code }}',
                'body_html' => '<p>Dzień dobry {{ $recipient_name }},</p><p>Przypominamy o zaległej płatności <strong>{{ $remaining_pln }} PLN</strong> za imprezę {{ $event_name }} ({{ $event_code }}).</p>@if($portal_url)<p><a href="{{ $portal_url }}">Przejdź do portalu</a></p>@endif',
                'placeholders' => ['recipient_name', 'event_name', 'event_code', 'remaining_pln', 'portal_url'],
                'is_active' => true,
            ]
        );

        MailTemplate::query()->firstOrCreate(
            ['key' => MailTemplate::KEY_STATUS_CHANGED],
            [
                'name' => 'Zmiana statusu imprezy',
                'subject' => '{{ event_code }}: status {{ new_status }}',
                'body_html' => '<p>Impreza {{ $event_name }} zmieniła status z {{ $previous_status }} na <strong>{{ $new_status }}</strong>.</p>',
                'placeholders' => ['event_name', 'event_code', 'previous_status', 'new_status'],
                'is_active' => true,
            ]
        );

        MailTemplate::query()->firstOrCreate(
            ['key' => MailTemplate::KEY_EVENT_BULK_NOTICE],
            [
                'name' => 'Komunikat masowy (impreza)',
                'subject' => '{{ event_code }}: informacja organizacyjna',
                'body_html' => '<p>Dzień dobry {{ $recipient_name }},</p><p>Informacja dotycząca imprezy <strong>{{ $event_name }}</strong> ({{ $event_code }}).</p><p>Saldo do zapłaty (jeśli dotyczy): <strong>{{ $remaining_pln }} PLN</strong>.</p><p>W razie pytań prosimy o kontakt z biurem.</p>',
                'placeholders' => ['recipient_name', 'event_name', 'event_code', 'remaining_pln', 'segment'],
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: string, body_html: string}|null
     */
    public function render(string $key, array $data): ?array
    {
        if (! Schema::hasTable('mail_templates')) {
            return null;
        }

        $this->ensureDefaults();

        $template = MailTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        $subject = $this->interpolate($template->subject, $data);

        try {
            $body = Blade::render($template->body_html, $data);
        } catch (\Throwable) {
            return null;
        }

        return [
            'subject' => $subject,
            'body_html' => $body instanceof HtmlString ? $body->toHtml() : (string) $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function interpolate(string $text, array $data): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $m) use ($data): string {
            $key = $m[1];

            return isset($data[$key]) ? (string) $data[$key] : $m[0];
        }, $text);
    }
}
