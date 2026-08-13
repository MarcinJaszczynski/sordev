<x-filament-panels::page>
    <style>
        .sor-help-prose table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.875rem;
        }
        .sor-help-prose th,
        .sor-help-prose td {
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0.75rem;
            text-align: left;
            vertical-align: top;
        }
        .sor-help-prose th {
            background: #f8fafc;
            font-weight: 600;
        }
        .sor-help-prose h2 {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
            font-weight: 700;
        }
        .sor-help-prose h2:first-child {
            margin-top: 0;
        }
        .sor-help-prose ul,
        .sor-help-prose ol {
            margin: 0.5rem 0 0.75rem 1.25rem;
            list-style: disc;
        }
        .sor-help-prose ol {
            list-style: decimal;
        }
        .sor-help-prose p {
            margin: 0.5rem 0;
        }
        .sor-help-prose strong {
            font-weight: 600;
        }
    </style>

    <div class="sor-help-center grid gap-6 lg:grid-cols-12">
        <aside class="lg:col-span-4 xl:col-span-3">
            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <label for="help-search" class="mb-1 block text-sm font-medium text-gray-700">Szukaj</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="help-search"
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="np. wpłata, umowa, status…"
                        />
                    </x-filament::input.wrapper>
                </div>

                @forelse ($this->articlesByCategory() as $category => $articles)
                    <div>
                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ $category }}
                        </h2>
                        <ul class="space-y-1">
                            @foreach ($articles as $item)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectArticle(@js($item->slug))"
                                        @class([
                                            'w-full rounded-lg px-3 py-2 text-left text-sm transition',
                                            'bg-primary-50 text-primary-900 ring-1 ring-primary-200' => $this->article === $item->slug,
                                            'text-gray-700 hover:bg-gray-50' => $this->article !== $item->slug,
                                        ])
                                    >
                                        <span class="font-medium">{{ $item->title }}</span>
                                        @if ($item->summary !== '')
                                            <span class="mt-0.5 block text-xs text-gray-500 line-clamp-2">
                                                {{ $item->summary }}
                                            </span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Brak wyników dla podanego hasła.</p>
                @endforelse
            </div>
        </aside>

        <section class="lg:col-span-8 xl:col-span-9">
            @php($selected = $this->selectedArticle())
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                @if ($selected)
                    <header class="mb-5 border-b border-gray-100 pb-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ $selected->category }}
                        </p>
                        <h2 class="mt-1 text-xl font-bold text-gray-900 sm:text-2xl">
                            {{ $selected->title }}
                        </h2>
                        @if ($selected->summary !== '')
                            <p class="mt-2 text-sm text-gray-600">{{ $selected->summary }}</p>
                        @endif
                        @if ($selected->tags !== [])
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($selected->tags as $tag)
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                        {{ $tag }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </header>

                    <article class="sor-help-prose max-w-none text-sm text-gray-800">
                        {!! $this->selectedArticleHtml() !!}
                    </article>
                @else
                    <p class="text-sm text-gray-500">
                        Wybierz scenariusz z listy po lewej stronie.
                    </p>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
