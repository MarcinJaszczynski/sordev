<div>
    <!-- Komunikaty -->
    <div id="notifications" class="fixed top-4 right-4 z-50">
        <!-- Tu będą wyświetlane notyfikacje -->
    </div>

    <div class="program-editor-header mb-6">
        <div>
            <div class="program-page-eyebrow">Szablon imprezy</div>
            <h2 class="program-page-title">Program wydarzenia: {{ $eventTemplate->name }}</h2>
            <p class="program-page-subtitle">Uporządkuj punkty programu, materiały, widoczność i zadania w jednym miejscu.</p>
        </div>
        <div class="flex items-center space-x-3"> <a
                href="{{ \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $eventTemplate->id]) }}"
                class="program-toolbar-link">
                ← Wróć do edycji szablonu
            </a> <button wire:click="showAddModal"
                class="program-toolbar-button">
                <x-heroicon-o-plus-circle class="w-5 h-5 mr-2" />
                Dodaj punkt programu
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6" id="program-days-container" wire:ignore.self>
        @forelse ($programByDays as $day)
            @php
                $dayNumber = $day['day'];
                $points = $day['points'];
            @endphp
            <div class="program-day-card fi-section-content" data-day="{{ $dayNumber }}">
                <div class="program-day-header">
                    <div>
                        <div class="program-day-eyebrow">Harmonogram dnia</div>
                        <h3 class="program-day-title">
                        @if($dayNumber > $eventTemplate->duration_days)
                            Fakultatywnie
                        @else
                            Dzień {{ $dayNumber }}
                        @endif
                        </h3>
                    </div>
                    <div class="program-day-insurance-block">
                        <div class="program-day-insurance-label">Ubezpieczenia dnia</div>
                        <div class="flex flex-wrap gap-4 items-center mt-2">
                        @foreach(App\Models\Insurance::active()->get() as $insurance)
                            <label class="flex items-center gap-2">
                                <input type="checkbox"
                                    wire:click="toggleDayInsurance({{ $dayNumber }}, {{ $insurance->id }})"
                                    @checked($eventTemplate->dayInsurances->where('day', $dayNumber)->pluck('insurance_id')->contains($insurance->id))
                                    class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:border-blue-600 focus:ring-blue-600">
                                <span class="text-sm text-gray-700">{{ $insurance->name }}</span>
                            </label>
                        @endforeach
                        </div>
                    </div>
                </div>
                <ul class="program-day-list space-y-4 min-h-[100px] border border-dashed border-slate-300 bg-slate-50/70 p-3 rounded-2xl"
                    data-day-id="{{ $dayNumber }}" wire:ignore>
                    @forelse ($points as $point)
                        @php
                            $colors = [
                                0 => ['bg' => 'bg-amber-500', 'icon' => 'clipboard-document-list'],
                                1 => ['bg' => 'bg-sky-600', 'icon' => 'calculator'],
                                2 => ['bg' => 'bg-emerald-600', 'icon' => 'check-circle'],
                                3 => ['bg' => 'bg-rose-500', 'icon' => 'puzzle-piece'],
                            ];
                            $colorIdx = $loop->index % 4;
                            $color = $colors[$colorIdx];
                            $pointDescription = trim(strip_tags((string) ($point['description'] ?? '')));
                            $pointOfficeNotes = trim(strip_tags((string) ($point['office_notes'] ?? '')));
                            $pointPivotNotes = trim(strip_tags((string) ($point['pivot_notes'] ?? '')));
                        @endphp
                        <li class="program-point-item relative"
                            data-pivot-id="{{ $point['pivot_id'] ?? $point['id'] }}" data-point-id="{{ $point['id'] }}" data-id="{{ $point['pivot_id'] ?? $point['id'] }}">
                            <div class="program-point-shell">
                                <div class="program-point-rail">
                                    <div class="program-point-drag drag-handle select-none" title="Przeciągnij, aby zmienić kolejność">
                                        <x-heroicon-o-bars-3 class="w-5 h-5 text-slate-400" />
                                    </div>
                                    <div class="program-point-index {{ $color['bg'] }}">
                                        @if($color['icon'] === 'clipboard-document-list')
                                            <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-white" />
                                        @elseif($color['icon'] === 'calculator')
                                            <x-heroicon-o-calculator class="w-6 h-6 text-white" />
                                        @elseif($color['icon'] === 'check-circle')
                                            <x-heroicon-o-check-circle class="w-6 h-6 text-white" />
                                        @elseif($color['icon'] === 'puzzle-piece')
                                            <x-heroicon-o-puzzle-piece class="w-6 h-6 text-white" />
                                        @endif
                                        <span class="program-point-order">{{ $loop->iteration }}</span>
                                    </div>
                                </div>

                                <div class="program-point-body">
                                    <div class="program-point-main">
                                        <div class="program-kicker">Punkt programu</div>
                                        <div class="program-heading-row program-heading-row--spread">
                                            <h4 class="program-point-title">{{ $point['name'] }}</h4>
                                            <div class="program-settings-inline">
                                                <label class="program-setting-tile compact">
                                                    <input type="checkbox"
                                                           wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'include_in_program')"
                                                           @checked($point['include_in_program'] ?? true)
                                                           class="form-checkbox text-orange-500">
                                                    <span class="program-setting-text">Program</span>
                                                </label>
                                                <label class="program-setting-tile compact">
                                                    <input type="checkbox"
                                                           wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'include_in_calculation')"
                                                           @checked($point['include_in_calculation'] ?? true)
                                                           class="form-checkbox text-blue-500">
                                                    <span class="program-setting-text">W kosztach</span>
                                                </label>
                                                <label class="program-setting-tile compact">
                                                    <input type="checkbox"
                                                           wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'active')"
                                                           @checked($point['active'] ?? true)
                                                           class="form-checkbox text-green-500">
                                                    <span class="program-setting-text">Aktywny</span>
                                                </label>
                                                <label class="program-setting-tile compact">
                                                    <input type="checkbox"
                                                           wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'show_title_style')"
                                                           @checked($point['show_title_style'] ?? true)
                                                           class="form-checkbox text-purple-500">
                                                    <span class="program-setting-text">Styl tytułu</span>
                                                </label>
                                                <label class="program-setting-tile compact">
                                                    <input type="checkbox"
                                                           wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'show_description')"
                                                           @checked($point['show_description'] ?? true)
                                                           class="form-checkbox text-pink-500">
                                                    <span class="program-setting-text">Opis</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="program-meta-row">
                                            @if(!empty($point['start_time']) && !empty($point['end_time']))
                                                <span class="program-meta-chip">Godziny: {{ substr((string) $point['start_time'], 0, 5) }} - {{ substr((string) $point['end_time'], 0, 5) }}</span>
                                            @endif
                                            <span class="program-meta-chip">Czas trwania: {{ isset($point['duration_hours']) || isset($point['duration_minutes']) ? sprintf('%02d:%02d', $point['duration_hours'] ?? 0, $point['duration_minutes'] ?? 0) : '-' }}</span>
                                            @if(!empty($point['tags']) && is_array($point['tags']))
                                                <span class="program-meta-chip">Tagów: {{ count($point['tags']) }}</span>
                                            @endif
                                        </div>

                                        @if($pointDescription || $pointOfficeNotes || $pointPivotNotes)
                                            <div class="program-detail-grid">
                                                @if($pointDescription)
                                                    <div class="program-detail-card">
                                                        <div class="program-detail-label">Opis</div>
                                                        <div class="program-detail-value">{{ \Illuminate\Support\Str::limit($pointDescription, 220) }}</div>
                                                    </div>
                                                @endif
                                                @if($pointOfficeNotes)
                                                    <div class="program-detail-card">
                                                        <div class="program-detail-label">Uwagi dla biura</div>
                                                        <div class="program-detail-value">{{ \Illuminate\Support\Str::limit($pointOfficeNotes, 140) }}</div>
                                                    </div>
                                                @endif
                                                @if($pointPivotNotes)
                                                    <div class="program-detail-card">
                                                        <div class="program-detail-label">Notatki w programie</div>
                                                        <div class="program-detail-value">{{ \Illuminate\Support\Str::limit($pointPivotNotes, 140) }}</div>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="program-inline-empty">Brak opisu i notatek dla tego punktu programu.</div>
                                        @endif

                                        <div class="program-sidebar-card mt-2">
                                            <div class="program-card-title">Media</div>
                                            <div class="program-media-stack">
                                                @if(!empty($point['featured_image']) && !str_contains($point['featured_image'], 'tmp'))
                                                    <img src="{{ Storage::url($point['featured_image']) }}" alt="Miniaturka" class="program-featured-image" onerror="this.style.display='none'">
                                                @endif
                                                @if(!empty($point['gallery_images']) && is_array($point['gallery_images']))
                                                    <div class="program-gallery-strip">
                                                        @foreach(array_slice($point['gallery_images'], 0, 4) as $image)
                                                            @if(is_string($image) && !str_contains($image, 'tmp'))
                                                                <img src="{{ Storage::url($image) }}" alt="Miniaturka galerii" class="program-gallery-thumb" onerror="this.style.display='none'">
                                                            @endif
                                                        @endforeach
                                                        @if(count($point['gallery_images']) > 4)
                                                            <span class="program-gallery-counter">+{{ count($point['gallery_images']) - 4 }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if(empty($point['featured_image']) && empty($point['gallery_images']))
                                                    <div class="program-empty-state">Brak zdjęć dla tego punktu.</div>
                                                @endif
                                            </div>
                                        </div>

                                        @if(!empty($point['tags']) && is_array($point['tags']))
                                            <div class="program-tag-section">
                                                <div class="program-detail-label">Tagi</div>
                                                <div class="program-tag-list">
                                                    @foreach($point['tags'] as $tag)
                                                        <span class="program-tag-pill">{{ $tag['name'] }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <aside class="program-point-sidebar">
                                        <div class="program-sidebar-card">
                                            <div class="program-card-title">Akcje</div>
                                            <div class="program-actions-stack">
                                                <a href="{{ $this->getTaskCreateUrlForPoint((int) ($point['id'] ?? 0)) }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="program-action-link program-action-primary"
                                                   title="Dodaj zadanie">
                                                    <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                                    <span>Dodaj zadanie</span>
                                                </a>
                                                <a href="/admin/event-template-program-points/{{ $point['id'] }}/edit"
                                                   class="program-action-link"
                                                   title="Edytuj punkt programu">
                                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                    <span>Edytuj punkt</span>
                                                </a>
                                                <button wire:click="duplicatePoint({{ $point['pivot_id'] ?? $point['id'] }})"
                                                    wire:confirm="Czy na pewno chcesz zduplikować ten punkt programu?"
                                                    class="program-action-link"
                                                    title="Duplikuj punkt programu">
                                                    <x-heroicon-o-document-duplicate class="w-4 h-4" />
                                                    <span>Duplikuj</span>
                                                </button>
                                                <button wire:click="deletePoint({{ $point['pivot_id'] ?? $point['id'] }})"
                                                    wire:confirm="Czy na pewno chcesz usunąć ten punkt z programu?"
                                                    class="program-action-link program-action-danger"
                                                    title="Usuń punkt programu">
                                                    <x-heroicon-o-trash class="w-4 h-4" />
                                                    <span>Usuń</span>
                                                </button>
                                            </div>
                                        </div>
                                    </aside>
                                </div>
                            </div>
                        </li>
                        @if(!empty($point['children']))
                            <li class="children-container">
                                <div class="program-children-shell ml-8 mt-3">
                                    <div class="program-kicker">Podpunkty programu</div>
                                    <ul class="program-children-list">
                                    @foreach($point['children'] as $child)
                                        @php
                                            $childDescription = trim(strip_tags((string) ($child['description'] ?? '')));
                                            $childOfficeNotes = trim(strip_tags((string) ($child['office_notes'] ?? '')));
                                        @endphp
                                        <li class="program-child-card">
                                            <div class="program-child-main">
                                                <div class="program-child-icon">
                                                    <x-heroicon-o-arrow-turn-down-right class="w-4 h-4" />
                                                </div>
                                                <div class="program-child-content">
                                                    <div class="program-kicker">Podpunkt programu</div>
                                                    <div class="program-heading-row program-heading-row--spread">
                                                        <div class="program-child-title">{{ $child['name'] }}</div>
                                                        <div class="program-settings-inline">
                                                            <label class="program-setting-tile compact">
                                                                <input type="checkbox"
                                                                       wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'include_in_program')"
                                                                       @checked($child['include_in_program'] ?? true)
                                                                       class="form-checkbox text-orange-500">
                                                                <span class="program-setting-text">Program</span>
                                                            </label>
                                                            <label class="program-setting-tile compact">
                                                                <input type="checkbox"
                                                                       wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'include_in_calculation')"
                                                                       @checked($child['include_in_calculation'] ?? true)
                                                                       class="form-checkbox text-blue-500">
                                                                <span class="program-setting-text">W kosztach</span>
                                                            </label>
                                                            <label class="program-setting-tile compact">
                                                                <input type="checkbox"
                                                                       wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'active')"
                                                                       @checked($child['active'] ?? true)
                                                                       class="form-checkbox text-green-500">
                                                                <span class="program-setting-text">Aktywny</span>
                                                            </label>
                                                            <label class="program-setting-tile compact">
                                                                <input type="checkbox"
                                                                       wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'show_title_style')"
                                                                       @checked($child['show_title_style'] ?? true)
                                                                       class="form-checkbox text-purple-500">
                                                                <span class="program-setting-text">Styl tytułu</span>
                                                            </label>
                                                            <label class="program-setting-tile compact">
                                                                <input type="checkbox"
                                                                       wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'show_description')"
                                                                       @checked($child['show_description'] ?? true)
                                                                       class="form-checkbox text-pink-500">
                                                                <span class="program-setting-text">Opis</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="program-meta-row">
                                                        <span class="program-meta-chip">Czas trwania: {{ sprintf('%02d:%02d', $child['duration_hours'] ?? 0, $child['duration_minutes'] ?? 0) }}</span>
                                                        @if(!empty($child['tags']) && is_array($child['tags']))
                                                            <span class="program-meta-chip">Tagów: {{ count($child['tags']) }}</span>
                                                        @endif
                                                    </div>
                                                    @if($childDescription || $childOfficeNotes)
                                                        <div class="program-detail-grid compact">
                                                            @if($childDescription)
                                                                <div class="program-detail-card">
                                                                    <div class="program-detail-label">Opis</div>
                                                                    <div class="program-detail-value">{{ \Illuminate\Support\Str::limit($childDescription, 140) }}</div>
                                                                </div>
                                                            @endif
                                                            @if($childOfficeNotes)
                                                                <div class="program-detail-card">
                                                                    <div class="program-detail-label">Uwagi dla biura</div>
                                                                    <div class="program-detail-value">{{ \Illuminate\Support\Str::limit($childOfficeNotes, 120) }}</div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div class="program-inline-empty">Brak opisu podpunktu.</div>
                                                    @endif
                                                    @if(!empty($child['tags']) && is_array($child['tags']))
                                                        <div class="program-tag-list mt-3">
                                                            @foreach($child['tags'] as $tag)
                                                                <span class="program-tag-pill">{{ $tag['name'] }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="program-child-side">
                                                <div class="program-sidebar-card compact">
                                                    <div class="program-card-title">Akcje</div>
                                                    <div class="program-actions-stack compact">
                                                        <a href="{{ $this->getTaskCreateUrlForChild((int) ($child['id'] ?? 0)) }}"
                                                           target="_blank"
                                                           rel="noopener"
                                                           class="program-action-link program-action-primary"
                                                           title="Dodaj zadanie do podpunktu">
                                                            <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                                            <span>Dodaj zadanie</span>
                                                        </a>
                                                        <a href="/admin/event-template-program-points/{{ $child['id'] }}/edit"
                                                           class="program-action-link"
                                                           title="Edytuj podpunkt programu">
                                                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                            <span>Edytuj podpunkt</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                    </ul>
                                </div>
                            </li>
                        @endif
                    @empty
                        <li class="text-center text-gray-400 py-4 italic">Brak punktów programu na ten dzień.</li>
                    @endforelse <!-- endforelse points -->
                </ul>
            </div>
        @empty
            @for ($i = 1; $i <= $duration_days; $i++)
                <div class="program-day-card fi-section-content" data-day="{{ $i }}">
                    <div class="program-day-header">
                        <div>
                            <div class="program-day-eyebrow">Harmonogram dnia</div>
                            <h3 class="program-day-title">
                            @if($i > $eventTemplate->duration_days)
                                Fakultatywnie
                            @else
                                Dzień {{ $i }}
                            @endif
                            </h3>
                        </div>
                    </div>
                    <ul class="program-day-list space-y-4 min-h-[100px] border border-dashed border-slate-300 bg-slate-50/70 p-3 rounded-2xl"
                        data-day-id="{{ $i }}">
                        <li class="text-center text-gray-400 py-4 italic">Brak punktów programu na ten dzień.</li>
                        <!-- Dodaj ukryty element, aby Sortable.js widział dropzone nawet gdy lista jest pusta -->
                        <li class="program-point-item invisible h-0"></li>
                    </ul>
                </div>
            @endfor
        @endforelse
    </div> <!-- Koniec głównej siatki dni programu -->
    <style>
        [x-cloak] {
            display: none !important;
        }
        
        .program-editor-header {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            align-items: flex-start;
            padding: 1.5rem;
            border: 1px solid #dbe3ef;
            border-radius: 1.5rem;
            background: linear-gradient(135deg, #f8fbff 0%, #eef4fb 100%);
            box-shadow: 0 20px 45px -35px rgba(15, 23, 42, 0.55);
        }

        .program-page-eyebrow,
        .program-day-eyebrow,
        .program-kicker {
            font-size: 0.72rem;
            line-height: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }

        .program-page-title {
            margin-top: 0.35rem;
            font-size: 1.7rem;
            line-height: 2rem;
            color: #0f172a;
            font-weight: 700;
        }

        .program-page-subtitle {
            margin-top: 0.4rem;
            color: #475569;
            max-width: 48rem;
            font-size: 0.95rem;
        }

        .program-toolbar-link,
        .program-toolbar-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            padding: 0.8rem 1rem;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .program-toolbar-link {
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }

        .program-toolbar-link:hover {
            background: #f8fafc;
        }

        .program-toolbar-button {
            background: #0f766e;
            color: #ffffff;
            border: 1px solid #115e59;
        }

        .program-toolbar-button:hover {
            background: #115e59;
        }

        .program-day-card {
            padding: 1rem;
            border-radius: 1.5rem;
            border: 1px solid #dbe3ef;
            background: #fff;
            box-shadow: 0 20px 45px -38px rgba(15, 23, 42, 0.55);
        }

        .program-day-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .program-day-title {
            margin-top: 0.35rem;
            font-size: 1.25rem;
            line-height: 1.75rem;
            font-weight: 700;
            color: #172554;
        }

        .program-day-insurance-block {
            min-width: 20rem;
            padding: 0.6rem 0.85rem;
            border-radius: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .program-day-insurance-label,
        .program-card-title,
        .program-detail-label {
            font-size: 0.76rem;
            line-height: 1rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* Ładne checkboxy */
        .form-checkbox {
            appearance: none;
            background-color: #fff;
            margin: 0;
            color: currentColor;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #d1d5db;
            border-radius: 0.25rem;
            display: grid;
            place-content: center;
            transition: all 0.2s ease;
        }

        .form-checkbox:checked {
            border-color: currentColor;
            background-color: currentColor;
        }

        .form-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
            transform: scale(0);
            transform-origin: bottom left;
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
        }

        .form-checkbox:checked::before {
            transform: scale(1);
        }

        .form-checkbox:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 2px currentColor;
        }

        /* Drag handle cursor */
        .drag-handle {
            cursor: grab !important;
            user-select: none;
        }

        .drag-handle:active {
            cursor: grabbing !important;
        }

        /* Sortable states */
        .sortable-ghost {
            opacity: 0.4;
            background: #e5e7eb !important;
            border: 2px dashed #3b82f6 !important;
        }

        .sortable-chosen {
            opacity: 0.8;
            box-shadow: 0 0 0 2px #3b82f6 !important;
        }

        .sortable-drag {
            opacity: 1;
            cursor: grabbing !important;
            transform: rotate(3deg);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3) !important;
        }

        /* Highlight drop zones during drag */
        .program-day-list.sortable-over {
            background-color: #dbeafe !important;
            border-color: #3b82f6 !important;
            border-width: 2px;
        }

        .program-point-item {
            transition: all 0.2s ease;
        }

        .program-point-shell {
            display: grid;
            grid-template-columns: 5.5rem minmax(0, 1fr);
            border: 1px solid #dbe3ef;
            border-radius: 1.1rem;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 16px 36px -32px rgba(15, 23, 42, 0.55);
        }

        .program-point-rail {
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            border-right: 1px solid #dbe3ef;
        }

        .program-point-drag {
            min-height: 3.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #dbe3ef;
        }

        .program-point-index {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            flex: 1;
            padding: 1rem 0.5rem;
        }

        .program-point-order {
            font-size: 1.55rem;
            line-height: 1.75rem;
            font-weight: 800;
            color: #ffffff;
        }

        .program-point-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 13.5rem;
            gap: 0.75rem;
            padding: 0.85rem;
        }

        .program-point-main,
        .program-point-sidebar,
        .program-child-content {
            min-width: 0;
        }

        .program-heading-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.2rem;
        }

        .program-heading-row--spread {
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            row-gap: 0.45rem;
        }

        .program-settings-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            justify-content: flex-end;
        }

        .program-settings-inline .program-setting-tile {
            background: #f8fafc;
        }

        .program-point-title,
        .program-child-title {
            font-size: 1rem;
            line-height: 1.35rem;
            font-weight: 700;
            color: #0f172a;
        }

        .program-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.45rem;
        }

        .program-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 9999px;
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            color: #334155;
            padding: 0.2rem 0.55rem;
            font-size: 0.69rem;
            font-weight: 600;
        }

        .program-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
            margin-top: 0.55rem;
        }

        .program-detail-grid.compact {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .program-detail-card,
        .program-sidebar-card,
        .program-children-shell {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 0.55rem 0.7rem;
        }

        .program-sidebar-card {
            background: #f8fafc;
            margin: 0;
        }

        .program-sidebar-card.compact {
            padding: 0.45rem 0.6rem;
        }

        .program-detail-value {
            margin-top: 0.3rem;
            color: #1e293b;
            font-size: 0.78rem;
            line-height: 1.2rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .program-detail-value.is-muted,
        .program-empty-state {
            color: #94a3b8;
        }

        .program-tag-section {
            margin-top: 0.6rem;
        }

        .program-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.35rem;
        }

        .program-tag-list.mt-3 {
            margin-top: 0.75rem;
        }

        .program-tag-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            background: #e2e8f0;
            color: #334155;
            padding: 0.2rem 0.55rem;
            font-size: 0.68rem;
            font-weight: 600;
        }

        .program-media-stack {
            margin-top: 0.45rem;
        }

        .program-featured-image {
            width: 100%;
            height: 5.25rem;
            object-fit: cover;
            border-radius: 0.9rem;
            border: 1px solid #dbe3ef;
        }

        .program-gallery-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.3rem;
            margin-top: 0.35rem;
        }

        .program-gallery-thumb,
        .program-gallery-counter {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 0.7rem;
            object-fit: cover;
            border: 1px solid #dbe3ef;
        }

        .program-gallery-counter {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .program-point-sidebar,
        .program-child-side {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .program-point-sidebar .program-actions-stack,
        .program-child-side .program-actions-stack {
            grid-template-columns: 1fr;
        }

        .program-settings-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.35rem;
            margin-top: 0.4rem;
        }

        .program-settings-grid.compact {
            grid-template-columns: 1fr;
        }

        .program-setting-tile {
            display: flex;
            align-items: center;
            gap: 0.38rem;
            padding: 0.36rem 0.45rem;
            border-radius: 0.65rem;
            border: 1px solid #dbe3ef;
            background: #ffffff;
            min-width: 0;
        }

        .program-setting-tile.compact {
            padding: 0.32rem 0.4rem;
        }

        .program-setting-text {
            font-size: 0.69rem;
            line-height: 1rem;
            font-weight: 600;
            color: #334155;
        }

        .program-actions-stack {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.35rem;
            margin-top: 0.4rem;
        }

        .program-actions-stack.compact {
            margin-top: 0.35rem;
        }

        .program-action-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            justify-content: flex-start;
            width: 100%;
            border-radius: 0.65rem;
            border: 1px solid #dbe3ef;
            background: #ffffff;
            color: #1e293b;
            padding: 0.4rem 0.45rem;
            font-size: 0.7rem;
            font-weight: 600;
            transition: 0.2s ease;
            min-height: 2rem;
        }

        .program-action-link:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .program-action-primary {
            background: #ecfeff;
            border-color: #99f6e4;
            color: #115e59;
        }

        .program-action-primary:hover {
            background: #ccfbf1;
        }

        .program-action-danger {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fecaca;
        }

        .program-action-danger:hover {
            background: #fee2e2;
        }

        .program-children-shell {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 0.55rem 0.65rem;
        }

        .program-children-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-top: 0.45rem;
        }

        .program-child-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 13.5rem;
            gap: 0.55rem;
            border: 1px solid #dbe3ef;
            background: #ffffff;
            border-radius: 0.8rem;
            padding: 0.6rem;
        }

        .program-child-main {
            display: grid;
            grid-template-columns: 1.5rem minmax(0, 1fr);
            gap: 0.55rem;
        }

        .program-child-icon {
            margin-top: 0.95rem;
            color: #64748b;
        }

        @media (min-width: 1280px) {
            .program-detail-grid {
                grid-template-columns: 1.35fr 1fr 1fr;
            }

            .program-child-card .program-detail-grid.compact {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1279px) {
            .program-point-body,
            .program-child-card {
                grid-template-columns: 1fr;
            }

            .program-actions-stack {
                grid-template-columns: 1fr;
            }

            .program-day-header,
            .program-editor-header {
                flex-direction: column;
            }

            .program-day-insurance-block {
                min-width: 0;
                width: 100%;
            }
        }

        @media (max-width: 767px) {
            .program-detail-grid,
            .program-detail-grid.compact,
            .program-settings-grid {
                grid-template-columns: 1fr;
            }

            .program-settings-inline {
                justify-content: flex-start;
            }

            .program-point-shell {
                grid-template-columns: 1fr;
            }

            .program-point-rail {
                flex-direction: row;
                border-right: 0;
                border-bottom: 1px solid #dbe3ef;
            }

            .program-point-drag {
                min-width: 3.5rem;
                min-height: 0;
                border-bottom: 0;
                border-right: 1px solid #dbe3ef;
            }

            .program-point-index {
                flex-direction: row;
                justify-content: flex-start;
                padding: 0.8rem 1rem;
            }

            .program-inline-empty {
                margin-top: 0.5rem;
                font-size: 0.72rem;
                color: #94a3b8;
                font-style: italic;
            }

            .program-toolbar-button {
                width: 100%;
            }

            .program-editor-header .flex.items-center.space-x-3 {
                width: 100%;
                flex-direction: column;
                gap: 0.75rem;
            }
        }

        @media (min-width: 1280px) {
            .program-action-link span {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (min-width: 1280px) {
            .program-point-item:hover .program-point-shell {
                box-shadow: 0 22px 42px -34px rgba(15, 23, 42, 0.6);
            }
        }
    </style>
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="show" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true">
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="show"
                class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        {{ $editPoint ? 'Edytuj punkt programu' : 'Dodaj punkt do programu' }}
                    </h3>
                    <div class="mt-4">
                        <form wire:submit.prevent="savePoint" class="space-y-4">
                            <div x-data="{
                                open: @entangle('searchDropdownOpen').live,
                                searchText: @entangle('searchProgramPoint').live,
                                selectedIndex: -1,
                                get maxIndex() {
                                    return Math.max(0, this.$el.querySelectorAll('[data-search-item]').length - 1);
                                },
                                init() {
                                    const observer = new MutationObserver(() => {
                                        this.checkAndOpenDropdown();
                                    });
                                    observer.observe(this.$el, { childList: true, subtree: true });
                                    
                                    window.addEventListener('livewire:updated', () => {
                                        this.checkAndOpenDropdown();
                                    });
                                    this.$nextTick(() => this.checkAndOpenDropdown());
                                },
                                checkAndOpenDropdown() {
                                    this.$nextTick(() => {
                                        const hasItems = this.$el.querySelectorAll('[data-search-item]').length > 0;
                                        const text = (this.searchText || '').trim();
                                        const canBrowse = text === '' || text.length >= 2;
                                        this.open = canBrowse && hasItems;
                                    });
                                },
                                selectNext() {
                                    if (this.selectedIndex < this.maxIndex) {
                                        this.selectedIndex++;
                                        this.scrollToSelected();
                                    }
                                },
                                selectPrev() {
                                    if (this.selectedIndex > 0) {
                                        this.selectedIndex--;
                                        this.scrollToSelected();
                                    } else if (this.selectedIndex === 0) {
                                        this.selectedIndex = -1;
                                    }
                                },
                                selectCurrent() {
                                    if (this.selectedIndex >= 0) {
                                        const items = this.$el.querySelectorAll('[data-search-item]');
                                        if (items[this.selectedIndex]) {
                                            items[this.selectedIndex].click();
                                        }
                                    }
                                },
                                scrollToSelected() {
                                    if (this.selectedIndex < 0) return;
                                    
                                    this.$nextTick(() => {
                                        const items = this.$el.querySelectorAll('[data-search-item]');
                                        const selectedItem = items[this.selectedIndex];
                                        
                                        if (selectedItem) {
                                            selectedItem.scrollIntoView({ 
                                                block: 'nearest', 
                                                behavior: 'instant'
                                            });
                                        }
                                    });
                                },
                                resetSelection() {
                                    this.selectedIndex = -1;
                                }
                            }" @click.away="open = false; resetSelection()" class="relative">
                                <label for="program_point_id" class="block text-sm font-medium text-gray-700 mb-1">Punkt programu</label>
                                <input type="text" 
                                    x-model="searchText"
                                    x-init="$el.focus()"
                                    @input.debounce.300ms="
                                        $wire.updateSearch($event.target.value);
                                        resetSelection();
                                        requestAnimationFrame(() => {
                                            checkAndOpenDropdown();
                                        });
                                    "
                                    @focus="
                                        checkAndOpenDropdown();
                                    "
                                    @keydown.down.prevent="
                                        if (open && maxIndex >= 0) {
                                            selectNext();
                                        }
                                    "
                                    @keydown.up.prevent="
                                        if (open) {
                                            selectPrev();
                                        }
                                    "
                                    @keydown.enter.prevent="
                                        if (open && selectedIndex >= 0) {
                                            selectCurrent();
                                        }
                                    "
                                    @keydown.escape="
                                        open = false;
                                        resetSelection();
                                        $event.target.blur();
                                    "
                                    placeholder="Wpisz min. 2 znaki albo kliknij, by przeglądać listę…"
                                    class="block w-full mb-2 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                    autocomplete="off" />
                                <input type="hidden" wire:model.live.debounce.500ms="modalData.program_point_id" id="program_point_id" />
                                
                                {{-- Debug info --}}
                                @if(config('app.debug'))
                                    <div class="text-xs text-gray-500 mb-2">
                                        Search: "{{ $searchProgramPoint }}" | Results: {{ count($availableProgramPoints) }} | Open: <span x-text="open"></span> | Selected: <span x-text="selectedIndex"></span>
                                    </div>
                                @endif

                                @php
                                    $searchTrimmed = trim((string) $searchProgramPoint);
                                    $showSearchResults = $searchTrimmed === '' || strlen($searchTrimmed) >= 2;
                                @endphp
                                
                                {{-- Lista wyników: browse (pusty input) albo filtr od 2 znaków --}}
                                @if($showSearchResults)
                                    <div x-show="open"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="results-container absolute z-50 bg-white border border-gray-300 rounded-lg shadow-lg w-full mt-1 pr-2"
                                         style="min-width: 100%; max-height: 420px; overflow-y: auto;"
                                         wire:key="search-results-{{ md5($searchProgramPoint) }}">
                                        <ul class="divide-y divide-gray-100">
                                            @forelse($availableProgramPoints as $index => $sdkPoint)
                                                <li data-search-item
                                                    data-index="{{ $index }}"
                                                    wire:key="search-item-{{ $sdkPoint->id }}-{{ $index }}"
                                                    @click="$wire.selectProgramPoint({{ $sdkPoint->id }}); open = false; searchText = ''; resetSelection()"
                                                    @mouseenter="selectedIndex = {{ $index }}"
                                                    class="px-4 py-3 cursor-pointer transition-colors"
                                                    :class="{
                                                        'bg-primary-100 font-semibold': {{ $sdkPoint->id == $modalData['program_point_id'] ? 'true' : 'false' }},
                                                        'bg-primary-50': selectedIndex === {{ $index }},
                                                        'hover:bg-primary-50': selectedIndex !== {{ $index }}
                                                    }">
                                                    @php
                                                        $searchKind = \App\Support\ProgramPointSearchDisplay::kindLabel($sdkPoint);
                                                        $searchMeta = \App\Support\ProgramPointSearchDisplay::metaLine($sdkPoint);
                                                        $searchSnippet = \App\Support\ProgramPointSearchDisplay::snippet($sdkPoint, 80);
                                                    @endphp
                                                    <div class="flex flex-col gap-0.5">
                                                        <div class="flex flex-wrap items-baseline gap-x-1.5">
                                                            <span @class([
                                                                'inline-flex rounded px-1 py-px text-[10px] font-semibold uppercase tracking-wide',
                                                                'bg-violet-100 text-violet-800' => str_starts_with($searchKind, 'Set'),
                                                                'bg-slate-100 text-slate-700' => $searchKind === 'Podpunkt',
                                                                'bg-sky-100 text-sky-800' => $searchKind === 'Punkt',
                                                            ])>{{ $searchKind }}</span>
                                                            <span class="font-medium text-gray-900">{{ $sdkPoint->name }}</span>
                                                        </div>
                                                        @if($searchMeta !== '')
                                                            <span class="text-xs text-gray-600">{{ $searchMeta }}</span>
                                                        @endif
                                                        @if($searchSnippet)
                                                            <span class="text-xs text-gray-500">{{ $searchSnippet }}</span>
                                                        @endif
                                                    </div>
                                                </li>
                                            @empty
                                                <li class="px-4 py-3 text-gray-500 italic text-center" wire:key="no-results">
                                                    @if($searchTrimmed === '')
                                                        Brak punktów programu w bazie.
                                                    @else
                                                        Brak punktów pasujących do: "{{ $searchProgramPoint }}"
                                                    @endif
                                                </li>
                                            @endforelse
                                            @if($availableProgramPoints->count() >= 50)
                                                <li class="px-4 py-2 text-xs text-gray-500 bg-gray-50 text-center border-t" wire:key="limit-info">
                                                    Pokazano pierwszych 50 wyników. Sprecyzuj wyszukiwanie dla lepszych rezultatów.
                                                </li>
                                            @endif
                                            @if($availableProgramPoints->count() > 0)
                                                <li class="px-4 py-2 text-xs text-gray-400 bg-gray-50 text-center border-t" wire:key="keyboard-help">
                                                    ↑↓ przewijaj | Enter wybierz | Esc zamknij
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                @elseif($searchTrimmed !== '')
                                    <p class="text-xs text-gray-500 mt-1">Wpisz jeszcze co najmniej {{ max(1, 2 - strlen($searchTrimmed)) }} znak(i), aby filtrować.</p>
                                @endif
                                
                                @if($modalData['program_point_id'] && $selectedProgramPointName)
                                    <div class="text-xs text-green-700 mt-1">Wybrano: <span class="font-semibold">{{ $selectedProgramPointName }}</span></div>
                                @endif
                                @error('modalData.program_point_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            @if($modalData['program_point_id'] && $selectedProgramPointName)
                                <div class="p-3 bg-gray-50 rounded border border-gray-200">
                                    <p class="text-sm"><span class="font-semibold">Wybrany punkt:</span>
                                        {{ $selectedProgramPointName }}</p>
                                    @if($selectedProgramPointDescription)
                                        <p class="text-xs text-gray-600 mt-1">{{ $selectedProgramPointDescription }}</p>
                                    @endif
                                </div>
                            @endif
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Godzina startu (opcjonalnie)</label>
                                    <input type="time" wire:model.live.debounce.500ms="modalData.start_time" id="start_time"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    @error('modalData.start_time') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">Godzina końca (opcjonalnie)</label>
                                    <input type="time" wire:model.live.debounce.500ms="modalData.end_time" id="end_time"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    @error('modalData.end_time') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notatki</label>
                                <textarea wire:model.live.debounce.500ms="modalData.notes" id="notes"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    rows="3"></textarea>
                                @error('modalData.notes') <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-slate-50 p-3">
                                <p class="mb-2 text-sm font-semibold text-gray-800">Gdzie ma być ten punkt</p>
                                <p class="mb-3 text-xs text-gray-500">Domyślnie w programie i w kosztach.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.live.debounce.500ms="modalData.include_in_program"
                                        id="include_in_program"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <span class="ml-2 text-sm font-medium text-gray-800">W programie</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.live.debounce.500ms="modalData.include_in_calculation"
                                        id="include_in_calculation"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <span class="ml-2 text-sm font-medium text-gray-800">W kosztach</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.live.debounce.500ms="modalData.active" id="active"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Aktywny</span>
                                </label>
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 sm:col-start-2 sm:text-sm">
                                    Zapisz
                                </button>
                                <button type="button" wire:click="closeModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:col-start-1 sm:text-sm">
                                    Anuluj
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
@include('filament.components.filament-sortable-boot')
<script>
    window.sortableInstances = window.sortableInstances || [];

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('notify', (event) => {
            const notificationData = event[0] || event;
            showNotification(notificationData.message, notificationData.type);
        });

        Livewire.on('program-point-saved', (message) => {
            showNotification(message, 'success');
        });

        Livewire.on('program-point-deleted', (message) => {
            showNotification(message, 'success');
        });

        Livewire.on('program-point-error', (message) => {
            showNotification(message, 'error');
        });

        Livewire.on('program-updated', (message) => {
            showNotification(message, 'success');
        });
    });

    function showNotification(message, type = 'info') {
        const container = document.getElementById('notifications');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `mb-2 p-3 rounded-md shadow-lg max-w-sm ${type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' :
                type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' :
                    'bg-blue-50 text-blue-800 border border-blue-200'
            }`;
        notification.innerHTML = `
        <div class="flex justify-between items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-500 hover:text-gray-700">×</button>
        </div>
    `;
        container.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    function destroyAllSortables() {
        window.sortableInstances.forEach(instance => {
            try {
                if (instance && typeof instance.destroy === 'function') {
                    instance.destroy();
                }
            } catch (e) {
                // Ignore errors during cleanup
            }
        });
        window.sortableInstances = [];
    }

    function initializeSortable() {
        if (typeof Sortable === 'undefined') {
            setTimeout(initializeSortable, 50);
            return;
        }

        const daysContainer = document.getElementById('program-days-container');
        if (!daysContainer) return;

        destroyAllSortables();

        const dayLists = daysContainer.querySelectorAll('.program-day-list');
        if (dayLists.length === 0) return;

        dayLists.forEach((listEl) => {
            try {
                const sortableInstance = new Sortable(listEl, {
                    group: 'program-points',
                    animation: 150,
                    handle: '.drag-handle',
                    draggable: '.program-point-item',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    dataIdAttr: 'data-id',
                    swapThreshold: 0.65,
                    invertSwap: false,
                    direction: 'vertical',
                    removeCloneOnHide: true,
                    emptyInsertThreshold: 5,
                    preventOnFilter: true,
                    revertOnSpill: false,
                    onStart: function(evt) {
                        try {
                            document.querySelectorAll('.program-day-list').forEach(list => {
                                list.classList.add('sortable-over');
                            });
                        } catch (e) {}
                    },
                    onMove: function(evt) {
                        try {
                            return true;
                        } catch (e) {
                            return false;
                        }
                    },
                    onEnd: function (evt) {
                        try {
                        console.log('🎯 Drag ended:', {
                            item: evt.item.dataset.pivotId,
                            fromDay: evt.from.dataset.dayId,
                            toDay: evt.to.dataset.dayId,
                            oldIndex: evt.oldIndex,
                            newIndex: evt.newIndex,
                            timestamp: new Date().toISOString()
                        });

                        // Remove visual feedback
                        document.querySelectorAll('.program-day-list').forEach(list => {
                            list.classList.remove('sortable-over');
                        });

                        let orderedPivotsPerDay = {};
                        daysContainer.querySelectorAll('.program-day-list').forEach(dayList => {
                            const dayId = dayList.dataset.dayId;
                            orderedPivotsPerDay[dayId] = [];
                            dayList.querySelectorAll('.program-point-item').forEach(item => {
                                const pivotId = item.dataset.pivotId;
                                if (pivotId) {
                                    orderedPivotsPerDay[dayId].push(pivotId);
                                }
                            });
                        });

                        console.log('📤 Updating program order:', orderedPivotsPerDay);
                        
                        // Use Livewire.find() to get the correct component
                        const wireIdElement = listEl.closest('[wire\\:id]');
                        console.log('Looking for Livewire component, wireIdElement:', wireIdElement);
                        
                        
                        if (!wireIdElement) {
                            const livewireComponents = Livewire.all();
                            if (livewireComponents.length > 0) {
                                livewireComponents[0].call('updateProgramOrder', orderedPivotsPerDay);
                            }
                            return;
                        }
                        
                        const wireId = wireIdElement.getAttribute('wire:id');
                        const component = Livewire.find(wireId);
                        
                        if (component) {
                            component.call('updateProgramOrder', orderedPivotsPerDay);
                        }
                        } catch (error) {
                            setTimeout(() => {
                                initializeSortable();
                            }, 500);
                        }
                    }
                });

                window.sortableInstances.push(sortableInstance);
            } catch (error) {
                // Ignore initialization errors
            }
        });
    }
    
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initializeSortable, 100);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initializeSortable, 100);
        });
    }
    
    document.addEventListener('livewire:updated', function () {
        setTimeout(initializeSortable, 150);
    });
    
    document.addEventListener('livewire:morphed', function () {
        setTimeout(initializeSortable, 150);
    });

    document.addEventListener('livewire:navigated', function () {
        setTimeout(initializeSortable, 150);
    });
</script>
@endpush

</div>