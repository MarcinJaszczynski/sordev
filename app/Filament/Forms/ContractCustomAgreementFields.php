<?php

namespace App\Filament\Forms;

use App\Models\Contract;
use App\Models\EventAgreement;
use Filament\Forms;
use Filament\Forms\Get;

class ContractCustomAgreementFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function contentSchema(): array
    {
        return [
            Forms\Components\Select::make('payment_mode')
                ->label('Tryb wpłat')
                ->options(Contract::$customPaymentModes)
                ->default(Contract::CUSTOM_PAYMENT_TOTAL_LUMP)
                ->live()
                ->visible(fn (Get $get): bool => static::isCustomType($get('agreement_type')))
                ->required(fn (Get $get): bool => static::isCustomType($get('agreement_type')))
                ->helperText('Określa jak umowa własna synchronizuje wpłaty z rozliczeniem imprezy.'),

            Forms\Components\Select::make('body_edit_mode')
                ->label('Sposób przygotowania treści')
                ->options(static::bodyEditModeOptions())
                ->default(Contract::BODY_EDIT_TEMPLATE)
                ->live()
                ->visible(fn (Get $get): bool => static::isCustomType($get('agreement_type')))
                ->required(fn (Get $get): bool => static::isCustomType($get('agreement_type')))
                ->helperText('Umowa własna: wgraj gotowy PDF od kontrahenta albo wpisz treść ręcznie.'),

            Forms\Components\FileUpload::make('custom_agreement_document_path')
                ->label('Dokument umowy (PDF)')
                ->acceptedFileTypes(['application/pdf'])
                ->disk('public')
                ->directory('event-agreements/custom-documents')
                ->downloadable()
                ->openable()
                ->preserveFilenames()
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => static::isCustomType($get('agreement_type'))
                    && ($get('body_edit_mode') ?? Contract::BODY_EDIT_UPLOAD) === Contract::BODY_EDIT_UPLOAD)
                ->required(fn (Get $get): bool => static::isCustomType($get('agreement_type'))
                    && ($get('body_edit_mode') ?? Contract::BODY_EDIT_UPLOAD) === Contract::BODY_EDIT_UPLOAD),

            \FilamentTiptapEditor\TiptapEditor::make('agreement_body')
                ->label('Treść umowy')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => static::shouldShowAgreementBodyEditor($get('agreement_type'), $get('body_edit_mode')))
                ->required(fn (Get $get): bool => static::isCustomType($get('agreement_type'))
                    && $get('body_edit_mode') === Contract::BODY_EDIT_MANUAL),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bodyEditModeOptions(): array
    {
        return [
            Contract::BODY_EDIT_UPLOAD => 'Wgrany dokument PDF',
            Contract::BODY_EDIT_MANUAL => 'Ręczna edycja treści',
        ];
    }

    public static function isCustomType(mixed $agreementType): bool
    {
        return in_array($agreementType, [Contract::TYPE_CUSTOM, EventAgreement::TYPE_CUSTOM], true);
    }

    public static function shouldShowAgreementBodyEditor(mixed $agreementType, mixed $bodyEditMode): bool
    {
        if (static::isCustomType($agreementType)) {
            return $bodyEditMode === Contract::BODY_EDIT_MANUAL;
        }

        return $bodyEditMode !== Contract::BODY_EDIT_UPLOAD;
    }
}
