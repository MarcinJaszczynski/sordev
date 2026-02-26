Instrukcje: Blog admin (Filament)

Co dodałem:
- Filament Resource: `app/Filament/Resources/BlogPostResource.php` wraz ze stronami List/Create/Edit.
- Migracja pivot: `database/migrations/2025_08_24_000002_create_blog_post_tag_table.php`.

Co zrobić lokalnie (kroki):
1. Zainstaluj zależności PHP (jeśli jeszcze nie):
   composer install

2. Upewnij się, że `filament/filament` jest zainstalowany w projekcie. Jeśli nie:
   composer require filament/filament

3. Uruchom migracje:

```powershell
php artisan migrate
```

4. Uruchom serwer lokalnie (opcjonalnie):

```powershell
php artisan serve
```

5. Zaloguj się do panelu admin Filament (zwykle `/admin`) i powinieneś zobaczyć sekcję "Blog" z możliwością tworzenia wpisów. Pola: title, slug, excerpt, content (RichEditor), featured_image (upload do dysku `public`), tags (relacja), is_featured, is_published, published_at.

Uwagi:
- Edytor RichEditor wymaga bibliotek Filament; upewnij się, że assets Filament są zbudowane.
- Jeśli chcesz inny edytor (CKEditor/Tiptap) lub dodatkową funkcjonalność (gallery jako osobna tabela), powiedz, to dodam.
