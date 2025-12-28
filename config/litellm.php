<?php

/**
 * LiteLLM Proxy Configuration
 * 
 * This file contains all settings for connecting to the LiteLLM proxy
 * and managing the 5 model aliases.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | LiteLLM Proxy Connection
    |--------------------------------------------------------------------------
    */
    'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000'),
    'master_key' => env('LITELLM_MASTER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Model Aliases (LiteLLM'deki model_name'ler)
    |--------------------------------------------------------------------------
    |
    | Laravel sadece bu alias'ları bilir. Key havuzu ve load balancing
    | LiteLLM tarafında yönetilir.
    |
    */
    'aliases' => [
        'fast' => 'cf-fast',                    // Claude Haiku 3.5
        'deep' => 'cf-deep',                    // Claude Sonnet 4
        'planner' => 'cf-planner',              // GPT-4o-mini (JSON planner)
        'grace' => 'cf-grace',                  // Llama 405B FREE (OpenRouter)
        'grace_fallback' => 'cf-grace-fallback', // GPT-4o-mini (backup)
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier Configurations
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'fast' => [
            'timeout' => 60,
            'max_input_tokens' => 8000,
            'max_output_tokens' => 900,
        ],
        'deep' => [
            'timeout' => 120,
            'max_input_tokens' => 16000,
            'max_output_tokens' => 1400,
        ],
        'planner' => [
            'timeout' => 30,
            'max_input_tokens' => 12000,
            'max_output_tokens' => 500,
        ],
        'grace' => [
            'timeout' => 90,  // Longer for Llama FREE
            'max_input_tokens' => 8000,
            'max_output_tokens' => 800,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Policy (Laravel-level, on top of LiteLLM's internal retries)
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 2,
        'delay_ms' => 1000,
        'multiplier' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'forward_request_id' => true,
        'request_id_header' => 'X-Request-Id',
    ],
];


