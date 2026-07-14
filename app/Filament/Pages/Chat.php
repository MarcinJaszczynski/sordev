<?php

namespace App\Filament\Pages;

use App\Support\FilamentNavigation;
use Filament\Pages\Page;

class Chat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Czat';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONTACTS;

    protected static string $view = 'filament.pages.chat';

    protected static ?int $navigationSort = 3;

    public ?int $conversationId = null;

    public function mount(?int $conversation = null): void
    {
        $this->conversationId = $conversation;
    }

    public function getTitle(): string
    {
        return 'Czat';
    }
}
