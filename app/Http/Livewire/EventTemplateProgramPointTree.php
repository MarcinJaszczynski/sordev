<?php

namespace App\Http\Livewire;

use App\Models\EventTemplateProgramPoint;
use Livewire\Component;

class EventTemplateProgramPointTree extends Component
{
    public $tree = [];

    public function mount()
    {
        $this->tree = $this->buildTree();
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

    public function render()
    {
        return view('livewire.event-template-program-point-tree');
    }
}
