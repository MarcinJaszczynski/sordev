# Sitemap Generation Documentation

## Setup Completed ✅

The sitemap generation system has been successfully configured for your Laravel application.

### What was installed and configured:

1. **Package**: `spatie/laravel-sitemap` - Professional sitemap generation library
2. **Command**: `php artisan sitemap:generate` - Generate the sitemap XML file
3. **Routing**: `/sitemap.xml` - Public endpoint to serve the sitemap
4. **Scheduling**: Daily generation at 3:00 AM (UTC)
5. **robots.txt**: Updated with sitemap reference

### How to use:

#### Manual Generation
Generate the sitemap manually anytime:
```bash
php artisan sitemap:generate
```

#### Automatic Generation
The sitemap is automatically generated daily at **03:00 UTC** via Laravel scheduler.

To verify the scheduler is working:
```bash
# In production, ensure the Laravel scheduler is running via Cron:
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

#### Access the Sitemap
- Browser: `http://127.0.0.1:8000/sitemap.xml` (or your production URL)
- Direct file: `public/sitemap.xml`

### Sitemap Contents:

The generated sitemap includes:
- ✅ Homepage (default region)
- ✅ All regional pages (up to 50 regions)
- ✅ Package listings per region
- ✅ Insurance pages
- ✅ FAQ pages
- ✅ Contact pages
- ✅ Documents (global)
- ✅ Blog (global)
- ✅ Event templates/package details (up to 200)
- ✅ Blog posts (published only, up to 100)

### URL Configuration

**Important**: The sitemap URLs use your `APP_URL` from `.env`:
- Development: `APP_URL=http://127.0.0.1:8000` → URLs in sitemap point to localhost
- Production: `APP_URL=https://bprafa.pl` → URLs in sitemap point to production domain

So ensure your `.env` has the correct `APP_URL` before generating the sitemap in production.

### Search Engine Submission

After verifying the sitemap is accessible:

1. **Google Search Console** (`https://search.google.com/search-console`)
   - Add property for your domain
   - Submit sitemap via Sitemaps section

2. **Bing Webmaster Tools** (`https://www.bing.com/webmasters`)
   - Add your site
   - Submit sitemap

3. **robots.txt reference**
   - Automatically set in `/robots.txt` to point to `/sitemap.xml`
   - Search engines will find it automatically

### Monitoring

Monitor sitemap generation in logs:
```bash
tail -f storage/logs/laravel.log | grep "sitemap"
```

### Customization

To modify what gets included in the sitemap, edit:
`app/Console/Commands/GenerateSitemap.php`

### Limits Applied
- Regions: 50 (to keep sitemap manageable)
- Event templates: 200
- Blog posts: 100

Adjust these limits in the command if needed based on your content volume.

## Current Status

✅ Package installed
✅ Command created and tested
✅ Routing configured
✅ Scheduler configured
✅ robots.txt updated
✅ Sitemap generated successfully (95 KB)
