<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\Place;
use App\Models\EventTemplate;
use App\Models\BlogPost;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate XML sitemap for the website';

    public function handle()
    {
        $this->info('Generowanie sitemapy...');

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
}
