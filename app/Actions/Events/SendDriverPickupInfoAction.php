<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Mail\DriverPickupInfoMail;
use App\Models\Event;
use App\Services\Sms\SmsChannelInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Wysyła kierowcy (kontrahentowi) informację o podstawieniu — e-mail i/lub SMS.
 * Po sukcesie ustawia audit `driver_pickup_info_sent_*`.
 */
final class SendDriverPickupInfoAction
{
    public function __construct(
        private readonly SmsChannelInterface $sms,
    ) {}

    /**
     * @return array{email_sent: bool, sms_sent: bool}
     */
    public function __invoke(Event $event, string $messageHtml, bool $sendEmail = true, bool $sendSms = true): array
    {
        $event->loadMissing(['driverContractor', 'startPlace', 'bus']);

        $contractor = $event->driverContractor;
        if (! $contractor) {
            throw new InvalidArgumentException('Najpierw wybierz kierowcę (kontrahenta).');
        }

        $email = trim((string) ($contractor->email ?? ''));
        $phone = trim((string) ($contractor->phone ?: $event->driver_phone ?? ''));

        if (! $sendEmail && ! $sendSms) {
            throw new InvalidArgumentException('Wybierz co najmniej jeden kanał: e-mail lub SMS.');
        }

        $emailSent = false;
        $smsSent = false;

        if ($sendEmail) {
            if ($email === '') {
                throw new InvalidArgumentException('Kierowca nie ma adresu e-mail — uzupełnij kontrahenta albo wyślij tylko SMS.');
            }

            Mail::to($email)->send(new DriverPickupInfoMail($event, $messageHtml));
            $emailSent = true;
        }

        if ($sendSms) {
            if ($phone === '') {
                throw new InvalidArgumentException('Kierowca nie ma telefonu — uzupełnij kontrahenta albo wyślij tylko e-mail.');
            }

            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(Str::of($messageHtml)->replace(['<br>', '<br/>', '<br />', '</p>'], "\n")->toString())) ?? '');
            $plain = Str::limit($plain, 450, '…');
            $prefix = sprintf('[%s] ', $event->code ?: '#'.$event->id);
            $this->sms->send($phone, $prefix.$plain);
            $smsSent = true;
        }

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
            $event->update([
                'driver_pickup_info_sent_at' => now(),
                'driver_pickup_info_sent_by' => Auth::id(),
            ]);
        }

        return [
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
        ];
    }

    public static function defaultMessageHtml(Event $event): string
    {
        $event->loadMissing(['driverContractor', 'startPlace', 'bus', 'transportContractor']);

        $lines = [
            '<p>Dzień dobry'.(filled($event->driverContractor?->name) ? ', '.e($event->driverContractor->name) : '').',</p>',
            '<p>Poniżej informacje o podstawieniu na imprezę <strong>'.e($event->name ?: '—').'</strong>'
                .(filled($event->code) ? ' ('.e($event->code).')' : '').':</p>',
            '<ul>',
            '<li><strong>Data:</strong> '.e(optional($event->start_date)?->format('d.m.Y') ?? '—')
                .(filled($event->end_date) ? ' – '.e($event->end_date->format('d.m.Y')) : '').'</li>',
            '<li><strong>Miejsce wyjazdu:</strong> '.e($event->startPlace?->name ?? '—').'</li>',
            '<li><strong>Godzina podstawienia:</strong> '.e((string) ($event->substitution_time ?? '—')).'</li>',
            '<li><strong>Godzina odjazdu:</strong> '.e((string) ($event->departure_time ?? '—')).'</li>',
            '<li><strong>Godzina powrotu:</strong> '.e((string) ($event->return_time ?? '—')).'</li>',
            '<li><strong>Autokar:</strong> '.e($event->bus?->name ?? '—').'</li>',
            '<li><strong>Nr rejestracyjny:</strong> '.e((string) ($event->vehicle_registration ?? '—')).'</li>',
            '<li><strong>Firma:</strong> '.e($event->transportContractor?->name ?? $event->transport_company_name ?? '—').'</li>',
        ];

        if (filled($event->pickup_place_details)) {
            $lines[] = '<li><strong>Szczegóły miejsca:</strong> '.e(strip_tags((string) $event->pickup_place_details)).'</li>';
        }

        $lines[] = '</ul>';
        $lines[] = '<p>Pozdrawiamy,<br>Biuro</p>';

        return implode("\n", $lines);
    }
}
