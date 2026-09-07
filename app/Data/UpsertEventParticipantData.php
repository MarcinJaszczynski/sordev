<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;
use App\Models\EventParticipant;
use Carbon\CarbonInterface;

readonly class UpsertEventParticipantData
{
    /**
     * @param  array<string, bool>|null  $consentFlags
     */
    public function __construct(
        public Event $event,
        public ?EventParticipant $participant = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $gender = null,
        public CarbonInterface|string|null $birthDate = null,
        public ?string $pesel = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $bookingReference = null,
        public ?string $diet = null,
        public bool $parentConsent = false,
        public ?array $consentFlags = null,
        public bool $ensurePayment = true,
        public ?float $dueAmountPln = null,
        public ?float $firstPaymentAmountPln = null,
        public CarbonInterface|string|null $firstPaymentPaidAt = null,
        public ?string $firstPaymentMethod = null,
        public string $source = EventParticipant::SOURCE_MANUAL,
    ) {}
}
