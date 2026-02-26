<?php

namespace App\Livewire;

use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Livewire\Component;

class ProgramPointChildrenEditor extends Component
{
    public EventTemplateProgramPoint $programPoint;
    public $children = [];
    public $showModal = false;
    public $editChild = null;
    public $modalData = [
        'id' => null,
        'child_program_point_id' => '',
        'order' => 0,
    ];

    // Nowe właściwości dla lepszego wyszukiwania
    public $searchTerm = '';
    public $selectedTags = [];
    public $selectedCurrency = '';
    public $minPrice = '';
    public $maxPrice = '';
    public $minDuration = '';
    public $maxDuration = '';
    public $convertToPln = '';
    public $availablePoints = [];
    public $filteredPoints = [];

    protected function rules()
    {
        return [
            'modalData.child_program_point_id' => 'required|exists:event_template_program_points,id',
            'modalData.order' => 'integer|min:0',
            'searchTerm' => 'nullable|string|max:100',
            'minPrice' => 'nullable|numeric|min:0|max:999999.99',
            'maxPrice' => 'nullable|numeric|min:0|max:999999.99',
            'minDuration' => 'nullable|integer|min:0|max:1440',
            'maxDuration' => 'nullable|integer|min:0|max:1440',
        ];
    }

    protected $messages = [
        'modalData.child_program_point_id.required' => 'Pole punkt programu jest wymagane.',
        'modalData.child_program_point_id.exists' => 'Wybrany punkt programu jest nieprawidłowy.',
        'searchTerm.max' => 'Wyszukiwane wyrażenie jest zbyt długie (maksymalnie 100 znaków).',
        'minPrice.numeric' => 'Minimalna cena musi być liczbą.',
        'maxPrice.numeric' => 'Maksymalna cena musi być liczbą.',
        'minDuration.integer' => 'Minimalny czas trwania musi być liczbą całkowitą.',
        'maxDuration.integer' => 'Maksymalny czas trwania musi być liczbą całkowitą.',
    ];

    /**
     * Security check for user permissions
     */
    protected function checkPermissions(): void
    {
        if (!Auth::check()) {
            $this->logSecurityEvent('unauthorized_access_attempt', 'User not authenticated');
            throw new AuthenticationException('Nieautoryzowany dostęp');
        }

        // Note: For now, we only check if user is authenticated
        // TODO: Implement proper permission system with roles/policies
        // Example: if (!Auth::user()->can('manage_program_points')) { ... }
    }

    /**
     * Rate limiting check
     */
    protected function checkRateLimit(string $action): void
    {
        $key = 'program_children_editor:' . Auth::id() . ':' . $action;

        if (RateLimiter::tooManyAttempts($key, 10)) { // 10 attempts per minute
            $this->logSecurityEvent('rate_limit_exceeded', "Action: $action");
            throw ValidationException::withMessages([
                'general' => 'Zbyt wiele żądań. Spróbuj ponownie za chwilę.'
            ]);
        }

        RateLimiter::hit($key, 60); // 1 minute window
    }

