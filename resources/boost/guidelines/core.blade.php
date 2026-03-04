## Laravel Mixpanel

### Overview

Laravel package that integrates Mixpanel analytics into Blade templates using `spatie/laravel-settings` for database-stored configuration. Loads the Mixpanel JavaScript SDK and initializes it with settings managed via the `MixpanelSettings` class.

**Namespace:** `JeffersonGoncalves\Mixpanel`
**Service Provider:** `MixpanelServiceProvider` (extends `Spatie\LaravelPackageTools\PackageServiceProvider`)

### Key Concepts

- **Settings-driven**: All configuration lives in `MixpanelSettings` (group: `mixpanel`), not in config files.
- **Blade view**: Include `mixpanel::script` in your layout to render the Mixpanel JS SDK and initialization.
- **JS config builder**: `MixpanelSettings::toJsConfig()` converts settings to a JavaScript-compatible configuration array.
- **Auto-discovery**: Service provider is auto-discovered via `composer.json` extra.laravel.providers.
- **Custom lib URL**: Supports loading the Mixpanel SDK from a custom URL via `custom_lib_url`.

### Settings (spatie/laravel-settings)

Settings class: `JeffersonGoncalves\Mixpanel\Settings\MixpanelSettings`
Group: `mixpanel`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `project_token` | `?string` | `null` | Mixpanel project token (required for tracking) |
| `api_host` | `?string` | `null` | Custom API host for EU residency or proxy |
| `custom_lib_url` | `?string` | `null` | Custom Mixpanel JS library URL |
| `debug` | `bool` | `false` | Enable Mixpanel debug mode |
| `autocapture` | `bool` | `true` | Enable automatic event capture |
| `track_pageview` | `string` | `'true'` | Track page views: `'true'`, `'false'`, or `'url-with-path'` |
| `persistence` | `string` | `'cookie'` | Persistence mode: `'cookie'` or `'localStorage'` |
| `cookie_expiration` | `int` | `365` | Cookie expiration in days |
| `secure_cookie` | `bool` | `false` | Use secure cookies (HTTPS only) |
| `cross_subdomain_cookie` | `bool` | `true` | Share cookies across subdomains |
| `ip` | `bool` | `true` | Use IP address for geolocation |
| `property_blacklist` | `?string` | `null` | Comma-separated list of properties to exclude |
| `opt_out_tracking_by_default` | `bool` | `false` | Opt out of tracking by default |
| `stop_utm_persistence` | `bool` | `false` | Stop persisting UTM parameters |
| `record_sessions_percent` | `int` | `0` | Session replay recording percentage (0-100) |
| `record_heatmap_data` | `bool` | `false` | Enable heatmap data collection |

@verbatim
<code-snippet name="read-settings" lang="php">
use JeffersonGoncalves\Mixpanel\Settings\MixpanelSettings;

$settings = app(MixpanelSettings::class);
$settings->project_token;  // ?string
$settings->debug;          // bool
$settings->toJsConfig();   // array (JS-compatible config)
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="update-settings" lang="php">
use JeffersonGoncalves\Mixpanel\Settings\MixpanelSettings;

$settings = app(MixpanelSettings::class);
$settings->project_token = 'your-project-token';
$settings->debug = true;
$settings->record_sessions_percent = 50;
$settings->save();
</code-snippet>
@endverbatim

### Configuration

No config file is published. All configuration is managed through the `MixpanelSettings` class.

**Publish settings migration:**

@verbatim
<code-snippet name="publish-migration" lang="bash">
php artisan vendor:publish --tag="mixpanel-settings-migrations"
php artisan migrate
</code-snippet>
@endverbatim

### Blade Integration

Include the tracking script in your layout's `<head>`:

@verbatim
<code-snippet name="blade-include" lang="blade">
<head>
    @include('mixpanel::script')
</head>
</code-snippet>
@endverbatim

The script loads the Mixpanel JS SDK (or custom lib URL) and calls `mixpanel.init()` with the project token and JS config. It only renders when `project_token` is not empty.

### Conventions

- Settings group name: `mixpanel`
- View namespace: `mixpanel`
- Package name: `laravel-mixpanel`
- Migration publish tag: `mixpanel-settings-migrations`
- `toJsConfig()` converts `property_blacklist` from comma-separated string to array.
- `track_pageview` values `'true'`/`'false'` are cast to booleans in JS config; other values (e.g., `'url-with-path'`) are passed as strings.
- No models or relationships -- this is a script-injection package.
- PHP 8.2+ required, Laravel 11+, spatie/laravel-settings ^3.3.
