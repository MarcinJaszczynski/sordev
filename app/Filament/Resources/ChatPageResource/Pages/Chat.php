<?php

namespace App\Filament\Resources\ChatPageResource\Pages;

use App\Filament\Resources\ChatPageResource;
use Filament\Resources\Pages\Page;

class Chat extends Page
{
    protected static string $resource = ChatPageResource::class;

    protected static string $view = 'filament.resources.chat-page-resource.pages.chat';
}
