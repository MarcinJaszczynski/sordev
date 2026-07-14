<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasCustomAgreementContent
{
    public function isCustom(): bool
    {
        return $this->agreement_type === static::TYPE_CUSTOM;
    }

    public function usesUploadedAgreementDocument(): bool
    {
        return filled($this->custom_agreement_document_path)
            && $this->body_edit_mode === static::BODY_EDIT_UPLOAD;
    }

    public function shouldAutoGenerateAgreementBody(): bool
    {
        if ($this->usesUploadedAgreementDocument()) {
            return false;
        }

        if ($this->body_edit_mode === static::BODY_EDIT_MANUAL) {
            return false;
        }

        if ($this->isCustom()) {
            return false;
        }

        return $this->contractTemplate !== null || $this->body_edit_mode === static::BODY_EDIT_TEMPLATE;
    }

    public function hasRenderableAgreementContent(): bool
    {
        return $this->usesUploadedAgreementDocument() || filled($this->agreement_body);
    }

    public function getCustomAgreementDocumentUrlAttribute(): ?string
    {
        if (blank($this->custom_agreement_document_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->custom_agreement_document_path);
    }

    public function resolveAgreementPdfBytes(): ?string
    {
        if ($this->usesUploadedAgreementDocument()) {
            $absolutePath = Storage::disk('public')->path($this->custom_agreement_document_path);

            return is_file($absolutePath) ? (string) file_get_contents($absolutePath) : null;
        }

        return null;
    }
}
