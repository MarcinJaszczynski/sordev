<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\DB;

class EventProgramTree extends Component
{
    public EventTemplate $eventTemplate;
    public array $pointsByDay = [];
    public bool $showModal = false;
    public int $modalDay = 1;
    public ?int $modalPivotId = null;
    public bool $editMode = false;
    public string $modalName = '';
    public string $modalDescription = '';
    public string $modalSearchTerm = '';
    public array $modalExistingResults = [];
    public ?int $modalSelectedExisting = null;
    public string $modalOfficeNotes = '';
    public string $modalPilotNotes = '';
    public array $modalTags = [];
    public int $modalDurationHours = 1;
    public int $modalDurationMinutes = 0;
    public float $modalUnitPrice = 0;
    public int $modalGroupSize = 1;
    public int $modalCurrencyId = 0;
    public bool $modalConvertToPln = false;
    public $modalFeaturedImage;
    public array $modalGalleryImages = [];

    protected $listeners = [
        'updateOrder',
        'delete',
        'refreshComponent' => '$refresh',
    ];

    // Automatyczne wyszukiwanie przy zmianie terminu
    public function updatedModalSearchTerm(string $value): void
    {
        $this->searchPoints($value);
    }

    /**
     * Wyszukiwanie istniejących punktów programu na podstawie terminu
     */
    public function searchPoints(string $term): void
    {
        if (strlen($term) < 2) {
            $this->modalExistingResults = [];
            return;
        }
        $this->modalExistingResults = EventTemplateProgramPoint::query()
            ->where('name', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id','name','description'])
            ->toArray();
    }

    /**
     * Wybór istniejącego punktu z wyników wyszukiwania
     */
    public function selectExisting(int $id): void
    {
        $this->modalSelectedExisting = $id;
        $point = EventTemplateProgramPoint::find($id);
        if ($point) {
            $this->modalName = $point->name;
            $this->modalDescription = $point->description;
        }
        $this->modalExistingResults = [];
    }

    public function mount(EventTemplate $eventTemplate)
    {
        $this->eventTemplate = $eventTemplate;
        $this->loadPoints();
    }

    protected function loadPoints(): void
    {
        // Pobierz dane pivot wraz z informacją o parent-child z osobnej tabeli
        $rows = DB::table('event_template_event_template_program_point as p')
            ->leftJoin('event_template_program_point_parent as pp', 'p.id', '=', 'pp.child_id')
            ->where('p.event_template_id', $this->eventTemplate->id)
            ->orderBy('p.day')
            ->orderBy('p.order')
            ->select([
                'p.id as pivot_id',
                'pp.parent_id',
                'p.day',
                'p.order',
                'p.event_template_program_point_id',
            ])
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $point = EventTemplateProgramPoint::find($row->event_template_program_point_id);
            $items[] = [
                'pivot_id'    => $row->pivot_id,
                'parent_id'   => $row->parent_id,
                'day'         => $row->day,
                'order'       => $row->order,
                'name'        => $point?->name ?? '',
                'description' => $point?->description ?? '',
                'children'    => [],
            ];
        }

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['day']][$item['pivot_id']] = $item;
        }

        foreach ($grouped as $day => &$map) {
            $tree = [];
            foreach ($map as $id => &$node) {
                if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                    $map[$node['parent_id']]['children'][] = &$node;
                } else {
                    $tree[] = &$node;
                }
            }
            usort($tree, fn($a, $b) => $a['order'] <=> $b['order']);
            $grouped[$day] = $tree;
        }
        unset($map, $node, $tree);

        // Upewnij się, że mamy grupy dla wszystkich dni wydarzenia
        $duration = $this->eventTemplate->duration_days;
        for ($d = 1; $d <= $duration; $d++) {
            if (! isset($grouped[$d])) {
                $grouped[$d] = [];
            }
        }
        // Sortuj wg dnia
        ksort($grouped);

        // Przekształć na listę grup, by zachować dzień w JSON
        $list = [];
        foreach ($grouped as $day => $tree) {
            $list[] = ['day' => (int)$day, 'points' => $tree];
        }
        $this->pointsByDay = $list;
    }

    public function updateOrder(int $pivotId, ?int $parentPivotId, int $day, int $order): void
    {
        // Zaktualizuj tylko dzień i kolejność w tabeli pivot
        DB::table('event_template_event_template_program_point')
            ->where('id', $pivotId)
            ->update(['day' => $day, 'order' => $order]);

        // Zaktualizuj relację parent-child
        DB::table('event_template_program_point_parent')
            ->where('child_id', $pivotId)
            ->delete();
        if (! is_null($parentPivotId)) {
            DB::table('event_template_program_point_parent')->insert([
                'parent_id'  => $parentPivotId,
                'child_id'   => $pivotId,
                'order'      => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // W render() dane zostaną załadowane na nowo
    }

    // Usuń pivot entry
    public function delete(int $pivotId): void
    {
        DB::table('event_template_event_template_program_point')
            ->where('id', $pivotId)
            ->delete();
        $this->loadPoints();
        $this->emitSelf('refreshComponent');
    }

    // Duplikuj punkt programu
    public function duplicate(int $pivotId): void
    {
        // Pobierz dane oryginalnego punktu
        $original = DB::table('event_template_event_template_program_point')
            ->where('id', $pivotId)
            ->first();
            
        if (!$original) {
            return;
        }

        // Znajdź największą kolejność w tym samym dniu
        $maxOrder = DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $this->eventTemplate->id)
            ->where('day', $original->day)
            ->max('order');

        // Utwórz duplikat
        DB::table('event_template_event_template_program_point')->insert([
            'event_template_id' => $original->event_template_id,
            'event_template_program_point_id' => $original->event_template_program_point_id,
            'day' => $original->day,
            'order' => ($maxOrder ?? 0) + 1,
            'notes' => $original->notes,
            'include_in_program' => $original->include_in_program,
            'include_in_calculation' => $original->include_in_calculation,
            'active' => $original->active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->loadPoints();
        $this->emitSelf('refreshComponent');
    }

    // Dodaj nowy lub przypnij istniejący w zależności od wybranego
    public function addSave(int $day, ?int $pivotId, string $name, string $description): void
    {
        // Jeśli wybrano istniejący, to attach
        if ($this->modalSelectedExisting) {
            // Ustal kolejność
            $maxOrder = DB::table('event_template_event_template_program_point')
                ->where('event_template_id', $this->eventTemplate->id)
                ->where('day', $day)
                ->max('order');
            $order = $maxOrder ? $maxOrder + 1 : 1;
            DB::table('event_template_event_template_program_point')->insert([
                'event_template_id' => $this->eventTemplate->id,
                'event_template_program_point_id' => $this->modalSelectedExisting,
                'parent_id' => null,
                'day' => $day,
                'order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Tworzenie nowego punktu
            parent::addSave($day, $pivotId, $name, $description);
        }
        // Reset
        $this->modalSearchTerm = '';
        $this->modalSelectedExisting = null;
    }

    // Zapis edycji istniejącego punktu
    public function editSave(int $day, int $pivotId, string $name, string $description): void
    {
        $pivot = DB::table('event_template_event_template_program_point')
            ->where('id', $pivotId)
            ->first();
        if ($pivot) {
            $point = EventTemplateProgramPoint::find($pivot->event_template_program_point_id);
            if ($point) {
                $point->update(['name' => $name, 'description' => $description]);
            }
        }
        $this->loadPoints();
        $this->emitSelf('refreshComponent');
    }

    public function addChild(int $day): void
    {
        $this->modalDay = $day;
        $this->modalPivotId = null;
        $this->editMode = false;
        $this->modalName = '';
        $this->modalDescription = '';
        $this->modalSearchTerm = '';
        $this->modalExistingResults = [];
        $this->modalSelectedExisting = null;
        $this->showModal = true;
    }

    public function edit(int $pivotId): void
    {
        $this->modalPivotId = $pivotId;
        $this->editMode = true;
        $row = DB::table('event_template_event_template_program_point')->where('id', $pivotId)->first();
        $point = EventTemplateProgramPoint::find($row->event_template_program_point_id);
        $this->modalName = $point->name;
        $this->modalDescription = $point->description;
        $this->modalSearchTerm = '';
        $this->modalExistingResults = [];
        $this->modalSelectedExisting = null;
        $this->modalDay = $row->day;
        $this->showModal = true;
    }

    protected function rules(): array
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

    public function savePoint(): void
    {
        $this->validate($this->rules());
        // Find or create point model
        if ($this->editMode && $this->modalPivotId) {
            $pivot = DB::table('event_template_event_template_program_point')->where('id', $this->modalPivotId)->first();
            $point = EventTemplateProgramPoint::findOrFail($pivot->event_template_program_point_id);
        } else {
            $point = new EventTemplateProgramPoint();
        }
        // Fill model fields
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
        // Gallery
        $galleryPaths = [];
        foreach ($this->modalGalleryImages as $img) {
            if ($img) {
                $galleryPaths[] = $img->store('program_points/gallery', 'public');
            }
        }
        $point->gallery_images = $galleryPaths;
        $point->save();
        // Sync tags if any
        if (! empty($this->modalTags)) {
            $point->tags()->sync($this->modalTags);
        }
        // Attach pivot if new
        if (! $this->editMode) {
            $maxOrder = DB::table('event_template_event_template_program_point')
                ->where('event_template_id', $this->eventTemplate->id)
                ->where('day', $this->modalDay)
                ->max('order');
            $order = $maxOrder ? $maxOrder + 1 : 1;
            DB::table('event_template_event_template_program_point')->insert([
                'event_template_id' => $this->eventTemplate->id,
                'event_template_program_point_id' => $point->id,
                'parent_id' => $this->modalParentId,
                'day' => $this->modalDay,
                'order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // Close modal and reload data
        $this->showModal = false;
        $this->loadPoints();
    }

    public function movePoint($pivotId, $newDay, $newParentPivotId, $newOrder)
    {
        // Aktualizuj dzień i kolejność w tabeli pivot
        DB::table('event_template_event_template_program_point')
            ->where('id', $pivotId)
            ->update([
                'day' => $newDay,
                'order' => $newOrder,
                'updated_at' => now(),
            ]);
        // Usuń stare relacje parent-child
        DB::table('event_template_program_point_parent')
            ->where('child_id', function($q) use ($pivotId) {
                $q->select('event_template_program_point_id')
                  ->from('event_template_event_template_program_point')
                  ->where('id', $pivotId);
            })
            ->delete();
        // Dodaj nową relację parent-child jeśli jest parent
        if ($newParentPivotId) {
            $parentPointId = DB::table('event_template_event_template_program_point')
                ->where('id', $newParentPivotId)
                ->value('event_template_program_point_id');
            $childPointId = DB::table('event_template_event_template_program_point')
                ->where('id', $pivotId)
                ->value('event_template_program_point_id');
            DB::table('event_template_program_point_parent')->insert([
                'parent_id' => $parentPointId,
                'child_id' => $childPointId,
                'order' => $newOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function render()
    {
        $days = $this->eventTemplate->duration_days ?? 1;
        $columns = [];
        for ($i = 1; $i <= $days; $i++) {
            $columns[$i] = [
                'day' => $i,
                'title' => "Dzień $i",
                'points' => $this->buildTree($i),
            ];
        }
        return view('livewire.event-program-tree', ['columns' => $columns]);
    }

    protected function buildTree($day)
    {
        // Pobierz punkty programu dla danego dnia
        $points = $this->eventTemplate->programPoints()
            ->withPivot(['id', 'day', 'order'])
            ->wherePivot('day', $day)
            ->get();
        // Pobierz relacje parent-child
        $pointIds = $points->pluck('id')->toArray();
        $hierarchyData = DB::table('event_template_program_point_parent')
            ->whereIn('child_id', $pointIds)
            ->get()
            ->keyBy('child_id');
        // Przypisz parent_pivot_id
        foreach ($points as $point) {
            $point->parent_pivot_id = null;
            if (isset($hierarchyData[$point->id])) {
                $parentPoint = $points->where('id', $hierarchyData[$point->id]->parent_id)->first();
                if ($parentPoint) {
                    $point->parent_pivot_id = $parentPoint->pivot->id;
                }
            }
        }
        // Zbuduj drzewo
        return $this->buildTreeRecursive($points);
    }

    protected function buildTreeRecursive($points, $parentPivotId = null, $level = 0)
    {
        $tree = collect([]);
        foreach ($points as $point) {
            if ($point->parent_pivot_id == $parentPivotId) {
                $point->level = $level;
                $point->children = $this->buildTreeRecursive($points, $point->pivot->id, $level + 1);
                $tree->push($point);
            }
        }
        return $tree;
    }
}
