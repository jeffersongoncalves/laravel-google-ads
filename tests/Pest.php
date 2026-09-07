<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GoogleAds\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');
