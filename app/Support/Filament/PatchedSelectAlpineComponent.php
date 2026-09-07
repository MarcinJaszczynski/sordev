<?php

namespace App\Support\Filament;

use Filament\Support\Assets\AlpineComponent;

/**
 * Serwuje poprawiony select.js (bez pustych wierszy Choices) z osobnym cache-bust.
 */
class PatchedSelectAlpineComponent extends AlpineComponent
{
    public function getSrc(): string
    {
        return asset('js/sorsystem/select.js').'?v=sor-empty-fix-4';
    }
}
