<?php

namespace JeffersonGoncalves\GoogleAds\Tests;

use JeffersonGoncalves\GoogleAds\GoogleAdsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GoogleAdsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('google-ads.access_token', 'fake-access-token');
        $app['config']->set('google-ads.developer_token', 'fake-developer-token');
        $app['config']->set('google-ads.customer_id', '1234567890');
        $app['config']->set('google-ads.version', 'v19');
        $app['config']->set('google-ads.timeout', 5);
    }
}
