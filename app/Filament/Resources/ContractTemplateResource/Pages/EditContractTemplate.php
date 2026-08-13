<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use App\Models\ContractTemplate;
use App\Support\AgreementHtml;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContractTemplate extends EditRecord
{
    protected static string $resource = ContractTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['content']) && is_string($data['content'])) {
            $data['content'] = AgreementHtml::plainTextToEditorHtml($data['content']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('content', $data)) {
            $data['content'] = AgreementHtml::normalizeContent($data['content']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_version')
                ->label('Utwórz nową wersję')
                ->icon('heroicon-o-document-duplicate')
                ->form([
                    \Filament\Forms\Components\Textarea::make('version_notes')
                        ->label('Notatka do nowej wersji')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    /** @var ContractTemplate $record */
                    $record = $this->getRecord();
                    $clone = $record->createNewVersion($data['version_notes'] ?? null);

                    Notification::make()
                        ->title('Utworzono wersję v'.$clone->version)
                        ->body('Poprzednie wersje w tej linii zostały dezaktywowane.')
                        ->success()
                        ->send();

                    $this->redirect(ContractTemplateResource::getUrl('edit', ['record' => $clone]));
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
