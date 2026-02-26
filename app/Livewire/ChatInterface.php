<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Attributes\On;

class ChatInterface extends Component
{
    public ?int $selectedConversationId = null;
    public string $newMessage = '';
    public string $newConversationTitle = '';
    public array $selectedUsers = [];
    public bool $showNewConversationModal = false;
    public string $searchTerm = '';
    public string $userSearch = '';

    public function mount(?int $conversationId = null)
    {
        Log::info('ChatInterface mount - START', [
            'conversationId' => $conversationId, 
            'class' => static::class,
            'methods' => get_class_methods($this)
        ]);
        
        $this->selectedConversationId = $conversationId;
        $this->newMessage = '';
        $this->newConversationTitle = '';
        $this->selectedUsers = [];
        $this->searchTerm = '';
        $this->userSearch = '';
        $this->showNewConversationModal = false;
    }

    public function selectConversation(int $conversationId)
    {
        $this->selectedConversationId = $conversationId;
        
        // Oznacz konwersację jako przeczytaną
        $conversation = Conversation::find($conversationId);
        if ($conversation) {
            $conversation->markAsRead(Auth::user());
            // Wyczyść cache powiadomień dla tego użytkownika
            NotificationService::clearCacheForUser(Auth::id());
            $this->dispatch('refresh-notifications');
        }
        
        $this->dispatch('conversation-selected', $conversationId);
    }

    public function sendMessage()
    {
        // Walidacja wiadomości
        $this->validate([
            'newMessage' => 'required|string|min:1|max:2000',
        ], [
            'newMessage.required' => 'Wiadomość nie może być pusta.',
            'newMessage.max' => 'Wiadomość nie może być dłuższa niż 2000 znaków.',
        ]);

        if (!$this->selectedConversationId) {
            session()->flash('error', 'Nie wybrano konwersacji.');
            return;
        }

        $conversation = Conversation::find($this->selectedConversationId);
        if (!$conversation) {
            session()->flash('error', 'Konwersacja nie została znaleziona.');
            return;
        }

        // Sprawdź czy użytkownik jest uczestnikiem konwersacji
        if (!$conversation->participants()->where('user_id', Auth::id())->exists()) {
            session()->flash('error', 'Nie masz uprawnień do pisania w tej konwersacji.');
            return;
        }

        try {
            Message::create([
                'conversation_id' => $this->selectedConversationId,
                'user_id' => Auth::id(),
                'content' => trim($this->newMessage),
            ]);

            // Aktualizuj last_message_at w konwersacji
            $conversation->update(['last_message_at' => now()]);

            // Wyczyść cache powiadomień dla wszystkich uczestników
            $participantIds = $conversation->participants()->pluck('user_id')->toArray();
            foreach ($participantIds as $userId) {
                NotificationService::clearCacheForUser($userId);
            }

            $this->newMessage = '';
            $this->dispatch('message-sent');
            $this->dispatch('refresh-notifications');
            
            // Przewiń do dołu po wysłaniu wiadomości
            $this->dispatch('scroll-to-bottom');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Wystąpił błąd podczas wysyłania wiadomości.');
            Log::error('Chat message send error: ' . $e->getMessage());
        }
    }

    public function startNewConversation()
    {
        Log::info('startNewConversation called by user: ' . Auth::id());
        $this->showNewConversationModal = true;
        $this->newConversationTitle = '';
        $this->selectedUsers = [];
        $this->userSearch = '';
        Log::info('Modal should be shown now, showNewConversationModal = ' . ($this->showNewConversationModal ? 'true' : 'false'));
    }

    public function createConversation()
    {
        Log::info('createConversation called', [
            'title' => $this->newConversationTitle,
            'selectedUsers' => $this->selectedUsers
        ]);

        $this->validate([
            'newConversationTitle' => 'required|string|min:1|max:255',
            'selectedUsers' => 'required|array|min:1',
            'selectedUsers.*' => 'exists:users,id',
        ], [
            'newConversationTitle.required' => 'Tytuł rozmowy jest wymagany.',
            'newConversationTitle.max' => 'Tytuł rozmowy nie może być dłuższy niż 255 znaków.',
            'selectedUsers.required' => 'Musisz wybrać przynajmniej jednego uczestnika.',
            'selectedUsers.min' => 'Musisz wybrać przynajmniej jednego uczestnika.',
        ]);

        try {
            // Określ typ rozmowy
            $type = count($this->selectedUsers) === 1 ? 'private' : 'group';

            $conversation = Conversation::create([
                'title' => trim($this->newConversationTitle),
                'type' => $type,
                'created_by' => Auth::id(),
                'last_message_at' => now(),
            ]);

            Log::info('Conversation created', ['id' => $conversation->id]);

            // Dodaj twórcy jako uczestnika
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => Auth::id(),
                'joined_at' => now(),
            ]);

            // Dodaj wybranych użytkowników
            foreach ($this->selectedUsers as $userId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'joined_at' => now(),
                ]);
            }

            // Wyczyść cache powiadomień dla wszystkich uczestników
            NotificationService::clearCacheForUser(Auth::id());
            foreach ($this->selectedUsers as $userId) {
                NotificationService::clearCacheForUser($userId);
            }

            $this->selectedConversationId = $conversation->id;
            $this->showNewConversationModal = false;
            $this->newConversationTitle = '';
            $this->selectedUsers = [];
            $this->userSearch = '';
            
            $this->dispatch('conversation-created', $conversation->id);
            $this->dispatch('refresh-notifications');

            session()->flash('message', 'Rozmowa została utworzona pomyślnie.');
            Log::info('Conversation creation completed successfully');
            
        } catch (\Exception $e) {
            Log::error('Error creating conversation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Wystąpił błąd podczas tworzenia rozmowy: ' . $e->getMessage());
        }
    }

    public function getConversationsProperty()
    {
        return Conversation::whereHas('participants', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->with(['participants', 'lastMessage.user'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getFilteredConversationsProperty()
    {
        $user = Auth::user();
        if (!$user) {
            return collect();
        }

        return $this->conversations
            ->filter(function ($conversation) {
                return stripos($conversation->getDisplayName(Auth::user()), $this->searchTerm) !== false;
            });
    }

    public function getSelectedConversationProperty()
    {
        if (!$this->selectedConversationId) {
            return null;
        }
        return Conversation::with('participants')->find($this->selectedConversationId);
    }

    public function getMessagesProperty()
    {
        if (!$this->selectedConversationId) {
            return collect();
        }

        return Message::with('user')
            ->where('conversation_id', $this->selectedConversationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getAvailableUsersProperty()
    {
        $users = User::where('id', '!=', Auth::id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
            
        Log::info('Available users count: ' . $users->count());
        return $users;
    }

    public function getFilteredAvailableUsersProperty()
    {
        if (empty($this->userSearch)) {
            return $this->availableUsers;
        }
        $needle = mb_strtolower($this->userSearch);
        return $this->availableUsers->filter(function ($user) use ($needle) {
            return mb_stripos(mb_strtolower($user->name), $needle) !== false;
        });
    }

    #[On('refresh-chat')]
    public function refreshChat()
    {
        // Metoda do odświeżania czatu (można używać z JavaScript)
    }

    public function render()
    {
        return view('livewire.chat-interface', [
            'conversations' => $this->filteredConversations,
            'selectedConversation' => $this->selectedConversation,
            'messages' => $this->messages,
            'availableUsers' => $this->filteredAvailableUsers,
        ]);
        // Prosty widok był tylko do testów: view('livewire.chat-interface-simple');
    }
}
