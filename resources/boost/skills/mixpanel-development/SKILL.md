---
name: mixpanel-development
description: Development guide for the laravel-mixpanel package -- Mixpanel analytics integration for Laravel using spatie/laravel-settings.
---

# Mixpanel Development Skill

## When to use this skill

- When developing or modifying the `jeffersongoncalves/laravel-mixpanel` package.
- When adding new settings properties to `MixpanelSettings`.
- When modifying the Blade tracking script or the JS config builder.
- When writing tests for the package.
- When integrating Mixpanel analytics into a Laravel application.

## Setup

### Requirements

- PHP 8.2 or 8.3
- Laravel 11, 12, or 13
- `spatie/laravel-settings` ^3.3
- `spatie/laravel-package-tools` ^1.14.0

### Installation

```bash
composer require jeffersongoncalves/laravel-mixpanel
```

### Publish and run the settings migration

```bash
php artisan vendor:publish --tag="mixpanel-settings-migrations"
php artisan migrate
```

### Include the tracking script in your layout

```blade
<head>
    @include('mixpanel::script')
</head>
```

## Architecture

### Directory Structure

```
src/
  MixpanelServiceProvider.php       # Package service provider
  Settings/
    MixpanelSettings.php            # Spatie Settings class (group: mixpanel)
database/
  settings/
    2026_01_01_000000_create_mixpanel_settings.php  # Settings migration
resources/
  views/
    script.blade.php                # Tracking script Blade view
```

### Service Provider

`MixpanelServiceProvider` extends `Spatie\LaravelPackageTools\PackageServiceProvider`:

- Registers the package name as `laravel-mixpanel` with views.
- Auto-registers `MixpanelSettings` into the `settings.settings` config array.
- Registers the settings migration path in `settings.migrations_paths`.
- Publishes settings migrations under tag `mixpanel-settings-migrations`.

```php
class MixpanelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-mixpanel')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        Config::set('settings.settings', array_merge(
            Config::get('settings.settings', []),
            [MixpanelSettings::class]
        ));
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        Config::set('settings.migrations_paths', array_merge(
            Config::get('settings.migrations_paths', []),
            [__DIR__.'/../database/settings']
        ));

        $this->publishes([
            __DIR__.'/../database/settings' => database_path('settings'),
        ], 'mixpanel-settings-migrations');
    }
}
```

### Settings Class

`MixpanelSettings` uses `spatie/laravel-settings` with group `mixpanel`:

```php
use Spatie\LaravelSettings\Settings;

class MixpanelSettings extends Settings
{
    public ?string $project_token;
    public ?string $api_host;
    public ?string $custom_lib_url;
    public bool $debug;
    public bool $autocapture;
    public string $track_pageview;
    public string $persistence;
    public int $cookie_expiration;
    public bool $secure_cookie;
    public bool $cross_subdomain_cookie;
    public bool $ip;
    public ?string $property_blacklist;
    public bool $opt_out_tracking_by_default;
    public bool $stop_utm_persistence;
    public int $record_sessions_percent;
    public bool $record_heatmap_data;

    public static function group(): string
    {
        return 'mixpanel';
    }

    public function toJsConfig(): array { /* ... */ }
}
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `project_token` | `?string` | `null` | Mixpanel project token |
| `api_host` | `?string` | `null` | Custom API host (e.g., EU residency proxy) |
| `custom_lib_url` | `?string` | `null` | Custom Mixpanel JS library URL |
| `debug` | `bool` | `false` | Enable debug mode in browser console |
| `autocapture` | `bool` | `true` | Automatically capture clicks, inputs, etc. |
| `track_pageview` | `string` | `'true'` | `'true'`, `'false'`, or `'url-with-path'` |
| `persistence` | `string` | `'cookie'` | `'cookie'` or `'localStorage'` |
| `cookie_expiration` | `int` | `365` | Cookie expiration in days |
| `secure_cookie` | `bool` | `false` | HTTPS-only cookies |
| `cross_subdomain_cookie` | `bool` | `true` | Share cookies across subdomains |
| `ip` | `bool` | `true` | Use IP for geolocation |
| `property_blacklist` | `?string` | `null` | Comma-separated excluded properties |
| `opt_out_tracking_by_default` | `bool` | `false` | Opt out by default (GDPR) |
| `stop_utm_persistence` | `bool` | `false` | Stop persisting UTM params |
| `record_sessions_percent` | `int` | `0` | Session replay percentage (0-100) |
| `record_heatmap_data` | `bool` | `false` | Heatmap data collection |

### The `toJsConfig()` Method

This method converts the settings into an array suitable for `mixpanel.init()`:

```php
public function toJsConfig(): array
{
    $config = [];

    if ($this->api_host) {
        $config['api_host'] = $this->api_host;
    }

    $config['debug'] = $this->debug;
    $config['autocapture'] = $this->autocapture;

    // 'true'/'false' strings become booleans; other values pass through as strings
    if (in_array($this->track_pageview, ['true', 'false'], true)) {
        $config['track_pageview'] = $this->track_pageview === 'true';
    } else {
        $config['track_pageview'] = $this->track_pageview;
    }

    $config['persistence'] = $this->persistence;
    $config['cookie_expiration'] = $this->cookie_expiration;
    $config['secure_cookie'] = $this->secure_cookie;
    $config['cross_subdomain_cookie'] = $this->cross_subdomain_cookie;
    $config['ip'] = $this->ip;

    // Comma-separated string becomes array
    if ($this->property_blacklist) {
        $config['property_blacklist'] = array_map('trim', explode(',', $this->property_blacklist));
    }

    $config['opt_out_tracking_by_default'] = $this->opt_out_tracking_by_default;
    $config['stop_utm_persistence'] = $this->stop_utm_persistence;
    $config['record_sessions_percent'] = $this->record_sessions_percent;
    $config['record_heatmap_data'] = $this->record_heatmap_data;

    return $config;
}
```

### Blade View

The `script.blade.php` view resolves `MixpanelSettings` from the container and conditionally renders the Mixpanel tracking scripts:

```blade
@php
    $settings = app(\JeffersonGoncalves\Mixpanel\Settings\MixpanelSettings::class);
