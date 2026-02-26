<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowLastPriceRecalcReport extends Command
{
    protected $signature = 'prices:last-report {--user=1}';
    protected $description = 'Pokaż ostatnie podsumowanie przeliczenia cen (z powiadomień w bazie)';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        // Laravel domyślnie przechowuje powiadomienia w tabeli notifications z kolumną data (JSON)
        try {
            $row = DB::table('notifications')
                ->where('notifiable_id', $userId)
                ->orderByDesc('created_at')
                ->first();
            if (!$row) {
                $this->warn('Brak powiadomień dla tego użytkownika.');
                return 0;
            }
            $data = json_decode($row->data ?? '{}', true);
            $title = $data['title'] ?? ($data['message'] ?? 'Powiadomienie');
            $body = $data['body'] ?? ($data['content'] ?? json_encode($data));
            $this->info($title);
            $this->line($body);
            $this->line('Czas: ' . ($row->created_at ?? '')); 
            return 0;
        } catch (\Throwable $e) {
            $this->error('Nie udało się odczytać powiadomień: ' . $e->getMessage());
            return 1;
        }
    }
}
