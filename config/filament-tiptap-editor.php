<?php

return [
    'direction' => 'ltr',
    'max_content_width' => '5xl',
    'disable_stylesheet' => false,
    'disable_link_as_button' => false,

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | Profiles determine which tools are available for the toolbar.
    | 'default' is all available tools, but you can create your own subsets.
    | The order of the tools doesn't matter.
    |
    */
    'profiles' => [
        'default' => [
            'undo', 'redo', '|',
            'heading', 'bullet-list', 'ordered-list', 'blockquote', '|',
            'bold', 'italic', 'strike', 'code', 'underline', 'color', 'highlight', 'link', '|',
            'superscript', 'subscript', '|',
            'align-left', 'align-center', 'align-right', 'align-justify',
        ],
        /* Uwagi imprezy — bez wyrównania do środka; kolor i highlight jak w default */
        'notes' => [
            'undo', 'redo', '|',
            'heading', 'bullet-list', 'ordered-list', 'blockquote', '|',
            'bold', 'italic', 'strike', 'underline', 'color', 'highlight', 'link',
        ],
        'simple' => ['heading', 'bullet-list', 'ordered-list', '|', 'bold', 'italic', 'underline', 'color', 'highlight', '|', 'link'],
        'minimal' => ['bold', 'italic', 'link', 'bullet-list', 'ordered-list'],
        'none' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    */
    'media_action' => FilamentTiptapEditor\Actions\MediaAction::class,
    //    'media_action' => Awcodes\Curator\Actions\MediaAction::class,
    'edit_media_action' => FilamentTiptapEditor\Actions\EditMediaAction::class,
    'link_action' => FilamentTiptapEditor\Actions\LinkAction::class,
    'grid_builder_action' => FilamentTiptapEditor\Actions\GridBuilderAction::class,
    'oembed_action' => FilamentTiptapEditor\Actions\OEmbedAction::class,

    /*
    |--------------------------------------------------------------------------
    | Output format
    |--------------------------------------------------------------------------
    |
    | Which output format should be stored in the Database.
    |
    | See: https://tiptap.dev/guide/output
    */
    'output' => FilamentTiptapEditor\Enums\TiptapOutput::Html,

    /*
    |--------------------------------------------------------------------------
    | Media Uploader
    |--------------------------------------------------------------------------
    |
    | These options will be passed to the native file uploader modal when
    | inserting media. They follow the same conventions as the
    | Filament Forms FileUpload field.
    |
    | See https://filamentphp.com/docs/3.x/panels/installation#file-upload
    |
    */
    'accepted_file_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'application/pdf'],
    'disk' => 'public',
    'directory' => 'images',
    'visibility' => 'public',
    'preserve_file_names' => false,
    'max_file_size' => 2042,
    'min_file_size' => 0,
    'image_resize_mode' => null,
    'image_crop_aspect_ratio' => null,
    'image_resize_target_width' => null,
    'image_resize_target_height' => null,
    'use_relative_paths' => true,

    /*
    |--------------------------------------------------------------------------
    | Menus
    |--------------------------------------------------------------------------
    |
    */
    'disable_floating_menus' => true,
    'disable_bubble_menus' => false,
    'disable_toolbar_menus' => false,

    'bubble_menu_tools' => ['bold', 'italic', 'underline', 'strike', 'color', 'highlight', 'link'],
    'floating_menu_tools' => [],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    */
    'extensions_script' => null,
    'extensions_styles' => null,
    'extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | PresetColors
    |--------------------------------------------------------------------------
    |
    | Possibility to define presets colors in ColorPicker.
    | Only hexadecimal value
    'preset_colors' => [
        'primary' => '#f59e0b',
        //..
    ]
    |
    */
    'preset_colors' => [
        'amber' => '#f59e0b',
        'red' => '#dc2626',
        'green' => '#16a34a',
        'blue' => '#2563eb',
        'purple' => '#7c3aed',
        'gray' => '#4b5563',
        'black' => '#111827',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protocols
    |--------------------------------------------------------------------------
    |
    | With newer versions of Tiptap, you need to define additional protocols
    | for the link extension. i.e. 'ftp', 'mailto', etc.
    |
    */
    'link_protocols' => [],
];