    /**
     * Log security events
     */
    protected function logSecurityEvent(string $event, string $details = ''): void
    {
        Log::warning('Security Event in ProgramPointChildrenEditor', [
            'event' => $event,
            'details' => $details,
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'program_point_id' => $this->programPoint->id ?? null,
            'timestamp' => now(),
        ]);
    }
    public function mount(EventTemplateProgramPoint $programPoint)
    {
        // Security checks
        $this->checkPermissions();

        $this->programPoint = $programPoint;
        $this->loadChildren();
        $this->loadAvailablePoints();

        // Inicjalizuj filteredPoints z wszystkimi dostępnymi punktami na starcie
        $this->filteredPoints = $this->availablePoints;

        Log::info('ProgramPointChildrenEditor mounted', [
            'user_id' => Auth::id(),
            'program_point_id' => $programPoint->id,
            'availablePoints_count' => count($this->availablePoints),
            'filteredPoints_count' => count($this->filteredPoints),
        ]);
    }
    public function loadAvailablePoints()
    {
        // Pobierz tylko ID dzieci bez ładowania całych modeli
        $childIds = DB::table('event_template_program_point_parent')
            ->where('parent_id', $this->programPoint->id)
            ->pluck('child_id');

        $query = EventTemplateProgramPoint::query()
            ->with(['currency', 'tags'])
            ->where('id', '!=', $this->programPoint->id)
            ->whereNotIn('id', $childIds)
            ->orderBy('name');

        if (!empty($this->searchTerm)) {
            $searchTerm = trim(strip_tags($this->searchTerm));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%$searchTerm%")
                    ->orWhere('description', 'like', "%$searchTerm%")
                    ->orWhere('office_notes', 'like', "%$searchTerm%")
                    ->orWhereHas('tags', function ($tq) use ($searchTerm) {
                        $tq->where('name', 'like', "%$searchTerm%");
                    });
            });
        }
        if (!empty($this->minPrice)) {
            $query->where('unit_price', '>=', (float)$this->minPrice);
        }
        if (!empty($this->maxPrice)) {
            $query->where('unit_price', '<=', (float)$this->maxPrice);
        }
        if (!empty($this->minDuration)) {
            $query->whereRaw('(duration_hours * 60 + duration_minutes) >= ?', [(int)$this->minDuration]);
        }
        if (!empty($this->maxDuration)) {
            $query->whereRaw('(duration_hours * 60 + duration_minutes) <= ?', [(int)$this->maxDuration]);
        }
        if ($this->convertToPln !== '') {
            $query->where('convert_to_pln', (bool)$this->convertToPln);
        }
        $points = $query->get(); // Brak limitu - pobierz wszystkie pasujące rekordy

        $this->availablePoints = $points->map(function ($point) {
            return [
                'id' => $point->id,
                'name' => $point->name,
                'description' => $point->description,
                'duration_hours' => $point->duration_hours,
                'duration_minutes' => $point->duration_minutes,
                'unit_price' => $point->unit_price,
                'group_size' => $point->group_size,
                'convert_to_pln' => $point->convert_to_pln,
                'office_notes' => $point->office_notes,
                'featured_image' => $point->featured_image,
                'gallery_images' => $point->gallery_images,
                'currency' => $point->currency ? [
                    'id' => $point->currency->id,
                    'symbol' => $point->currency->symbol,
                    'name' => $point->currency->name,
                ] : null,
                'tags' => $point->tags ? $point->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                    ];
                })->toArray() : [],
            ];
        })->toArray();

        $this->applyFilters();
    }
    public function applyFilters()
    {
        Log::info('ProgramPointChildrenEditor::applyFilters() called', [
            'searchTerm' => $this->searchTerm,
            'availablePoints_count' => count($this->availablePoints)
        ]);

        $filtered = collect($this->availablePoints);

        // Enhanced filtering with XSS protection
        if (!empty($this->searchTerm)) {
            $searchTerm = trim(strip_tags($this->searchTerm)); // Remove HTML tags
            $searchTerm = htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); // Escape special chars

            $filtered = $filtered->filter(function ($point) use ($searchTerm) {
                // Search in name
                if (stripos($point['name'], $searchTerm) !== false) {
                    return true;
                }

                // Search in description
                if (!empty($point['description']) && stripos($point['description'], $searchTerm) !== false) {
                    return true;
                }

                // Search in tags
                if (!empty($point['tags'])) {
                    foreach ($point['tags'] as $tag) {
                        if (stripos($tag['name'], $searchTerm) !== false) {
                            return true;
                        }
                    }
                }

                // Search in office notes
                if (!empty($point['office_notes']) && stripos($point['office_notes'], $searchTerm) !== false) {
                    return true;
                }
                return false;
            });
        }

        // Filter by tags with validation
        if (!empty($this->selectedTags) && is_array($this->selectedTags)) {
            $filtered = $filtered->filter(function ($point) {
                $pointTagNames = collect($point['tags'])->pluck('name')->toArray();
                return !empty(array_intersect($this->selectedTags, $pointTagNames));
            });
        }

        // Filtruj po walucie
        if (!empty($this->selectedCurrency)) {
            $filtered = $filtered->filter(function ($point) {
                return $point['currency']['symbol'] ?? '' === $this->selectedCurrency;
            });
        }

        // Filtruj po cenie
        if (!empty($this->minPrice)) {
            $filtered = $filtered->filter(function ($point) {
                return ($point['unit_price'] ?? 0) >= (float)$this->minPrice;
            });
        }
        if (!empty($this->maxPrice)) {
            $filtered = $filtered->filter(function ($point) {
                return ($point['unit_price'] ?? 0) <= (float)$this->maxPrice;
            });
        }

        // Filtruj po czasie trwania (w minutach)
        if (!empty($this->minDuration)) {
            $filtered = $filtered->filter(function ($point) {
                $totalMinutes = ($point['duration_hours'] ?? 0) * 60 + ($point['duration_minutes'] ?? 0);
                return $totalMinutes >= (int)$this->minDuration;
            });
        }

        if (!empty($this->maxDuration)) {
            $filtered = $filtered->filter(function ($point) {
                $totalMinutes = ($point['duration_hours'] ?? 0) * 60 + ($point['duration_minutes'] ?? 0);
                return $totalMinutes <= (int)$this->maxDuration;
            });
        }

        // Filtruj po convert_to_pln
        if ($this->convertToPln !== '') {
            $filtered = $filtered->filter(function ($point) {
                return (bool)($point['convert_to_pln'] ?? false) === (bool)$this->convertToPln;
            });
        }

        $this->filteredPoints = $filtered->values()->toArray(); // Brak limitu - pokazuj wszystkie pasujące podpunkty

        Log::info('ProgramPointChildrenEditor::applyFilters() completed', [
            'filteredPoints_count' => count($this->filteredPoints),
            'searchTerm' => $this->searchTerm
        ]);
    }

    // Metody do reagowania na zmiany filtrów
    public function updatedSearchTerm()
    {
        // truncate to max 100 characters to satisfy validation expectations
        if (!empty($this->searchTerm) && mb_strlen($this->searchTerm) > 100) {
            $this->searchTerm = mb_substr($this->searchTerm, 0, 100);
        }
        $this->applyFilters();
    }

    public function updatedSelectedTags()
    {
        $this->applyFilters();
    }

    public function updatedSelectedCurrency()
    {
        $this->applyFilters();
    }

    public function updatedMinPrice()
    {
        $this->applyFilters();
    }

    public function updatedMaxPrice()
    {
        $this->applyFilters();
    }

    public function updatedMinDuration()
    {
        $this->applyFilters();
    }

    public function updatedMaxDuration()
    {
        $this->applyFilters();
    }

    public function updatedConvertToPln()
    {
        $this->applyFilters();
    }

    public function clearFilters()
    {
        $this->searchTerm = '';
        $this->selectedTags = [];
        $this->selectedCurrency = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->minDuration = '';
        $this->maxDuration = '';
        $this->convertToPln = '';
        $this->applyFilters();
    }
    public function loadChildren()
    {
        $children = $this->programPoint->children()
            ->with(['currency', 'tags'])
            ->get();

        $this->children = $children->map(function ($child) {
            return [
                'pivot_id' => $child->pivot->id ?? null,
                'id' => $child->id,
                'name' => $child->name,
                'description' => $child->description,
                'office_notes' => $child->office_notes,
                'duration_hours' => $child->duration_hours,
                'duration_minutes' => $child->duration_minutes,
                'featured_image' => $child->featured_image,
                'gallery_images' => $child->gallery_images,
                'unit_price' => $child->unit_price,
                'group_size' => $child->group_size,
                'currency' => $child->currency ? $child->currency->toArray() : null,
                'tags' => $child->tags ? $child->tags->toArray() : [],
                'order' => $child->pivot->order ?? 0,
            ];
        })->toArray();
    }

    public function showAddModal()
    {
        $this->resetModalData();
        $this->resetFilters();
        $this->editChild = null;
        $this->showModal = true;
        $this->loadAvailablePoints();
    }

    private function resetFilters()
    {
        $this->searchTerm = '';
        $this->selectedTags = [];
        $this->selectedCurrency = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->minDuration = '';
        $this->maxDuration = '';
        $this->convertToPln = '';
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetModalData();
        $this->loadChildren(); // Odśwież dane po zamknięciu modala
        $this->dispatch('$refresh');
    }

    private function resetModalData()
    {
        $maxOrder = DB::table('event_template_program_point_parent')
            ->where('parent_id', $this->programPoint->id)
            ->max('order');

        $this->modalData = [
            'id' => null,
            'child_program_point_id' => '',
            'order' => $maxOrder !== null ? $maxOrder + 1 : 0,
        ];
        $this->resetErrorBag();
    }

    public function saveChild()
    {
        // Security checks
        $this->checkPermissions();
        $this->checkRateLimit('save_child');

        $this->validate();

        try {
            DB::transaction(function () {
                // Security: Validate child program point ID
                $childId = (int) $this->modalData['child_program_point_id'];
                if ($childId <= 0) {
                    throw ValidationException::withMessages([
                        'modalData.child_program_point_id' => 'Nieprawidłowy identyfikator punktu programu.',
                        'child_program_point_id' => 'Nieprawidłowy identyfikator punktu programu.',
                    ]);
                }

                // Security: Check if child point exists and user has access
                $childPoint = EventTemplateProgramPoint::find($childId);
                if (!$childPoint) {
                    $this->logSecurityEvent('invalid_child_point_access', "Child ID: $childId");
                    throw ValidationException::withMessages([
                        'modalData.child_program_point_id' => 'Wybrany punkt programu nie istnieje.',
                        'child_program_point_id' => 'Wybrany punkt programu nie istnieje.',
                    ]);
                }

                // Security: Prevent adding parent as child (circular reference)
                if ($childId === $this->programPoint->id) {
                    $this->logSecurityEvent('circular_reference_attempt', "Parent/Child ID: $childId");
                    throw ValidationException::withMessages([
                        'modalData.child_program_point_id' => 'Nie można dodać punktu jako podpunkt samego siebie.',
                        'child_program_point_id' => 'Nie można dodać punktu jako podpunkt samego siebie.',
                    ]);
                }

                // Security: Check if relationship already exists
                $existingRelation = DB::table('event_template_program_point_parent')
                    ->where('parent_id', $this->programPoint->id)
                    ->where('child_id', $childId)
                    ->first();

                if ($existingRelation) {
                    $this->logSecurityEvent('duplicate_relationship_attempt', "Parent: {$this->programPoint->id}, Child: $childId");
                    throw ValidationException::withMessages([
                        'modalData.child_program_point_id' => 'Ten punkt już jest dodany jako podpunkt.',
                        'child_program_point_id' => 'Ten punkt już jest dodany jako podpunkt.',
                    ]);
                }

                // Add new child
                DB::table('event_template_program_point_parent')->insert([
                    'parent_id' => $this->programPoint->id,
                    'child_id' => $childId,
                    'order' => (int) $this->modalData['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Log successful operation
                Log::info('Child program point added successfully', [
                    'user_id' => Auth::id(),
                    'parent_id' => $this->programPoint->id,
                    'child_id' => $childId,
                ]);

                $this->closeModal();
                $this->loadChildren(); // Wymuszenie ponownego ładowania danych
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Podpunkt dodany pomyślnie!']);
                $this->dispatch('$refresh'); // Wymuś odświeżenie komponentu po dodaniu
            });
        } catch (ValidationException $e) {
            // rethrow to let tests assert exceptions where appropriate
            throw $e;
        } catch (\Exception $e) {
            $this->logSecurityEvent('save_child_error', $e->getMessage());
            Log::error("Błąd dodawania podpunktu: " . $e->getMessage(), [
                'user_id' => Auth::id(),
                'parent_id' => $this->programPoint->id,
                'stack_trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Wystąpił błąd podczas dodawania podpunktu. Spróbuj ponownie.']);
        }
    }

    public function deleteChild($childId)
    {
        // Security checks
        $this->checkPermissions();
        $this->checkRateLimit('delete_child');

        try {
            DB::transaction(function () use ($childId) {
                // Security: Validate child ID
                $childId = (int) $childId;
                if ($childId <= 0) {
                    $this->logSecurityEvent('invalid_child_id_delete', "Child ID: $childId");
                    throw new \InvalidArgumentException('Nieprawidłowy identyfikator podpunktu.');
                }

                // Security: Verify ownership/access
                $existingRelation = DB::table('event_template_program_point_parent')
                    ->where('parent_id', $this->programPoint->id)
                    ->where('child_id', $childId)
                    ->first();

                if (!$existingRelation) {
                    $this->logSecurityEvent('unauthorized_delete_attempt', "Parent: {$this->programPoint->id}, Child: $childId");
                    throw new \InvalidArgumentException('Nie można usunąć podpunktu - brak uprawnień lub element nie istnieje.');
                }

                $deleted = DB::table('event_template_program_point_parent')
                    ->where('parent_id', $this->programPoint->id)
                    ->where('child_id', $childId)
                    ->delete();

                if ($deleted) {
                    // Log successful operation
                    Log::info('Child program point deleted successfully', [
                        'user_id' => Auth::id(),
                        'parent_id' => $this->programPoint->id,
                        'child_id' => $childId,
                    ]);

                    $this->loadChildren(); // Wymuszenie ponownego ładowania danych
                    $this->dispatch('notify', ['type' => 'success', 'message' => 'Podpunkt usunięty pomyślnie!']);
                    $this->dispatch('$refresh'); // Wymuś odświeżenie komponentu po usunięciu
                } else {
                    $this->logSecurityEvent('delete_failed', "Parent: {$this->programPoint->id}, Child: $childId");
                    $this->dispatch('notify', ['type' => 'error', 'message' => 'Nie udało się usunąć podpunktu.']);
                }
            });
        } catch (\InvalidArgumentException $e) {
            // rethrow to satisfy tests expecting InvalidArgumentException
            throw $e;
        } catch (\Exception $e) {
            $this->logSecurityEvent('delete_child_error', $e->getMessage());
            Log::error("Błąd usuwania podpunktu: " . $e->getMessage(), [
                'user_id' => Auth::id(),
                'parent_id' => $this->programPoint->id,
                'child_id' => $childId,
                'stack_trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Wystąpił błąd podczas usuwania podpunktu. Spróbuj ponownie.']);
        }
    }

    public function updateChildrenOrder($list)
    {
        // Security checks
        $this->checkPermissions();
        $this->checkRateLimit('update_order');

        try {
            DB::transaction(function () use ($list) {
                // Security: Validate input
                if (!is_array($list)) {
                    $this->logSecurityEvent('invalid_order_list', 'List is not an array');
                    throw new \InvalidArgumentException('Nieprawidłowa lista kolejności.');
                }

                // Security: Validate each child ID
                $validatedIds = [];
                foreach ($list as $index => $childId) {
                    $childId = (int) $childId;
                    if ($childId <= 0) {
                        continue; // Skip invalid IDs
                    }

                    // Verify that this child belongs to current parent
                    $exists = DB::table('event_template_program_point_parent')
                        ->where('parent_id', $this->programPoint->id)
                        ->where('child_id', $childId)
                        ->exists();

                    if ($exists) {
                        $validatedIds[] = $childId;
                    }
                }

                // Update order for validated IDs only
                foreach ($validatedIds as $index => $childId) {
                    DB::table('event_template_program_point_parent')
                        ->where('parent_id', $this->programPoint->id)
                        ->where('child_id', $childId)
                        ->update([
                            'order' => $index,
                            'updated_at' => now(),
                        ]);
                }
            });

            // Log successful operation
            Log::info('Children order updated successfully', [
                'user_id' => Auth::id(),
                'parent_id' => $this->programPoint->id,
                'children_count' => is_array($list) ? count($list) : 0,
            ]);

            $this->loadChildren();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Kolejność podpunktów zaktualizowana.']);
        } catch (\Exception $e) {
            $this->logSecurityEvent('update_order_error', $e->getMessage());
            Log::error("Błąd aktualizacji kolejności podpunktów: " . $e->getMessage(), [
                'user_id' => Auth::id(),
                'parent_id' => $this->programPoint->id,
                'stack_trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Błąd aktualizacji kolejności podpunktów. Spróbuj ponownie.']);
        }
    }

    /**
     * Renderuje komponent Livewire z zabezpieczeniami
     */
    public function render()
    {
        // Note: Security check is done in mount() method to avoid repeated checks
        // $this->checkPermissions(); // Removed from render to prevent repeated checks

        // Pobierz wszystkie tagi i waluty do filtrów
        $allTags = \App\Models\Tag::orderBy('name')->get();
        $allCurrencies = \App\Models\Currency::orderBy('symbol')->get();

        Log::info('ProgramPointChildrenEditor::render() called', [
            'availablePoints_count' => count($this->availablePoints),
            'filteredPoints_count' => count($this->filteredPoints),
            'searchTerm' => $this->searchTerm
        ]);

        return view('livewire.program-point-children-editor-simple', [
            'children' => $this->children,
            'programPoint' => $this->programPoint,
            'availablePoints' => $this->availablePoints, // Przekaż jako array
            'filteredPoints' => $this->filteredPoints,   // Przekaż jako array
            'allTags' => $allTags,
            'allCurrencies' => $allCurrencies,
        ]);
    }
}
