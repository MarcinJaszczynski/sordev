<x-filament-widgets::widget>
<div class="p-4 border rounded bg-white">
    <h3 class="text-lg font-bold mb-2">Szablony wydarzeń wykorzystujące ten punkt programu</h3>
    @if($this->eventTemplates->isEmpty())
        <div class="text-gray-500">Ten punkt programu nie jest wykorzystywany w żadnym szablonie wydarzenia.</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nazwa szablonu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcje</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->eventTemplates as $template)
                        <tr wire:key="etfp-{{ $template->id }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $template->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $template->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('filament.admin.resources.event-templates.edit', ['record' => $template->id]) }}" 
                                   class="text-primary-600 hover:text-primary-900 mr-3">
                                    Edytuj szablon
                                </a>
                                <a href="{{ route('filament.admin.resources.event-templates.edit-program', ['record' => $template->id]) }}" 
                                   class="text-blue-600 hover:text-blue-900">
                                    Edytuj program
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</x-filament-widgets::widget>
