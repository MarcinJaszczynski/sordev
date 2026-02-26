<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventTemplateProgramPointTreeExpandable extends Component
{
    use WithFileUploads;
    public $tree;
    public $expanded;
    public $showModal = false;
    public $modalParentId = null;
    public $modalName = '';

    // Pola do modala
    public $modalDescription = '';
    public $modalOfficeNotes = '';
    public $modalPilotNotes = '';
    public $modalTags = [];
    public $modalDurationHours = 1;
    public $modalDurationMinutes = 0;
    public $modalUnitPrice = 0;
    public $modalGroupSize = 1;
    public $modalCurrencyId = '';
    public $modalConvertToPln = false;
    public $modalFeaturedImage;
    public $modalGalleryImages = [];

    public $editMode = false;
    public $editPointId = null;

    // Wyszukiwanie na żywo
    public $modalSearchTerm = '';
    public $modalExistingResults = [];
    public $modalSelectedExisting = null;

    public function render()
    {
        Log::info('EventTemplateProgramPointTreeExpandable::render() called');
        return view('livewire.event-template-program-point-tree-expandable', [
            'tree' => $this->tree,
            'expanded' => $this->expanded,
        ]);
    }

    public function mount()
    {
        Log::info('EventTemplateProgramPointTreeExpandable::mount() called');
        $this->tree = $this->buildTree();
        Log::info('Tree built with ' . count($this->tree) . ' root items');
        if (!is_array($this->tree)) {
            $this->tree = [];
        }
        if (!is_array($this->expanded)) {
            $this->expanded = [];
        }
    }

    public static function canView(): bool
    {
        return true;
    }

    public static function getDefaultProperties(): array
    {
        return [];
    }

    public function toggle($id)
    {
        if (in_array($id, $this->expanded)) {
            $this->expanded = array_diff($this->expanded, [$id]);
        } else {
            $this->expanded[] = $id;
        }
    }

    public function updateOrder($parentId, $orderedIds)
    {
        // Najpierw usuń WSZYSTKIE powiązania parent-child dla tych child_id
        foreach ($orderedIds as $index => $id) {
            DB::table('event_template_program_point_parent')
                ->where('child_id', $id)
                ->delete();
        }
        // Następnie dodaj nowe powiązania z nowym parentem i kolejnością
        if (!is_null($parentId)) {
            foreach ($orderedIds as $index => $id) {
                DB::table('event_template_program_point_parent')
                    ->updateOrInsert(
                        [
                            'parent_id' => $parentId,
                            'child_id' => $id,
                        ],
                        [
                            'order' => $index,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
            }
        }
        $this->tree = $this->buildTree();
        $this->dispatch('$refresh'); // Wymuś odświeżenie komponentu po zmianie kolejności
    }

    /**
     * Automatyczne wyszukiwanie przy zmianie terminu z debounce
     */
    public function updatedModalSearchTerm($value)
    {
        Log::info('updatedModalSearchTerm called with value: ' . $value);

        // Zwiększ minimalną liczbę znaków do 3 dla lepszej wydajności
        if (strlen($value) >= 3) {
            Log::info('Calling searchPoints for term: ' . $value);
            $this->searchPoints($value);
        } else {
            Log::info('Clearing search results - term too short');
            $this->modalExistingResults = [];
        }

        Log::info('modalExistingResults count: ' . count($this->modalExistingResults));

        // Explicit refresh dla problemów z DOM morphing
        $this->render();
    }

    /**
     * Wyszukiwanie istniejących punktów programu - zoptymalizowane
     */
    public function searchPoints($term)
    {
        Log::info('searchPoints called with term: ' . $term);

        if (strlen($term) < 3) {
            $this->modalExistingResults = [];
            Log::info('Search term too short, clearing results');
            return;
        }

        try {
            // Optymalizacja: najpierw szukaj dokładnych dopasowań, potem częściowe
            $exactMatches = EventTemplateProgramPoint::where('name', 'like', $term . '%')
                ->orderBy('name')
                ->limit(5)
                ->get();

            $partialMatches = EventTemplateProgramPoint::where('name', 'like', '%' . $term . '%')
                ->where('name', 'not like', $term . '%') // Wykluczamy już znalezione dokładne
                ->orWhere('description', 'like', '%' . $term . '%')
                ->orderBy('name')
                ->limit(5)
                ->get();

            $allResults = $exactMatches->concat($partialMatches)->unique('id')->take(8);

            $this->modalExistingResults = $allResults->map(function ($point) {
                return [
                    'id' => $point->id,
                    'name' => $point->name,
                    'description' => $point->description ? substr($point->description, 0, 100) . '...' : '',
                    'duration_hours' => $point->duration_hours,
                    'duration_minutes' => $point->duration_minutes,
                    'unit_price' => $point->unit_price ?? 0,
                ];
            })->toArray();

            Log::info('Found ' . count($this->modalExistingResults) . ' results for term: ' . $term);
            Log::info('Results: ' . json_encode($this->modalExistingResults));

            // Wymuś odświeżenie UI
            $this->dispatch('searchResultsUpdated');
        } catch (\Exception $e) {
            Log::error('Error in searchPoints: ' . $e->getMessage());
            $this->modalExistingResults = [];
        }
    }

    /**
     * Wybór istniejącego punktu z wyszukiwania
     */
    public function selectExisting($pointId)
    {
        $point = EventTemplateProgramPoint::findOrFail($pointId);

        $this->modalName = $point->name;
        $this->modalDescription = $point->description;
        $this->modalOfficeNotes = $point->office_notes;
        $this->modalPilotNotes = $point->pilot_notes;
        $this->modalTags = $point->tags()->pluck('tags.id')->toArray();
        $this->modalDurationHours = $point->duration_hours;
        $this->modalDurationMinutes = $point->duration_minutes;
        $this->modalUnitPrice = $point->unit_price;
        $this->modalGroupSize = $point->group_size;
        $this->modalCurrencyId = $point->currency_id;
        $this->modalConvertToPln = $point->convert_to_pln;

        $this->modalSelectedExisting = $pointId;
        $this->modalSearchTerm = '';
        $this->modalExistingResults = [];
    }

    public function addChild($parentId)
    {
        $this->modalParentId = $parentId;
        $this->modalName = '';
        $this->modalDescription = '';
        $this->modalOfficeNotes = '';
        $this->modalPilotNotes = '';
        $this->modalTags = [];
        $this->modalDurationHours = 1;
        $this->modalDurationMinutes = 0;
        $this->modalUnitPrice = 0;
        $this->modalGroupSize = 1;
        $this->modalCurrencyId = '';
        $this->modalConvertToPln = false;
        $this->modalFeaturedImage = null;
        $this->modalGalleryImages = [];
        $this->modalSearchTerm = '';
        $this->modalExistingResults = [];
        $this->modalSelectedExisting = null;
        $this->editMode = false;
        $this->editPointId = null;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $point = \App\Models\EventTemplateProgramPoint::findOrFail($id);
        $this->editMode = true;
        $this->editPointId = $id;
        $this->modalParentId = optional($point->parents()->first())->id;
        $this->modalName = $point->name;
        $this->modalDescription = $point->description;
        $this->modalOfficeNotes = $point->office_notes;
        $this->modalPilotNotes = $point->pilot_notes;
        $this->modalTags = $point->tags()->pluck('tags.id')->toArray(); // poprawka ambiguous column name
        $this->modalDurationHours = $point->duration_hours;
        $this->modalDurationMinutes = $point->duration_minutes;
        $this->modalUnitPrice = $point->unit_price;
        $this->modalGroupSize = $point->group_size;
        $this->modalCurrencyId = $point->currency_id;
        $this->modalConvertToPln = $point->convert_to_pln;
        $this->modalFeaturedImage = null;
        $this->modalGalleryImages = [];
        $this->modalSearchTerm = '';
        $this->modalExistingResults = [];
        $this->modalSelectedExisting = null;
        $this->showModal = true;
    }

    public function delete($id)
    {
        $point = \App\Models\EventTemplateProgramPoint::findOrFail($id);
        // Usuwamy relacje parent-child
        DB::table('event_template_program_point_parent')->where('child_id', $id)->orWhere('parent_id', $id)->delete();
        // Usuwamy relacje tagów
        $point->tags()->detach();
        // Usuwamy punkt programu
        $point->delete();
        $this->tree = $this->buildTree();
        $this->dispatch('$refresh');
    }

    public function rules()
    {
        return [
            'modalName' => 'required|string|max:255',
            'modalDescription' => 'nullable|string',
            'modalOfficeNotes' => 'nullable|string',
            'modalPilotNotes' => 'nullable|string',
            'modalTags' => 'array',
            'modalDurationHours' => 'required|integer|min:0',
            'modalDurationMinutes' => 'required|integer|min:0|max:59',
            'modalUnitPrice' => 'required|numeric|min:0',
            'modalGroupSize' => 'required|integer|min:1',
            'modalCurrencyId' => 'required|integer',
            'modalConvertToPln' => 'boolean',
            'modalFeaturedImage' => 'nullable|file|image|max:2048',
            'modalGalleryImages' => 'nullable|array',
            'modalGalleryImages.*' => 'nullable|file|image|max:2048',
        ];
    }

    public function saveChild()
    {
        $this->validate($this->rules());
        if ($this->editMode && $this->editPointId) {
            $point = \App\Models\EventTemplateProgramPoint::findOrFail($this->editPointId);
        } else {
            $point = new \App\Models\EventTemplateProgramPoint();
        }
        $point->name = $this->modalName;
        $point->description = $this->modalDescription;
        $point->office_notes = $this->modalOfficeNotes;
        $point->pilot_notes = $this->modalPilotNotes;
        $point->duration_hours = $this->modalDurationHours;
        $point->duration_minutes = $this->modalDurationMinutes;
        $point->unit_price = $this->modalUnitPrice;
        $point->group_size = $this->modalGroupSize;
        $point->currency_id = $this->modalCurrencyId;
        $point->convert_to_pln = $this->modalConvertToPln;
        if ($this->modalFeaturedImage) {
            $point->featured_image = $this->modalFeaturedImage->store('program_points', 'public');
        }
        $galleryPaths = [];
        if (is_array($this->modalGalleryImages)) {
            foreach ($this->modalGalleryImages as $img) {
                if ($img) {
                    $galleryPaths[] = $img->store('program_points/gallery', 'public');
                }
            }
        }
        $point->gallery_images = $galleryPaths;
        $point->save();
        if (!empty($this->modalTags)) {
            $point->tags()->sync($this->modalTags);
        }
        if (!$this->editMode && $this->modalParentId) {
            DB::table('event_template_program_point_parent')->insert([
                'parent_id' => $this->modalParentId,
                'child_id' => $point->id,
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->showModal = false;
        $this->editMode = false;
        $this->editPointId = null;
        $this->modalSearchTerm = '';
        $this->modalExistingResults = [];
        $this->modalSelectedExisting = null;
        $this->tree = $this->buildTree();
        $this->dispatch('$refresh');
    }

    private function buildTree($parentId = null, $visited = [])
    {
        // Główne punkty (bez rodziców lub z danym parentId)
        $query = EventTemplateProgramPoint::query();
        if ($parentId) {
            $query->whereHas('parents', function ($q) use ($parentId) {
                $q->where('event_template_program_point_parent.parent_id', $parentId);
            });
        } else {
            // Punkty bez rodziców
            $query->whereDoesntHave('parents');
        }
        $nodes = $query->orderBy('name')->get();

        // Debug: sprawdź ile punktów znaleziono
        Log::info('BuildTree: parentId=' . ($parentId ?? 'null') . ', found nodes: ' . $nodes->count());

        $tree = [];
        foreach ($nodes as $node) {
            // Zapobiegamy zapętleniu
            if (in_array($node->id, $visited)) continue;
            $tree[] = [
                'id' => $node->id,
                'name' => $node->name,
                'children' => $this->buildTree($node->id, array_merge($visited, [$node->id])),
            ];
        }
        return $tree;
    }
}
