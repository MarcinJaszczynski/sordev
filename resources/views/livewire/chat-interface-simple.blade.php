<div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Chat Interface</h2>
    </div>
    
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div class="text-gray-600 dark:text-gray-400">
                <span class="font-medium">Selected ID:</span> {{ $selectedConversationId ?? 'null' }}
            </div>
            <div class="text-gray-600 dark:text-gray-400">
                <span class="font-medium">Message:</span> '{{ $newMessage }}'
            </div>
            <div class="text-gray-600 dark:text-gray-400">
                <span class="font-medium">Modal:</span> {{ $showNewConversationModal ? 'true' : 'false' }}
            </div>
        </div>
        
        <div class="flex justify-start">
            <button 
                wire:click="startNewConversation" 
                class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors duration-200"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nowy czat
            </button>
        </div>
        
        @if($showNewConversationModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-md mx-4 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nowa rozmowa</h3>
                        <button 
                            wire:click="$set('showNewConversationModal', false)" 
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tytuł rozmowy</label>
                            <input 
                                wire:model="newConversationTitle" 
                                placeholder="Wprowadź tytuł rozmowy..." 
                                class="w-full px-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                            >
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button 
                            wire:click="$set('showNewConversationModal', false)" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Anuluj
                        </button>
                        <button 
                            wire:click="createConversation" 
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            Utwórz rozmowę
                        </button>
                    </div>
                </div>
            </div>
        @endif
        
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Formularz Wiadomości</h3>
            <form wire:submit="sendMessage" class="space-y-4">
                <div>
                    <textarea 
                        wire:model="newMessage" 
                        placeholder="Wpisz wiadomość..." 
                        class="w-full px-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"
                        rows="3"
                    ></textarea>
                </div>
                <div class="flex justify-end">
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 text-white font-medium rounded-lg transition-colors disabled:cursor-not-allowed"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                    >
                        <span wire:loading.remove wire:target="sendMessage">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Wyślij
                        </span>
                        <span wire:loading wire:target="sendMessage" class="flex items-center">
                            <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Wysyłanie...
                        </span>
                    </button>
                </div>
            </form>
            
            @if (session()->has('error'))
                <div class="mt-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif
        </div>
        
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                Rozmowy ({{ count($this->conversations) }})
            </h3>
            <div class="space-y-2">
                @forelse($this->conversations as $conv)
                    <div class="p-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800 {{ $selectedConversationId === $conv->id ? 'bg-primary-50 dark:bg-primary-900/30 border-primary-200 dark:border-primary-700' : '' }}" 
                         wire:click="selectConversation({{ $conv->id }})">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $conv->title }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $conv->id }}</span>
                        </div>
                        @if($conv->lastMessage)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ Str::limit($conv->lastMessage->content, 50) }}</p>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-sm">Brak rozmów</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
