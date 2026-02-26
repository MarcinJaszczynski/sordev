<?php

namespace App\Filament\Resources\ChatResource\Pages;

use App\Filament\Resources\ChatResource;
use Filament\Resources\Pages\Page;

class ChatPage extends Page
{
    protected static string $resource = ChatResource::class;

    protected static string $view = 'filament.resources.chat-resource.pages.chat-page';
}
