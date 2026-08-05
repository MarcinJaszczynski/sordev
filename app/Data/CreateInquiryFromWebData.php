<?php

declare(strict_types=1);

namespace App\Data;

readonly class CreateInquiryFromWebData
{
    public function __construct(
        public string $email,
        public string $telephone,
        public ?string $name = null,
        public ?string $message = null,
        public ?string $eventName = null,
        public ?string $eventUrl = null,
        public ?string $startPlaceName = null,
    ) {}
}
