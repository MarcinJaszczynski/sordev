<?php

namespace App\Console\Commands;

use App\Support\Seo\LlmsTxtBuilder;
use Illuminate\Console\Command;

class GenerateLlmsTxtCommand extends Command
{
    protected $signature = 'seo:llms-txt
                            {--clear : Tylko wyczyść cache llms.txt}
                            {--write : Zapisz snapshot do storage/app/llms.txt (nie do public/ — unikamy omijania routingu)}';

    protected $description = 'Odświeża cache /llms.txt (katalog AI: wycieczka × miasto wyjazdu)';

    public function handle(LlmsTxtBuilder $builder): int
    {
        if ($this->option('clear')) {
            LlmsTxtBuilder::flushCache();
            $this->info('Wyczyszczono cache llms.txt.');

            return self::SUCCESS;
        }

        LlmsTxtBuilder::flushCache();
        $body = $builder->toString();

        $bytes = strlen($body);
        $connections = 0;
        if (preg_match('/# Połączeń w katalogu: (\d+)/u', $body, $m)) {
            $connections = (int) $m[1];
        }

        if ($this->option('write')) {
            $path = storage_path('app/llms.txt');
            file_put_contents($path, $body);
            $this->info("Zapisano {$path} ({$bytes} B, połączeń: {$connections}).");
        } else {
            $this->info("Odświeżono cache llms.txt ({$bytes} B, połączeń: {$connections}).");
        }

        return self::SUCCESS;
    }
}
