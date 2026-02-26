<?php

namespace App\Http\Livewire;

use App\Models\EventTemplateProgramPoint;
use Livewire\Component;

class EventTemplateProgramPointTreeExpandable extends Component
{
    public $expanded = [];
    public $tree = [];

    public function mount()
    {
        $this->tree = $this->buildTree();
    }

    public function toggle($id)
    {
        if (in_array($id, $this->expanded)) {
            $this->expanded = array_diff($this->expanded, [$id]);
        } else {
            $this->expanded[] = $id;
        }
    }

    private function buildTree($parentId = null)
    {
        $nodes = EventTemplateProgramPoint::where('parent_id', $parentId)
            ->orderBy('name')
            ->get();
        $tree = [];
        foreach ($nodes as $node) {
            $tree[] = [
                'id' => $node->id,
                'name' => $node->name,
                'children' => $this->buildTree($node->id),
            ];
        }
        return $tree;
    }

    public static function canView(): bool
    {
        return true;
    }

    public static function getDefaultProperties(): array
    {
        return [];
    }

    public function render()
    {
        return view('livewire.event-template-program-point-tree-expandable');
    }
}
