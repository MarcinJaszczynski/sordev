<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Chat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Czat';
    protected static ?string $navigationGroup = 'Komunikacja';
    protected static string $view = 'filament.pages.chat';
    protected static ?int $navigationSort = 1;

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
