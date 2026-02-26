@foreach($points as $point)
<div class="kanban-item bg-white border border-gray-200 rounded-lg p-3 mb-2 shadow-sm {{ $level > 0 ? 'ml-4' : '' }}" 
     data-pivot-id="{{ $point->pivot->id }}"
     data-point-id="{{ $point->id }}"
     data-parent-id="{{ $point->pivot->parent_id ?? '' }}"
     style="{{ $level > 0 ? 'margin-left: ' . ($level * 20) . 'px;' : '' }}">
    
    <!-- Główna zawartość punktu -->
    <div class="flex items-start justify-between group">
        <div class="flex-1">
            <!-- Tytuł z przyciskiem rozwijania -->
            <div class="flex items-center">
                @if($point->children && $point->children->count() > 0)
                    <button type="button" class="toggle-children mr-2 w-4 h-4 text-gray-400 hover:text-gray-600 transition-colors" 
                            data-point-id="{{ $point->id }}">
                        <svg class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                @else
                    <div class="w-4 h-4 mr-2"></div>
                @endif
                
                <h4 class="font-medium text-gray-900 flex-1">
                    {{ $point->name }}
                </h4>
                
                <!-- Ikona przeciągania -->
                <div class="drag-handle ml-2 cursor-move opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Notatki -->
            @if(!empty($point->pivot->notes))
                <p class="text-sm text-gray-600 mt-1 italic">{{ Str::limit($point->pivot->notes, 80) }}</p>
            @endif
            
            <!-- Metadane -->
            <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                <span class="flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16l4-2 4 2V4"></path>
                    </svg>                    Kolejność: {{ $point->pivot->order ?? 0 }}
                </span>
                
                @if(!($point->pivot->include_in_program ?? true))
                    <span class="flex items-center text-orange-600">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L12 12l-2.122-2.122m0 0L7.76 7.76m2.122 2.122L12 12"></path>
                        </svg>
                        Ukryty
                    </span>
                @endif
                
                @if(!($point->pivot->active ?? true))
                    <span class="flex items-center text-red-600">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18 12l-2.364-6.364m0 0L12 6l-3.636 0.364m7.272 11.636L12 18l-6.364-2.364"></path>
                        </svg>
                        Nieaktywny
                    </span>
                @endif
            </div>
        </div>
        
        <!-- Akcje -->
        <div class="program-point-actions flex items-center gap-1 ml-3">
            <button 
                class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" 
                wire:click="editPoint({{ $point->pivot->id }})"
                title="Edytuj punkt">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </button>
            <button 
                class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" 
                wire:click="deletePoint({{ $point->pivot->id }})"
                onclick="return confirm('Czy na pewno chcesz usunąć ten punkt z programu?')"
                title="Usuń punkt">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Kontener dla dzieci (zagnieżdżone punkty) -->
    @if($point->children && $point->children->count() > 0)
        <div class="kanban-children mt-3 pl-4 border-l-2 border-gray-100" data-parent-id="{{ $point->id }}">
            @foreach($point->children as $child)
                @include('livewire.kanban-program-point-tree', ['point' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
@endforeach
