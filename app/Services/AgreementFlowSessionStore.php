<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use Illuminate\Support\Facades\Session;

/**
 * Plan + zgody dla tokenu szablonu (bez tworzenia draftu umowy).
 */
final class AgreementFlowSessionStore
{
    public function keyFor(Contract|EventAgreement $template): string
    {
        $type = $template instanceof Contract ? 'contract' : 'agreement';

        return sprintf('agreement_flow.%s.%d', $type, (int) $template->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(Contract|EventAgreement $template): array
    {
        $raw = Session::get($this->keyFor($template), []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public function merge(Contract|EventAgreement $template, array $patch): array
    {
        $current = $this->get($template);
        $merged = array_replace_recursive($current, $patch);
        Session::put($this->keyFor($template), $merged);

        return $merged;
    }

    public function clear(Contract|EventAgreement $template): void
    {
        Session::forget($this->keyFor($template));
    }

    public function isPlanConfirmed(Contract|EventAgreement $template): bool
    {
        return filled(data_get($this->get($template), 'plan_confirmed_at'));
    }

    public function hasConsents(Contract|EventAgreement $template): bool
    {
        return (bool) data_get($this->get($template), 'consents.accepted', false);
    }
}