@endphp

@if(!empty($settings->project_token))
@if($settings->custom_lib_url)
<script type="text/javascript">
    var MIXPANEL_CUSTOM_LIB_URL = "{{ $settings->custom_lib_url }}";
</script>
@endif
<script type="text/javascript">
    // Mixpanel JS SDK loader snippet
    (function(f,b){...})(document,window.mixpanel||[]);
</script>
<script type="text/javascript">
    mixpanel.init("{{ $settings->project_token }}", {!! json_encode($settings->toJsConfig(), ...) !!});
</script>
@endif
```

Key behaviors:
- Only renders when `project_token` is not null/empty.
- If `custom_lib_url` is set, defines `MIXPANEL_CUSTOM_LIB_URL` global before the SDK loader.
- Uses `json_encode` with `JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT` for the config.
- The `toJsConfig()` method handles type conversions (string booleans, comma-separated arrays).

## Features

### Reading Settings

```php
use JeffersonGoncalves\Mixpanel\Settings\MixpanelSettings;

$settings = app(MixpanelSettings::class);

echo $settings->project_token;          // e.g., "abc123def456"
echo $settings->debug;                  // false
echo $settings->record_sessions_percent; // 0
```

### Updating Settings

```php
$settings = app(MixpanelSettings::class);
$settings->project_token = 'your-mixpanel-project-token';
$settings->api_host = 'https://api-eu.mixpanel.com';
$settings->debug = true;
$settings->record_sessions_percent = 25;
$settings->save();
```

### Custom API Host (EU Data Residency)

```php
$settings = app(MixpanelSettings::class);
$settings->api_host = 'https://api-eu.mixpanel.com';
$settings->save();
```

### Custom Library URL

```php
$settings = app(MixpanelSettings::class);
$settings->custom_lib_url = 'https://cdn.example.com/mixpanel.min.js';
$settings->save();
```

### Property Blacklist

The `property_blacklist` is stored as a comma-separated string and converted to an array by `toJsConfig()`:

```php
$settings = app(MixpanelSettings::class);
$settings->property_blacklist = '$current_url, $initial_referrer, $referrer';
$settings->save();

