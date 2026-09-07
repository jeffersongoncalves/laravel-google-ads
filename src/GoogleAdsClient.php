<?php

namespace JeffersonGoncalves\GoogleAds;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Google Ads REST API HTTP layer. Wraps GAQL search (via searchStream),
 * campaign/budget mutate calls, and a handful of report shortcuts behind a
 * small static client, threading the OAuth bearer token and developer
 * token, and returning null/[] sentinels on ordinary HTTP failures instead
 * of throwing.
 */
class GoogleAdsClient
{
    private const BASE_URL = 'https://googleads.googleapis.com';

    /**
     * Run a raw GAQL query and return the flattened list of result rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, ?string $customerId = null): array
    {
        $customerId = self::normalizeCustomerId($customerId ?? self::customerId());

        $response = self::request('post', "/customers/{$customerId}/googleAds:searchStream", 'google_ads_search', [
            'query' => $query,
        ]);

        $chunks = self::jsonOrNull($response);

        if ($chunks === null) {
            return [];
        }

        $rows = [];

        foreach ($chunks as $chunk) {
            foreach ($chunk['results'] ?? [] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function accountInfo(?string $customerId = null): ?array
    {
        $rows = self::search('SELECT customer.id, customer.descriptive_name FROM customer', $customerId);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function campaigns(?string $customerId = null): array
    {
        return self::search(
            'SELECT campaign.id, campaign.name, campaign.status, campaign_budget.amount_micros FROM campaign ORDER BY campaign.id',
            $customerId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function campaignsPerformance(string|int|null $days = null, ?string $customerId = null): array
    {
        $dateRange = self::daysToDateRange($days);

        return self::search(
            "SELECT campaign.name, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions FROM campaign WHERE segments.date DURING {$dateRange}",
            $customerId,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function pauseCampaign(string $campaignId, ?string $customerId = null): ?array
    {
        return self::mutateCampaignStatus($campaignId, 'PAUSED', $customerId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function enableCampaign(string $campaignId, ?string $customerId = null): ?array
    {
        return self::mutateCampaignStatus($campaignId, 'ENABLED', $customerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function adGroupsPerformance(string|int|null $days = null, ?int $limit = null, ?string $customerId = null): array
    {
        $dateRange = self::daysToDateRange($days);
        $limitClause = $limit !== null ? " LIMIT {$limit}" : '';

        return self::search(
            "SELECT ad_group.name, metrics.impressions, metrics.clicks, metrics.conversions FROM ad_group WHERE segments.date DURING {$dateRange}{$limitClause}",
            $customerId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function keywordsPerformance(string|int|null $days = null, int $limit = 50, ?string $customerId = null): array
    {
        $dateRange = self::daysToDateRange($days);

        return self::search(
            "SELECT ad_group_criterion.keyword.text, metrics.impressions, metrics.clicks, metrics.average_cpc FROM keyword_view WHERE segments.date DURING {$dateRange} ORDER BY metrics.clicks DESC LIMIT {$limit}",
            $customerId,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function updateBudget(string $budgetId, float $amount, ?string $customerId = null): ?array
    {
        $customerId = self::normalizeCustomerId($customerId ?? self::customerId());
        $amountMicros = (string) round($amount * 1_000_000);

        $response = self::request('post', "/customers/{$customerId}/campaignBudgets:mutate", 'google_ads_update_budget', [
            'operations' => [[
                'update' => [
                    'resourceName' => "customers/{$customerId}/campaignBudgets/{$budgetId}",
                    'amount_micros' => $amountMicros,
                ],
                'updateMask' => 'amount_micros',
            ]],
        ]);

        return self::jsonOrNull($response);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mutateCampaignStatus(string $campaignId, string $status, ?string $customerId): ?array
    {
        $customerId = self::normalizeCustomerId($customerId ?? self::customerId());

        $response = self::request('post', "/customers/{$customerId}/campaigns:mutate", 'google_ads_mutate_campaign_status', [
            'operations' => [[
                'update' => [
                    'resourceName' => "customers/{$customerId}/campaigns/{$campaignId}",
                    'status' => $status,
                ],
                'updateMask' => 'status',
            ]],
        ]);

        return self::jsonOrNull($response);
    }

    private static function daysToDateRange(string|int|null $days = null): string
    {
        $days = (int) ($days ?: 30);

        return match ($days) {
            7 => 'LAST_7_DAYS',
            14 => 'LAST_14_DAYS',
            30 => 'LAST_30_DAYS',
            90 => 'LAST_90_DAYS',
            default => "LAST_{$days}_DAYS",
        };
    }

    /**
     * Shared request/response handling: attaches the bearer token, developer
     * token, and optional login-customer-id header, catches transport
     * failures, and logs them rather than throwing.
     *
     * @param  'get'|'post'  $method
     * @param  array<string, mixed>  $body
     */
    private static function request(string $method, string $path, string $context, array $body = []): ?Response
    {
        $url = self::BASE_URL.'/'.self::version().$path;

        $headers = [];

        if ($token = self::accessToken()) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        if ($devToken = self::developerToken()) {
            $headers['developer-token'] = $devToken;
        }

        if ($loginCustomerId = self::loginCustomerId()) {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        try {
            $request = Http::timeout(self::timeout())->withHeaders($headers);

            return $method === 'get' ? $request->get($url) : $request->post($url, $body);
        } catch (Throwable $e) {
            self::logFailure($context, $url, $e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function jsonOrNull(?Response $response): ?array
    {
        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Accepts a bare numeric customer id, stripping dashes and a leading
     * `customers/` prefix.
     *
     * @throws InvalidArgumentException
     */
    private static function normalizeCustomerId(?string $customerId): string
    {
        $customerId = str_replace('-', '', (string) $customerId);
        $customerId = preg_replace('#^customers/#', '', $customerId) ?? $customerId;

        if ($customerId === '' || preg_match('/^\d+$/', $customerId) !== 1) {
            throw new InvalidArgumentException("Invalid Google Ads customer id: [{$customerId}]. Expected a numeric id, optionally dashed or prefixed with 'customers/'.");
        }

        return $customerId;
    }

    private static function logFailure(string $context, string $target, Throwable $e): void
    {
        Log::warning('GoogleAdsClient outbound fetch failed', [
            'context' => $context,
            'target' => $target,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }

    private static function accessToken(): ?string
    {
        $token = config('google-ads.access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private static function developerToken(): ?string
    {
        $token = config('google-ads.developer_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private static function loginCustomerId(): ?string
    {
        $id = config('google-ads.login_customer_id');

        return is_string($id) && $id !== '' ? str_replace('-', '', $id) : null;
    }

    private static function customerId(): ?string
    {
        $id = config('google-ads.customer_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private static function version(): string
    {
        $version = config('google-ads.version', 'v19');

        return is_string($version) && $version !== '' ? $version : 'v19';
    }

    private static function timeout(): int
    {
        return (int) config('google-ads.timeout', 8);
    }
}
