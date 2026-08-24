<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Blog';

    protected static ?string $modelLabel = 'wpis na blogu';

    protected static ?string $pluralModelLabel = 'wpisy na blogu';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 50;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Tytuł')
                        ->required()
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set) {
                            if ($state) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Identyfikator URL')
                        ->helperText('Fragment adresu wpisu, generowany z tytułu.')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record),
                    Forms\Components\Select::make('content_type')
                        ->label('Typ treści')
                        ->options([
                            'aktualnosci' => 'Aktualności',
                            'poradnik' => 'Poradnik turystyczny',
                        ])
                        ->default('aktualnosci')
                        ->required(),
                    Forms\Components\TextInput::make('guide_category')
                        ->label('Kategoria poradnika')
                        ->helperText('Np. wycieczki-szkolne, wyjazdy-firmowe')
                        ->maxLength(100)
                        ->visible(fn ($get) => $get('content_type') === 'poradnik'),
                    Forms\Components\TextInput::make('excerpt')
                        ->label('Krótki opis')
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make('Treść')
                ->schema([
                    \FilamentTiptapEditor\TiptapEditor::make('content')
                        ->label('Treść')
                        ->required()

                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Multimedia')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\FileUpload::make('featured_image')
                        ->image()
                        ->label('Obraz wyróżniający')
                        ->disk('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->getUploadedFileUsing(function ($file, $storedFileNames): ?array {
                            if (blank($file)) {
                                return null;
                            }
                            try {
                                $disk = Storage::disk('public');
                                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                                $size = $disk->exists($file) ? $disk->size($file) : 0;
                                $type = $disk->exists($file) ? $disk->mimeType($file) : null;
                            } catch (\Throwable $e) {
                                $size = 0;
                                $type = null;
                            }

                            return [
                                'name' => basename($file),
                                'size' => $size,
                                'type' => $type,
                                'url' => '/storage/'.ltrim($file, '/'),
                            ];
                        })
                        ->nullable(),
                    Forms\Components\TextInput::make('featured_image_alt')
                        ->label('Tekst alternatywny zdjęcia (alt)')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('gallery')
                        ->label('Galeria')
                        ->image()
                        ->multiple()
                        ->disk('public')
                        ->directory('blog/gallery')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->getUploadedFileUsing(function ($file, $storedFileNames): ?array {
                            if (blank($file)) {
                                return null;
                            }
                            try {
                                $disk = Storage::disk('public');
                                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                                $size = $disk->exists($file) ? $disk->size($file) : 0;
                                $type = $disk->exists($file) ? $disk->mimeType($file) : null;
                            } catch (\Throwable $e) {
                                $size = 0;
                                $type = null;
                            }

                            return [
                                'name' => basename($file),
                                'size' => $size,
                                'type' => $type,
                                'url' => '/storage/'.ltrim($file, '/'),
                            ];
                        })
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Publikacja i tagi')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\Select::make('tags')
                        ->label('Tagi')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Polecany')
                        ->inline(false),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Opublikowany')
                        ->inline(false),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Data publikacji'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('published_at')->date()->sortable(),
                IconColumn::make('is_published')->boolean()->label('Opublikowany'),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'create blog post')) {
            return true;
        }

        return false;
    }
}