// toJsConfig() output: ['property_blacklist' => ['$current_url', '$initial_referrer', '$referrer']]
```

## Configuration

This package uses **no config file**. All configuration is managed via `spatie/laravel-settings` in the database.

### Settings Migration

The migration creates 16 settings in the `mixpanel` group:

```php
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mixpanel.project_token', null);
        $this->migrator->add('mixpanel.api_host', null);
        $this->migrator->add('mixpanel.custom_lib_url', null);
        $this->migrator->add('mixpanel.debug', false);
        $this->migrator->add('mixpanel.autocapture', true);
        $this->migrator->add('mixpanel.track_pageview', 'true');
        $this->migrator->add('mixpanel.persistence', 'cookie');
        $this->migrator->add('mixpanel.cookie_expiration', 365);
        $this->migrator->add('mixpanel.secure_cookie', false);
        $this->migrator->add('mixpanel.cross_subdomain_cookie', true);
        $this->migrator->add('mixpanel.ip', true);
        $this->migrator->add('mixpanel.property_blacklist', null);
        $this->migrator->add('mixpanel.opt_out_tracking_by_default', false);
        $this->migrator->add('mixpanel.stop_utm_persistence', false);
        $this->migrator->add('mixpanel.record_sessions_percent', 0);
        $this->migrator->add('mixpanel.record_heatmap_data', false);
    }
};
```

### Adding New Settings

When adding a new setting property:

1. Add the property to `MixpanelSettings`:

```php
public bool $record_idle_timeout_ms = false;
```

2. Create a new settings migration:

```php
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mixpanel.record_idle_timeout_ms', false);
    }
};
```

3. Update `toJsConfig()` if the setting should be passed to `mixpanel.init()`.
4. Update `script.blade.php` if the setting affects the tracking script rendering.

## Testing Patterns

### Test Setup

The package uses Pest with `pestphp/pest-plugin-laravel` and `orchestra/testbench`.

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Run code formatting
composer format
```

### Writing Tests

```php
use JeffersonGoncalves\Mixpanel\Settings\MixpanelSettings;

it('renders the tracking script when project_token is set', function () {
    $settings = app(MixpanelSettings::class);
    $settings->project_token = 'test-token-123';
    $settings->save();

    $view = $this->blade('@include("mixpanel::script")');

    $view->assertSee('mixpanel.init("test-token-123"');
});

it('does not render the script when project_token is null', function () {
    $settings = app(MixpanelSettings::class);
    $settings->project_token = null;
    $settings->save();

    $view = $this->blade('@include("mixpanel::script")');

    $view->assertDontSee('mixpanel');
});

it('renders custom lib URL when set', function () {
    $settings = app(MixpanelSettings::class);
    $settings->project_token = 'test-token';
    $settings->custom_lib_url = 'https://cdn.example.com/mixpanel.js';
    $settings->save();

    $view = $this->blade('@include("mixpanel::script")');

    $view->assertSee('MIXPANEL_CUSTOM_LIB_URL');
    $view->assertSee('https://cdn.example.com/mixpanel.js');
});
```

### Testing toJsConfig()

```php
it('converts track_pageview string booleans to actual booleans', function () {
    $settings = app(MixpanelSettings::class);
    $settings->track_pageview = 'true';

    $config = $settings->toJsConfig();

    expect($config['track_pageview'])->toBeTrue();
});

it('passes non-boolean track_pageview values as strings', function () {
    $settings = app(MixpanelSettings::class);
    $settings->track_pageview = 'url-with-path';

    $config = $settings->toJsConfig();

    expect($config['track_pageview'])->toBe('url-with-path');
});

it('converts property_blacklist to array', function () {
    $settings = app(MixpanelSettings::class);
    $settings->property_blacklist = '$current_url, $referrer';

    $config = $settings->toJsConfig();

    expect($config['property_blacklist'])->toBe(['$current_url', '$referrer']);
});

it('excludes api_host from config when null', function () {
    $settings = app(MixpanelSettings::class);
    $settings->api_host = null;

    $config = $settings->toJsConfig();

    expect($config)->not->toHaveKey('api_host');
});
```

### Testing Settings Independently

```php
it('has the correct default values', function () {
    $settings = app(MixpanelSettings::class);

    expect($settings->project_token)->toBeNull();
    expect($settings->debug)->toBeFalse();
    expect($settings->autocapture)->toBeTrue();
    expect($settings->track_pageview)->toBe('true');
    expect($settings->persistence)->toBe('cookie');
    expect($settings->cookie_expiration)->toBe(365);
    expect($settings->record_sessions_percent)->toBe(0);
});

it('belongs to the mixpanel group', function () {
    expect(MixpanelSettings::group())->toBe('mixpanel');
});
```
