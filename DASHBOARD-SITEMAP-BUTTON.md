# Dashboard Sitemap Generator Button

## Overview
Added a button to the Filament admin dashboard for manual sitemap generation. This allows administrators to trigger XML sitemap regeneration without accessing the command line.

## Changes Made

### 1. Custom Dashboard Page
**File**: `app/Filament/Pages/Dashboard.php`

- Created custom Dashboard page extending Filament's BaseDashboard
- Added `getHeaderActions()` method with "Generuj Sitemapę" (Generate Sitemap) button
- Button features:
  - Icon: `heroicon-o-arrow-path` (refresh icon)
  - Color: success (green)
  - Requires confirmation modal before execution
  - Executes `php artisan sitemap:generate` command
  - Shows success notification: "Sitemap został pomyślnie wygenerowany. Dostępna jest pod adresem: /sitemap.xml"
  - Shows error notification if something goes wrong

### 2. AdminPanelProvider Updates
**File**: `app/Providers/Filament/AdminPanelProvider.php`

- Updated pages array to use custom Dashboard class: `\App\Filament\Pages\Dashboard::class`
- Removed unused `use Filament\Pages;` import
- Replaced with direct import of custom Dashboard page

## How It Works

1. User clicks "Generuj Sitemapę" button in dashboard header
2. Confirmation modal appears asking for confirmation
3. Upon confirmation, `Artisan::call('sitemap:generate')` is executed
4. Success or error notification appears with appropriate message
5. Sitemap is regenerated at `/sitemap.xml` and accessible via browser

## Usage

### Manual Generation
- Log in to admin panel at `/admin`
- Go to Dashboard (default page)
- Click "Generuj Sitemapę" button in top-right
- Confirm the action
- Wait for success notification

### Scheduled Generation (Existing)
- Sitemap is automatically generated daily at 03:00 UTC via `app/Console/Kernel.php`
- Manual button allows immediate regeneration when needed

## File Structure
```
app/Filament/Pages/
├── Dashboard.php          (NEW - Custom dashboard with sitemap button)
└── ...
```

## Technical Details

### Dependencies
- Filament 3.x
- Laravel Artisan command system
- Notifications system

### Error Handling
- Try-catch block wraps command execution
- Exceptions are caught and displayed to user via notification modal

### Permissions
- Requires standard admin access (inherited from BaseDashboard)
- Uses Filament's default authorization system

## Integration with Existing Sitemap System

The button integrates seamlessly with:
- `app/Console/Commands/GenerateSitemap.php` - The actual generation command
- Scheduled task in `app/Console/Kernel.php` - Automatic generation at 03:00 UTC
- Route `/sitemap.xml` - Serves the generated XML file
- `public/robots.txt` - References the sitemap

## Testing

To verify the button works:
1. Start Laravel development server: `php artisan serve`
2. Navigate to admin dashboard: `http://127.0.0.1:8000/admin`
3. Click "Generuj Sitemapę" button
4. Confirm action
5. Check for success notification
6. Verify sitemap at `http://127.0.0.1:8000/sitemap.xml`

## Future Enhancements

- Add last generation time display on dashboard
- Show breakdown of sitemap statistics (URLs count per category)
- Add progress bar for long-running generation
- Log generation history for audit purposes
