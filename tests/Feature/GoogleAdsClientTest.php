<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GoogleAds\GoogleAdsClient;

it('runs a GAQL search and flattens the streamed chunks', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([
            ['results' => [['campaign' => ['id' => '1', 'name' => 'Campaign A']]]],
            ['results' => [['campaign' => ['id' => '2', 'name' => 'Campaign B']]]],
        ], 200),
    ]);

    $rows = GoogleAdsClient::search('SELECT campaign.id, campaign.name FROM campaign');

    expect($rows)->toBe([
        ['campaign' => ['id' => '1', 'name' => 'Campaign A']],
        ['campaign' => ['id' => '2', 'name' => 'Campaign B']],
    ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://googleads.googleapis.com/v19/customers/1234567890/googleAds:searchStream'
            && $request->hasHeader('Authorization', 'Bearer fake-access-token')
            && $request->hasHeader('developer-token', 'fake-developer-token')
            && $request['query'] === 'SELECT campaign.id, campaign.name FROM campaign';
    });
});

it('returns an empty array when search fails', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response('', 403),
    ]);

    expect(GoogleAdsClient::search('SELECT campaign.id FROM campaign'))->toBe([]);
});

it('sends the login-customer-id header when configured', function () {
    config()->set('google-ads.login_customer_id', '9876543210');

    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    GoogleAdsClient::search('SELECT campaign.id FROM campaign');

    Http::assertSent(fn (Request $request) => $request->hasHeader('login-customer-id', '9876543210'));
});

it('fetches account info from the first result row', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([
            ['results' => [['customer' => ['id' => '1234567890', 'descriptiveName' => 'Acme']]]],
        ], 200),
    ]);

    expect(GoogleAdsClient::accountInfo())->toBe(['customer' => ['id' => '1234567890', 'descriptiveName' => 'Acme']]);
});

it('returns null for account info when there are no rows', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    expect(GoogleAdsClient::accountInfo())->toBeNull();
});

it('lists campaigns', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([
            ['results' => [['campaign' => ['id' => '1', 'name' => 'Campaign A', 'status' => 'ENABLED']]]],
        ], 200),
    ]);

    expect(GoogleAdsClient::campaigns())->toHaveCount(1);

    Http::assertSent(fn (Request $request) => str_contains((string) $request['query'], 'FROM campaign ORDER BY campaign.id'));
});

it('builds the campaigns performance query with the requested date range', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    GoogleAdsClient::campaignsPerformance(7);

    Http::assertSent(fn (Request $request) => str_contains((string) $request['query'], 'DURING LAST_7_DAYS'));
});

it('defaults the performance date range to 30 days', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    GoogleAdsClient::campaignsPerformance();

    Http::assertSent(fn (Request $request) => str_contains((string) $request['query'], 'DURING LAST_30_DAYS'));
});

it('pauses a campaign', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response(['results' => [['resourceName' => 'customers/1234567890/campaigns/111']]], 200),
    ]);

    $result = GoogleAdsClient::pauseCampaign('111');

    expect($result)->toBe(['results' => [['resourceName' => 'customers/1234567890/campaigns/111']]]);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'campaigns:mutate')
            && $request['operations'][0]['update']['status'] === 'PAUSED'
            && $request['operations'][0]['update']['resourceName'] === 'customers/1234567890/campaigns/111';
    });
});

it('enables a campaign', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response(['results' => []], 200),
    ]);

    GoogleAdsClient::enableCampaign('111');

    Http::assertSent(fn (Request $request) => $request['operations'][0]['update']['status'] === 'ENABLED');
});

it('builds the ad groups performance query with a limit clause', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    GoogleAdsClient::adGroupsPerformance(14, 10);

    Http::assertSent(fn (Request $request) => str_contains((string) $request['query'], 'DURING LAST_14_DAYS LIMIT 10'));
});

it('builds the keywords performance query with the default limit', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    GoogleAdsClient::keywordsPerformance();

    Http::assertSent(fn (Request $request) => str_contains((string) $request['query'], 'LIMIT 50'));
});

it('updates a budget converting dollars to micros', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response(['results' => []], 200),
    ]);

    GoogleAdsClient::updateBudget('222', 12.5);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'campaignBudgets:mutate')
            && $request['operations'][0]['update']['amount_micros'] === '12500000'
            && $request['operations'][0]['update']['resourceName'] === 'customers/1234567890/campaignBudgets/222';
    });
});

it('normalizes a dashed customer id', function () {
    Http::fake([
        'googleads.googleapis.com/*' => Http::response([], 200),
    ]);

    GoogleAdsClient::search('SELECT campaign.id FROM campaign', '123-456-7890');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/customers/1234567890/'));
});

it('rejects an invalid customer id', function () {
    expect(fn () => GoogleAdsClient::search('SELECT campaign.id FROM campaign', 'not-an-id'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an empty customer id', function () {
    config()->set('google-ads.customer_id', null);

    expect(fn () => GoogleAdsClient::search('SELECT campaign.id FROM campaign'))
        ->toThrow(InvalidArgumentException::class);
});
