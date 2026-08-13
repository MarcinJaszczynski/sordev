<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\Help\HelpArticle;
use App\Support\Help\HelpCatalog;
use Livewire\Attributes\Url;

trait InteractsWithHelpCenter
{
    #[Url(as: 'article', except: '')]
    public string $article = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mountInteractsWithHelpCenter(): void
    {
        if ($this->article === '' && $this->search === '') {
            $first = $this->filteredArticles()[0] ?? null;
            if ($first instanceof HelpArticle) {
                $this->article = $first->slug;
            }
        }
    }

    public function selectArticle(string $slug): void
    {
        $this->article = $slug;
    }

    public function updatedSearch(): void
    {
        $articles = $this->filteredArticles();
        $current = $this->selectedArticle();

        if ($current === null && isset($articles[0])) {
            $this->article = $articles[0]->slug;
        }
    }

    /**
     * @return list<HelpArticle>
     */
    public function filteredArticles(): array
    {
        return $this->helpCatalog()->search($this->helpPanel(), $this->search);
    }

    /**
     * @return array<string, list<HelpArticle>>
     */
    public function articlesByCategory(): array
    {
        $grouped = [];
        foreach ($this->filteredArticles() as $article) {
            $grouped[$article->category][] = $article;
        }

        return $grouped;
    }

    public function selectedArticle(): ?HelpArticle
    {
        if ($this->article === '') {
            return null;
        }

        foreach ($this->filteredArticles() as $article) {
            if ($article->slug === $this->article) {
                return $article;
            }
        }

        return $this->helpCatalog()->find($this->helpPanel(), $this->article);
    }

    public function selectedArticleHtml(): string
    {
        $article = $this->selectedArticle();
        if ($article === null) {
            return '';
        }

        return $this->helpCatalog()->renderHtml($article);
    }

    abstract protected function helpPanel(): string;

    protected function helpCatalog(): HelpCatalog
    {
        return app(HelpCatalog::class);
    }
}
