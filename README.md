<p align="center">
    <a href="https://doppar.com" target="_blank">
        <img src="https://raw.githubusercontent.com/doppar/doppar/7138fb0e72cd55256769be6947df3ac48c300700/public/logo.png" width="400">
    </a>
</p>
<p align="center">
<a href="https://github.com/doppar/insight/actions/workflows/tests.yml"><img src="https://github.com/doppar/insight/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/doppar/insight"><img src="https://img.shields.io/packagist/dt/doppar/insight" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/doppar/insight"><img src="https://img.shields.io/packagist/v/doppar/insight" alt="Latest Stable Version"></a>
<a href="https://github.com/doppar/insight/blob/main/LICENSE"><img src="https://img.shields.io/github/license/doppar/insight" alt="License"></a>
</p>

## About Doppar Insight

> **Note:** This repository contains the core code of the Doppar framework Insight package. If you want to build an application using Doppar, visit the main [Doppar repository](https://github.com/doppar/doppar).

## Screenshots

<table>
  <tr>
    <td width="50%">
      <h3 align="center">Queries</h3>
      <img src="ressources/1.png" alt="SQL Queries Profiling" width="100%">
    </td>
    <td width="50%">
      <h3 align="center">Session</h3>
      <img src="ressources/2.png" alt="Session Information" width="100%">
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <h3 align="center">Main Toolbar</h3>
      <img src="ressources/3.png" alt="Doppar Insight Toolbar" width="100%">
    </td>
  </tr>
</table>

## Installation

Install the package via Composer:

```bash
composer require doppar/insight
```

The service provider will be automatically registered via package discovery.

## Configuration

### Basic Setup

The profiler is automatically enabled in development mode. No additional configuration is required!

However, you can customize the behavior by publishing the configuration file:

```bash
php pool vendor:publish --provider="Doppar\Insight\ProfilerServiceProvider"
```

### Registration

If you disabled package discovery, add the service provider manually in `config/app.php`:

```php
'providers' => [
    // ...
    Doppar\Insight\ProfilerServiceProvider::class,
],
```

### Storage & Retention

The profiler automatically stores request data as JSON files in `storage/framework/profiler`. To prevent disk space issues, old files are automatically deleted after the retention period (1 day by default).

You can customize the retention period in your `config/insight.php`:

```php
return [
    'retention_days' => 7, // Keep data for 7 days
];
```

The cleanup runs automatically once per day when new profiler data is stored.

## Usage

Once installed, the profiler toolbar will automatically appear at the bottom of your HTML pages in development mode.

## Collectors

Doppar Insight uses collectors to gather data. Available collectors:

- **TimeMemoryCollector** - Tracks execution time and memory usage
- **RequestCollector** - Collects request information
- **ResponseCollector** - Collects response information
- **SqlCollector** - Profiles database queries
- **CacheCollector** - Tracks cache operations
- **LogCollector** - Captures application logs
- **SessionCollector** - Inspects session data
- **AuthCollector** - Shows authentication information
- **HttpCollector** - HTTP protocol details
- **DopparCollector** - Framework version and environment

### Production

Do not use this package in production.

## Contributing

Thank you for considering contributing to the Doppar framework! The contribution guide can be found in the [Doppar documentation](https://doppar.com/versions/3.x/contributions.html).

## Code of Conduct

In order to ensure that the Doppar community is welcoming to all, please review and abide by the [Code of Conduct](https://doppar.com/versions/3.x/contributions.html#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/doppar/framework/security/policy) on how to report security vulnerabilities.

## License

The Doppar framework is open-sourced software licensed under the [MIT license](LICENSE.md).


