<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth Access Token
    |--------------------------------------------------------------------------
    |
    | The OAuth bearer token used to authenticate requests to the Google Ads
    | API. See:
    | https://developers.google.com/google-ads/api/docs/oauth/overview
    |
    */
    'access_token' => env('GOOGLE_ADS_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Developer Token
    |--------------------------------------------------------------------------
    |
    | The developer token that identifies your application to the Google Ads
    | API. See:
    | https://developers.google.com/google-ads/api/docs/get-started/dev-token
    |
    */
    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Customer ID
    |--------------------------------------------------------------------------
    |
    | The default Google Ads customer (account) id, without dashes, requests
    | are sent against when no id is explicitly passed to the client.
    |
    */
    'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Login Customer ID
    |--------------------------------------------------------------------------
    |
    | Optional manager (MCC) account id sent as the `login-customer-id`
    | header, required when the credentials belong to a manager account
    | acting on behalf of a client account. See:
    | https://developers.google.com/google-ads/api/docs/concepts/call-structure
    |
    */
    'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The Google Ads API version segment used in request URLs. Google sunsets
    | old versions on a rolling schedule, check the current supported
    | versions at:
    | https://developers.google.com/google-ads/api/docs/release-notes
    |
    */
    'version' => env('GOOGLE_ADS_API_VERSION', 'v19'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait for a response before giving up.
    |
    */
    'timeout' => (int) env('GOOGLE_ADS_TIMEOUT', 8),
];
