<div class="min-h-[80vh] flex flex-col bg-gray-50 dark:bg-gray-900" 
     x-data="{ 
         scrollToBottom() { 
             const container = document.getElementById('messages-container'); 
             if (container) { 
                 container.scrollTop = container.scrollHeight; 
             } 
         } 
     }" 
     x-init="scrollToBottom()" 
     @scroll-to-bottom.window="scrollToBottom()" 
     @message-sent.window="scrollToBottom()">
    
    <div class="flex flex-1 overflow-hidden rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 mt-8 mx-auto max-w-6xl w-full bg-white dark:bg-gray-900">
        <!-- Lewy panel: lista czatów -->
        <aside class="w-80 min-w-[280px] max-w-xs bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex flex-col">
            <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <button 
                    wire:click="startNewConversation" 
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-semibold shadow-sm transition-colors duration-200 text-sm" 
                    title="Nowy czat"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nowy czat
                </button>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="searchTerm" 
                    placeholder="Szukaj rozmów..." 
                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                />
            </div>
            
            <nav class="flex-1 overflow-y-auto">
                @forelse($this->filteredConversations as $conversation)
                    @php 
                        $isUnread = $conversation->unreadCount(auth()->user()) > 0; 
                        $otherUser = $conversation->participants->where('id', '!=', auth()->id())->first(); 
                    @endphp
                    <div wire:click="selectConversation({{ $conversation->id }})"
                        class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $selectedConversationId === $conversation->id ? 'bg-primary-50 dark:bg-primary-900/30' : '' }}">
                        
                        <div class="relative flex-shrink-0">
                            @if($conversation->type === 'group')
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                            @else
                                @php 
                                    $initials = $otherUser ? strtoupper(substr($otherUser->name, 0, 2)) : 'UN'; 
                                    $colors = ['bg-red-500', 'bg-primary-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-blue-500', 'bg-orange-500']; 
                                    $color = $colors[($otherUser->id ?? 0) % count($colors)]; 
                                @endphp
                                <div class="w-10 h-10 {{ $color }} rounded-full flex items-center justify-center text-white font-bold text-sm">
                                    {{ $initials }}
                                </div>
                                @if($otherUser && $otherUser->isOnline())
                                    <span class="absolute w-3 h-3 bg-green-400 border-2 border-white dark:border-gray-900 rounded-full -bottom-0.5 -right-0.5"></span>
                                @endif
                            @endif
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-center">
                                <span class="truncate font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $conversation->getDisplayName(auth()->user()) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                    @if($conversation->last_message_at)
                                        {{ $conversation->last_message_at->isToday() ? $conversation->last_message_at->format('H:i') : $conversation->last_message_at->format('d.m') }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-0.5">
                                <span class="truncate text-xs {{ $isUnread ? 'font-semibold text-primary-700 dark:text-primary-200' : 'text-gray-500 dark:text-gray-400' }}">
                                    @if($conversation->lastMessage)
                                        @if($conversation->lastMessage->user_id === auth()->id())
                                            <span class="text-primary-600 font-medium">Ty:</span>
                                        @endif
                                        {{ Str::limit($conversation->lastMessage->content, 32) }}
                                    @else
                                        <span class="italic text-gray-400 dark:text-gray-500">Brak wiadomości</span>
                                    @endif
                                </span>
                                @if($isUnread)
                                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-primary-600 rounded-full">
                                        {{ $conversation->unreadCount(auth()->user()) > 9 ? '9+' : $conversation->unreadCount(auth()->user()) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                        <svg class="w-10 h-10 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <div class="text-sm">Brak rozmów</div>
                    </div>
                @endforelse
            </nav>
        </aside>
        
        <!-- Środkowy panel: rozmowa -->
        <main class="flex-1 flex flex-col bg-white dark:bg-gray-900">
            @if($selectedConversationId && $this->selectedConversation)
                <header class="flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    @if($this->selectedConversation->type === 'group')
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    @else
                        @php 
                            $otherUser = $this->selectedConversation->participants->where('id', '!=', auth()->id())->first(); 
                            $initials = $otherUser ? strtoupper(substr($otherUser->name, 0, 2)) : 'UN'; 
                            $colors = ['bg-red-500', 'bg-primary-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-blue-500', 'bg-orange-500']; 
                            $color = $colors[($otherUser->id ?? 0) % count($colors)]; 
                        @endphp
                        <div class="w-10 h-10 {{ $color }} rounded-full flex items-center justify-center text-white font-bold text-sm relative">
                            {{ $initials }}
                            @if($otherUser && $otherUser->isOnline())
                                <span class="absolute w-3 h-3 bg-green-400 border-2 border-white dark:border-gray-900 rounded-full -bottom-0.5 -right-0.5"></span>
                            @endif
                        </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $this->selectedConversation->getDisplayName(auth()->user()) }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if($this->selectedConversation->type === 'group')
                                {{ $this->selectedConversation->participants->count() }} uczestników
                            @else
                                @if($otherUser && $otherUser->isOnline())
                                    <span class="text-green-500 font-medium">● Online</span>
                                @else 
                                    Ostatnio {{ $otherUser?->updated_at?->diffForHumans() ?? 'dawno' }}
                                @endif
                            @endif
                        </div>
                    </div>
                </header>
                
                <section class="flex-1 overflow-y-auto px-6 py-4 bg-gray-50 dark:bg-gray-800 space-y-4" id="messages-container">
                    @forelse($this->messages as $message)
                        <div class="flex {{ $message->user_id === auth()->id() ? 'justify-end' : 'justify-start' }} group">
                            <div class="flex items-end space-x-2 max-w-lg">
                                @if($message->user_id !== auth()->id())
                                    <div class="w-8 h-8 rounded-full bg-primary-200 dark:bg-primary-800 flex items-center justify-center text-primary-700 dark:text-primary-200 font-bold text-xs">
                                        {{ strtoupper(substr($message->user->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div class="relative">
                                    <div class="px-4 py-2 rounded-2xl shadow-sm text-sm break-words {{ $message->user_id === auth()->id() ? 'bg-primary-600 text-white rounded-br-md' : 'bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-gray-100 rounded-bl-md' }}">
                                        @if($this->selectedConversation->type === 'group' && $message->user_id !== auth()->id())
                                            <span class="block text-xs font-medium text-primary-600 dark:text-primary-400 mb-1">{{ $message->user->name }}</span>
                                        @endif
                                        {{ $message->content }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 {{ $message->user_id === auth()->id() ? 'text-right' : 'text-left' }} opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        {{ $message->created_at->format('H:i') }}@if(!$message->created_at->isToday()) · {{ $message->created_at->format('d.m') }}@endif
                                    </div>
                                </div>
                                @if($message->user_id === auth()->id())
                                    <div class="w-8 h-8 rounded-full bg-primary-200 dark:bg-primary-800 flex items-center justify-center text-primary-700 dark:text-primary-200 font-bold text-xs">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 dark:text-gray-400 py-16">
                            <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <div class="text-base font-medium">Brak wiadomości</div>
                            <div class="text-sm text-gray-400 dark:text-gray-500">Wyślij pierwszą wiadomość aby rozpocząć rozmowę</div>
                        </div>
                    @endforelse
                </section>
                
                <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4">
                    @if (session()->has('error'))
                        <div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg text-sm">
                            {{ session('error') }}
                        </div>
                    @endif
                    
                    <form wire:submit="sendMessage" class="flex items-end gap-3">
                        <div class="flex-1">
                            <textarea 
                                wire:model="newMessage" 
                                wire:keydown.enter.prevent="sendMessage" 
                                wire:keydown.shift.enter.prevent="$set('newMessage', $event.target.value + '\n')" 
                                placeholder="Napisz wiadomość... (Enter - wyślij, Shift+Enter - nowa linia)" 
                                rows="1" 
                                class="w-full px-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none overflow-hidden" 
                                style="min-height: 44px; max-height: 120px;" 
                                oninput="this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px';"
                            ></textarea>
                            @error('newMessage')
                                <div class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <button 
                            type="submit" 
                            class="flex items-center justify-center w-11 h-11 bg-primary-600 hover:bg-primary-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 text-white rounded-lg transition-colors duration-200 disabled:cursor-not-allowed" 
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            title="Wyślij wiadomość"
                        >
                            <span wire:loading.remove wire:target="sendMessage">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </span>
                            <span wire:loading wire:target="sendMessage">
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </form>
                </footer>
            @else
                <!-- Placeholder: nie wybrano rozmowy -->
                <div class="flex-1 flex flex-col items-center justify-center text-center bg-white dark:bg-gray-900">
                    <svg class="w-48 h-48 mx-auto mb-6 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Nie wybrano rozmowy</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Wybierz rozmowę z listy lub rozpocznij nową, aby rozpocząć czat.</div>
                </div>
            @endif
        </main>
    </div>
    
    <!-- Modal nowej konwersacji -->
    @if($showNewConversationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showNewConversationModal', false)">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-md mx-4 p-6 relative" @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nowa rozmowa</h3>
                    <button 
                        wire:click="$set('showNewConversationModal', false)" 
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800" 
                        title="Zamknij"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tytuł rozmowy</label>
                        <input 
                            type="text" 
                            wire:model="newConversationTitle" 
                            placeholder="Wprowadź tytuł rozmowy..." 
                            class="w-full px-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                        />
                        @error('newConversationTitle')
                            <div class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Uczestnicy</label>
                        <input 
                            type="text" 
                            wire:model.live="userSearch" 
                            placeholder="Szukaj użytkownika..." 
                            class="w-full px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 mb-3" 
                        />
                        <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-gray-50 dark:bg-gray-800">
                            @forelse($this->filteredAvailableUsers as $user)
                                <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="selectedUsers" 
                                        value="{{ $user->id }}" 
                                        class="w-4 h-4 text-primary-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 focus:ring-2" 
                                    />
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                                        @if($user->isOnline())
                                            <span class="inline-block w-2 h-2 bg-green-400 rounded-full"></span>
                                        @endif
                                    </div>
                                </label>
                            @empty
                                <div class="text-center text-gray-500 dark:text-gray-400 py-4">
                                    <span class="text-sm">Brak dostępnych użytkowników</span>
                                </div>
                            @endforelse
                        </div>
                        @error('selectedUsers')
                            <div class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button 
                        wire:click="$set('showNewConversationModal', false)" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Anuluj
                    </button>
                    <button 
                        wire:click="createConversation" 
                        wire:loading.attr="disabled"
                        wire:target="createConversation"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:bg-primary-400 dark:disabled:bg-primary-500 rounded-lg transition-colors disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="createConversation">Utwórz rozmowę</span>
                        <span wire:loading wire:target="createConversation">Tworzenie...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
