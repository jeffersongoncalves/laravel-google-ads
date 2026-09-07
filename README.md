<div class="filament-hidden">

![Laravel Google Ads](https://raw.githubusercontent.com/jeffersongoncalves/laravel-google-ads/main/banners/laravel-google-ads.png)

</div>

# Laravel Google Ads

[![Tests](https://github.com/jeffersongoncalves/laravel-google-ads/actions/workflows/run-tests.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-google-ads/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/jeffersongoncalves/laravel-google-ads/actions/workflows/phpstan.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-google-ads/actions/workflows/phpstan.yml)
[![Code Style](https://github.com/jeffersongoncalves/laravel-google-ads/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-google-ads/actions/workflows/fix-php-code-style-issues.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-google-ads.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-google-ads)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-google-ads.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-google-ads)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/laravel-google-ads.svg?style=flat-square)](LICENSE.md)

A lightweight Google Ads API client for Laravel. It wraps GAQL search (`googleAds:searchStream`), campaign and budget mutate calls behind a small static client, threads your OAuth bearer token and developer token, and returns `null`/`[]` sentinels on ordinary HTTP failures instead of throwing.

## Features

- **`search()`** — run a raw GAQL query, flattened from the streamed response chunks into a single list of rows
- **`accountInfo()`** — fetch the customer id and descriptive name
- **`campaigns()`** — list campaigns with id, name, status, and budget
- **`campaignsPerformance()`** — impressions, clicks, cost, and conversions per campaign over a date range
- **`pauseCampaign()` / `enableCampaign()`** — toggle a campaign's status
- **`adGroupsPerformance()`** — impressions, clicks, and conversions per ad group
- **`keywordsPerformance()`** — impressions, clicks, and average CPC per keyword, ranked by clicks
- **`updateBudget()`** — update a campaign budget, converting dollars to micros

## Installation

```bash
composer require jeffersongoncalves/laravel-google-ads
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="google-ads-config"
```

## Configuration

Add to your `.env`:

```env
GOOGLE_ADS_ACCESS_TOKEN=ya29.xxxxxxxxxxxxxxxxxxxx
GOOGLE_ADS_DEVELOPER_TOKEN=xxxxxxxxxxxxxxxxxxxxxx
GOOGLE_ADS_CUSTOMER_ID=1234567890
GOOGLE_ADS_LOGIN_CUSTOMER_ID=
GOOGLE_ADS_API_VERSION=v19
GOOGLE_ADS_TIMEOUT=8
```

`GOOGLE_ADS_ACCESS_TOKEN` is the OAuth bearer token — see the [OAuth guide](https://developers.google.com/google-ads/api/docs/oauth/overview). `GOOGLE_ADS_DEVELOPER_TOKEN` identifies your application — see the [developer token guide](https://developers.google.com/google-ads/api/docs/get-started/dev-token). `GOOGLE_ADS_CUSTOMER_ID` is the default account requests target when no id is passed explicitly. `GOOGLE_ADS_LOGIN_CUSTOMER_ID` is only needed when authenticating through a manager (MCC) account. `GOOGLE_ADS_API_VERSION` should track a [currently supported version](https://developers.google.com/google-ads/api/docs/release-notes) — Google sunsets old ones on a rolling schedule.

### Config Options

```php
// config/google-ads.php
return [
    'access_token' => env('GOOGLE_ADS_ACCESS_TOKEN'),
    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
    'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
    'version' => env('GOOGLE_ADS_API_VERSION', 'v19'),
    'timeout' => (int) env('GOOGLE_ADS_TIMEOUT', 8),
];
```

## Usage

```php
use JeffersonGoncalves\GoogleAds\GoogleAdsClient;

// Run any GAQL query — customer id defaults to config('google-ads.customer_id')
$rows = GoogleAdsClient::search('SELECT campaign.id, campaign.name FROM campaign');

// Account info
$account = GoogleAdsClient::accountInfo();

// Campaigns
$campaigns = GoogleAdsClient::campaigns();
$performance = GoogleAdsClient::campaignsPerformance(days: 7);

// Toggle a campaign
GoogleAdsClient::pauseCampaign('111');
GoogleAdsClient::enableCampaign('111');

// Ad groups and keywords
$adGroups = GoogleAdsClient::adGroupsPerformance(days: 30, limit: 10);
$keywords = GoogleAdsClient::keywordsPerformance(days: 30, limit: 50);

// Update a budget (amount in dollars, converted to micros)
GoogleAdsClient::updateBudget('222', 25.00);

// Target a different account than the configured default
$rows = GoogleAdsClient::campaigns(customerId: '987-654-3210');
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Code Formatting

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
