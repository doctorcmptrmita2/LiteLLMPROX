<?php

/**
 * CodexFlow Configuration
 * 
 * This file contains all business logic settings including plans,
 * quotas, decompose pipeline, and pricing.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Plan Definitions
    |--------------------------------------------------------------------------
    */
    'plans' => [
        // ==========================================
        // TRIAL PLAN - 24 Saat Ücretsiz Deneme
        // ==========================================
        'trial_free' => [
            'name' => 'Deneme (24 Saat)',
            'price_try' => 0,
            'is_trial' => true,
            'trial_hours' => 24,
            'requires_card' => false,
            'max_cost_usd' => 0.50,

            'trial_quotas' => [
                'fast' => [
                    'input_tokens' => 200_000,
                    'output_tokens' => 40_000,
                    'requests' => 100,
                ],
                'deep' => [
                    'input_tokens' => 50_000,
                    'output_tokens' => 10_000,
                    'requests' => 20,
                ],
            ],

            // Grace Lane - KOTA BİTİNCE LLAMA FREE İLE SINIRSIZ DEVAM!
            'grace_unlimited' => true,
            'grace_fallback_on_quota_exhausted' => true,

            'planner_pool' => [
                'tokens' => 10_000,
            ],

            'on_expire' => 'suspend',
            'upgrade_prompt' => true,
            'upgrade_discount' => 0.10,
        ],

        // ==========================================
        // STARTER PLAN - 500 TL/ay
        // ==========================================
        'starter_500_try' => [
            'name' => 'Starter',
            'price_try' => 500,
            'target_margin' => 0.30,
            'max_cost_usd' => 10.00,

            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 3_000_000,
                    'output_tokens' => 600_000,
                    'requests' => 800,
                ],
                'deep' => [
                    'input_tokens' => 200_000,
                    'output_tokens' => 40_000,
                    'requests' => 80,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 40, 'tokens' => 120_000],
                'deep' => ['requests' => 4, 'tokens' => 18_000],
            ],
            'grace_daily' => [
                'requests' => 30,
                'tokens' => 80_000,
            ],
            'planner_pool' => [
                'monthly_tokens' => 50_000,
            ],
        ],

        // ==========================================
        // PRO PLAN - 1000 TL/ay
        // ==========================================
        'pro_1000_try' => [
            'name' => 'Pro',
            'price_try' => 1000,
            'target_margin' => 0.30,
            'max_cost_usd' => 20.00,

            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 6_000_000,
                    'output_tokens' => 1_200_000,
                    'requests' => 1500,
                ],
                'deep' => [
                    'input_tokens' => 400_000,
                    'output_tokens' => 80_000,
                    'requests' => 150,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 80, 'tokens' => 250_000],
                'deep' => ['requests' => 8, 'tokens' => 35_000],
            ],
            'grace_daily' => [
                'requests' => 50,
                'tokens' => 150_000,
            ],
            'planner_pool' => [
                'monthly_tokens' => 100_000,
            ],
        ],

        // ==========================================
        // TEAM PLAN - 2500 TL/ay
        // ==========================================
        'team_2500_try' => [
            'name' => 'Team',
            'price_try' => 2500,
            'seats' => 5,
            'target_margin' => 0.30,
            'max_cost_usd' => 50.00,

            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 15_000_000,
                    'output_tokens' => 3_000_000,
                    'requests' => 4000,
                ],
                'deep' => [
                    'input_tokens' => 1_000_000,
                    'output_tokens' => 200_000,
                    'requests' => 400,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 200, 'tokens' => 600_000],
                'deep' => ['requests' => 20, 'tokens' => 90_000],
            ],
            'grace_daily' => [
                'requests' => 120,
                'tokens' => 400_000,
            ],
            'planner_pool' => [
                'monthly_tokens' => 250_000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Settings
    |--------------------------------------------------------------------------
    */
    'trial' => [
        'duration_hours' => env('TRIAL_DURATION_HOURS', 24),
        'plan_code' => 'trial_free',
        'grace_on_quota_exhausted' => true,
        'grace_unlimited_during_trial' => true,

        'limits' => [
            'max_trials_per_email_domain' => 3,
            'max_trials_per_ip' => 2,
            'disposable_email_block' => true,
            'require_email_verification' => true,
        ],

        'conversion' => [
            'reminder_hours' => [12, 20, 23],
            'extend_on_feedback' => false,
            'discount_on_upgrade' => 0.10,
            'discount_valid_hours' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Decompose Pipeline Settings
    |--------------------------------------------------------------------------
    */
    'decompose' => [
        'enabled' => false, // DISABLED: Causing loop issues with Cursor IDE
        'triggers' => [
            'header' => 'x-decompose',
            'min_input_tokens' => 50000,  // Raised to avoid false triggers
            'min_char_length' => 150000,  // Raised significantly
        ],
        'limits' => [
            'max_planner_calls' => 1,
            'max_chunks' => 3,
            'max_total_calls' => 4,
            'max_files_per_chunk' => 5,
            'total_timeout_seconds' => 480,
        ],
        'chunk_limits' => [
            'fast' => ['max_output_tokens' => 700, 'timeout' => 60],
            'deep' => ['max_output_tokens' => 1200, 'timeout' => 120],
        ],
        'planner' => [
            'max_output_tokens' => 500,
            'timeout' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admission Control (Token Clamping)
    |--------------------------------------------------------------------------
    */
    'admission' => [
        'fast' => ['max_input' => 8000, 'max_output' => 900, 'timeout' => 60],
        'deep' => ['max_input' => 16000, 'max_output' => 1400, 'timeout' => 120],
        'grace' => ['max_input' => 8000, 'max_output' => 800, 'timeout' => 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'version' => 'v1',
        'only_deterministic' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'per_key_per_minute' => 60,
        'per_user_per_minute' => 120,
        'burst_allowance' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'llm_requests_days' => 21,
        'aggregates_months' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Calculation (USD per 1M tokens)
    |--------------------------------------------------------------------------
    */
    'costs' => [
        'fast' => ['input' => 0.80, 'output' => 4.00],
        'deep' => ['input' => 3.00, 'output' => 15.00],
        'planner' => ['input' => 0.15, 'output' => 0.60],
        'grace' => ['input' => 0.00, 'output' => 0.00],          // Llama FREE!
        'grace_fallback' => ['input' => 0.15, 'output' => 0.60], // GPT-4o-mini
    ],

    /*
    |--------------------------------------------------------------------------
    | API Key Settings
    |--------------------------------------------------------------------------
    */
    'api_keys' => [
        'prefix' => 'cf_',
        'length' => 40,
        'hash_algo' => 'bcrypt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'plan' => env('DEFAULT_PLAN', 'trial_free'),
        'tier' => 'fast',
    ],
];



