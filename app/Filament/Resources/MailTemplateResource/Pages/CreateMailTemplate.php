<?php

declare(strict_types=1);

namespace App\Filament\Resources\MailTemplateResource\Pages;

use App\Filament\Resources\MailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMailTemplate extends CreateRecord
{
    protected static string $resource = MailTemplateResource::class;
}
