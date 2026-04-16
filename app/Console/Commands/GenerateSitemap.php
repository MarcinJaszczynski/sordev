<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\Place;
use App\Models\EventTemplate;
use App\Models\BlogPost;
use Illuminate\Support\Facades\URL as URLFacade;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--base-url= : Publiczny adres serwisu, np. https://example.com}';
    protected $description = 'Generate XML sitemap for the website';

    public function handle()
    {
        $this->info('Generowanie sitemapy...');

        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.public_url', config('app.url'))), '/');
        $host = (string) (parse_url($baseUrl, PHP_URL_HOST) ?? '');

        if ($baseUrl === '' || $host === '' || $this->isLoopbackHost($host)) {
            $this->error('❌ Ustaw publiczny adres serwisu (APP_PUBLIC_URL/APP_URL) lub podaj --base-url, bo aktualny wskazuje na localhost/127.0.0.1.');
            return 1;
        }

        URLFacade::forceRootUrl($baseUrl);

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if (is_string($scheme) && in_array($scheme, ['http', 'https'], true)) {
            URLFacade::forceScheme($scheme);
        }

        $sitemap = Sitemap::create();

        // Pobierz pierwszy region do generowania URLs
        $defaultRegion = Place::first();
        if (!$defaultRegion) {
            $this->error('❌ Brak regionów w bazie danych!');
            return 1;
        }
        
        $defaultSlug = \Illuminate\Support\Str::slug($defaultRegion->name);

        // Dodaj stronę główną (z domyślnym regionem)
        $sitemap->add(Url::create(route('home', ['regionSlug' => $defaultSlug]))
            ->setLastModificationDate(now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0));

        // Dodaj strony regionalne (dla każdego regionu)
        $places = Place::limit(50)->get(); // Limit 50 regionów dla sitemapy
        foreach ($places as $place) {
            $slug = \Illuminate\Support\Str::slug($place->name);
            
            // Strona główna regionu
            $sitemap->add(Url::create(route('home', ['regionSlug' => $slug]))
                ->setLastModificationDate($place->updated_at ?? now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.9));

            // Oferty wycieczek
            $sitemap->add(Url::create(route('packages', ['regionSlug' => $slug]))
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                ->setPriority(0.8));

            // Ubezpieczenia
            $sitemap->add(Url::create(route('insurance', ['regionSlug' => $slug]))
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.7));

            // FAQ
            $sitemap->add(Url::create(route('faq', ['regionSlug' => $slug]))
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.6));

            // Kontakt
            $sitemap->add(Url::create(route('contact', ['regionSlug' => $slug]))
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.6));
        }

        // Dodaj strony globalne (bez regionSlug)
        // Dokumenty
        $sitemap->add(Url::create(route('documents.global'))
            ->setLastModificationDate(now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.7));

        // Blog
        $sitemap->add(Url::create(route('blog.global'))
            ->setLastModificationDate(now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(0.7));

        // Dodaj szablony wycieczek (oferty)
        $eventTemplates = EventTemplate::limit(200)->get(); // Limit 200 szablonów
        foreach ($eventTemplates as $template) {
            // Pobierz region startowy lub domyślny
            $region = $template->startPlace ?? $defaultRegion;
            if ($region) {
                $regionSlug = \Illuminate\Support\Str::slug($region->name);
                
                $templateSlug = \Illuminate\Support\Str::slug($template->name);
                $dayLength = $template->duration_days . '-dniowe';
                
                try {
                    $url = route('package.pretty', [
                        'regionSlug' => $regionSlug,
                        'dayLength' => $dayLength,
                        'id' => $template->id,
                        'slug' => $templateSlug
                    ]);
                    
                    $sitemap->add(Url::create($url)
                        ->setLastModificationDate($template->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.8));
                } catch (\Exception $e) {
                    $this->warn("⚠️  Nie można wygenerować URL dla szablonu {$template->id}: " . $e->getMessage());
                }
            }
        }

        // Dodaj artykuły bloga
        try {
            $blogPosts = BlogPost::where('published', true)->limit(100)->get();
            foreach ($blogPosts as $post) {
                $sitemap->add(Url::create(route('blog.post.global', ['slug' => $post->slug]))
                    ->setLastModificationDate($post->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.7));
            }
        } catch (\Exception $e) {
            $this->warn("⚠️  Błąd przy dodawaniu artykułów bloga: " . $e->getMessage());
        }

        // Zapisz sitemapę
        $sitemap->writeToFile(public_path('sitemap.xml'));
        
        $this->info("✅ Sitemap wygenerowana pomyślnie: public/sitemap.xml");
        return 0;
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
