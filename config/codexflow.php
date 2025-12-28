<?php

/**
 * CodexFlow Configuration
 * 
 * This file contains all business logic settings including plans,
 * quotas, role-based pipeline, and pricing.
 * 
 * Version: 3.0 (Role-Based Pipeline Edition)
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
            'target_margin' => 0.25, // %25 kar marjı
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
            'target_margin' => 0.25, // %25 kar marjı
            'max_cost_usd' => 21.50, // 750 TL / 35

            'monthly_quotas' => [
                // Cheap tier (Haiku) - cömert
                'cheap' => [
                    'requests' => 2000,
                    'tokens' => 10_000_000,
                ],
                // Balanced tier (Sonnet 4) - kontrollü
                'balanced' => [
                    'input_tokens' => 3_000_000,
                    'output_tokens' => 600_000,
                    'requests' => 400,
                ],
                // Premium tier (Sonnet 4.5) - kısıtlı
                'premium' => [
                    'input_tokens' => 500_000,
                    'output_tokens' => 100_000,
                    'requests' => 50,
                ],
                // Legacy mapping
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
                'cheap' => ['requests' => 100, 'tokens' => 400_000],
                'balanced' => ['requests' => 20, 'tokens' => 150_000],
                'premium' => ['requests' => 5, 'tokens' => 50_000],
            ],
            'grace_daily' => [
                'requests' => 100,
                'tokens' => 500_000,
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
            'target_margin' => 0.25,
            'max_cost_usd' => 53.50, // 1875 TL / 35

            'monthly_quotas' => [
                'cheap' => [
                    'requests' => 5000,
                    'tokens' => 25_000_000,
                ],
                'balanced' => [
                    'input_tokens' => 7_500_000,
                    'output_tokens' => 1_500_000,
                    'requests' => 1000,
                ],
                'premium' => [
                    'input_tokens' => 1_250_000,
                    'output_tokens' => 250_000,
                    'requests' => 125,
                ],
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
                'cheap' => ['requests' => 250, 'tokens' => 1_000_000],
                'balanced' => ['requests' => 50, 'tokens' => 375_000],
                'premium' => ['requests' => 10, 'tokens' => 100_000],
            ],
            'grace_daily' => [
                'requests' => 200,
                'tokens' => 1_000_000,
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
    | Role-Based Pipeline Configuration
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        'enabled' => env('PIPELINE_ENABLED', true),
        
        // Pipeline stages and their model assignments
        'stages' => [
            'triage' => [
                'model_alias' => 'cf-triage',
                'max_output_tokens' => 800,
                'timeout' => 15,
                'description' => 'Task classification and risk scoring',
            ],
            'plan' => [
                'model_alias' => 'cf-planner',
                'max_output_tokens' => 2000,
                'timeout' => 45,
                'description' => 'Step plan generation',
            ],
            'code_cheap' => [
                'model_alias' => 'cf-cheap-coder',
                'max_output_tokens' => 3000,
                'timeout' => 60,
                'description' => 'Low risk coding',
            ],
            'code_balanced' => [
                'model_alias' => 'cf-balanced-coder',
                'max_output_tokens' => 6000,
                'timeout' => 120,
                'description' => 'Medium risk coding',
            ],
            'code_premium' => [
                'model_alias' => 'cf-premium-coder',
                'max_output_tokens' => 6000,
                'timeout' => 180,
                'description' => 'High/critical risk coding',
            ],
            'review_budget' => [
                'model_alias' => 'cf-budget-reviewer',
                'max_output_tokens' => 2500,
                'timeout' => 90,
                'description' => 'Budget code review',
            ],
            'review_premium' => [
                'model_alias' => 'cf-premium-coder',
                'max_output_tokens' => 2500,
                'timeout' => 120,
                'description' => 'Premium code review for critical',
            ],
            'test' => [
                'model_alias' => 'cf-cheap-coder',
                'max_output_tokens' => 3500,
                'timeout' => 90,
                'description' => 'Test generation',
            ],
        ],
        
        // Quality gates configuration
        'quality_gates' => [
            'plan_required' => [
                'stage' => 'plan',
                'require' => 'step_plan',
                'on_fail' => 'abort',
            ],
            'patch_only' => [
                'stage' => 'code',
                'require' => 'unified_diff',
                'on_fail' => 'retry_with_strict_prompt',
            ],
            'must_fix_zero' => [
                'stage' => 'review',
                'rule' => 'must_fix_count_eq_0',
                'on_fail' => 'reroute_to_coding',
            ],
            'tests_required' => [
                'stage' => 'test',
                'when' => 'risk_medium_plus',
                'on_fail' => 'reroute_to_test',
            ],
            'safety_gate' => [
                'stage' => 'final_review',
                'when' => 'sensitive_domain',
                'require' => ['risk_notes', 'test_gaps'],
                'on_fail' => 'reroute_to_review',
            ],
        ],
        
        // Rework loop settings
        'rework' => [
            'max_iterations' => 3,
            'escalate_on_limit' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget Classification Rules
    |--------------------------------------------------------------------------
    */
    'budget' => [
        // Domains that trigger critical risk
        'critical_domains' => [
            'auth', 'billing', 'payment', 'webhooks', 
            'encryption', 'acl', 'permissions',
        ],
        
        // Domains that trigger high risk
        'high_risk_domains' => [
            'queue', 'cron', 'concurrency', 'caching',
            'rate_limit', 'retry', 'data_consistency',
        ],
        
        // Risk escalation rules (evaluated top-down)
        'risk_escalation' => [
            ['domains' => 'critical_domains', 'set_risk' => 'critical'],
            ['files_gte' => 3, 'set_risk' => 'high'],
            ['domains' => 'high_risk_domains', 'set_risk' => 'high'],
            ['files_between' => [2, 3], 'set_risk' => 'medium'],
        ],
        
        // Budget class assignment rules
        'class_rules' => [
            ['risk_in' => ['high', 'critical'], 'set_class' => 'premium'],
            ['risk_in' => ['medium'], 'set_class' => 'balanced'],
            ['files_between' => [2, 5], 'set_class' => 'balanced'],
            ['default' => true, 'set_class' => 'cheap'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Control Settings
    |--------------------------------------------------------------------------
    */
    'cost_control' => [
        // Per-request cost caps by budget class
        'per_request_cap_usd' => [
            'cheap' => 0.01,
            'balanced' => 0.20,
            'premium' => 0.50,
        ],
        
        // Downgrade behavior when cap is hit
        'downgrade_on_cap' => true,
        'downgrade_order' => ['premium', 'balanced', 'cheap'],
        
        // Fallback pool when all budgets exhausted
        'fallback_pool' => ['cf-oss-fallback', 'cf-grace'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Decompose Pipeline Settings (Legacy - for backward compatibility)
    |--------------------------------------------------------------------------
    */
    'decompose' => [
        'enabled' => false, // Use pipeline instead
        'triggers' => [
            'header' => 'x-decompose',
            'min_input_tokens' => 50000,
            'min_char_length' => 150000,
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
        // Role-based tiers
        'triage' => ['max_input' => 8000, 'max_output' => 800, 'timeout' => 15],
        'planner' => ['max_input' => 32000, 'max_output' => 2000, 'timeout' => 45],
        'cheap_coder' => ['max_input' => 16000, 'max_output' => 3000, 'timeout' => 60],
        'balanced_coder' => ['max_input' => 64000, 'max_output' => 6000, 'timeout' => 120],
        'premium_coder' => ['max_input' => 64000, 'max_output' => 6000, 'timeout' => 180],
        'budget_reviewer' => ['max_input' => 32000, 'max_output' => 2500, 'timeout' => 90],
        'grace' => ['max_input' => 16000, 'max_output' => 2000, 'timeout' => 90],
        'agent' => ['max_input' => 100000, 'max_output' => 4000, 'timeout' => 180],
        'grok_tools' => ['max_input' => 500000, 'max_output' => 8000, 'timeout' => 300],
        // Legacy tiers
        'fast' => ['max_input' => 8000, 'max_output' => 900, 'timeout' => 60],
        'deep' => ['max_input' => 16000, 'max_output' => 1400, 'timeout' => 120],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'version' => 'v2', // Updated for pipeline
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
        // Role-based tiers
        'triage' => ['input' => 0.15, 'output' => 0.60],           // GPT-4o-mini
        'planner' => ['input' => 0.15, 'output' => 0.60],          // GPT-4o-mini
        'cheap_coder' => ['input' => 0.80, 'output' => 4.00],      // Claude Haiku 3.5
        'balanced_coder' => ['input' => 3.00, 'output' => 15.00],  // Claude Sonnet 4
        'premium_coder' => ['input' => 3.00, 'output' => 15.00],   // Claude Sonnet 4.5
        'budget_reviewer' => ['input' => 0.14, 'output' => 0.28],  // DeepSeek V3
        'oss_fallback' => ['input' => 0.14, 'output' => 0.28],     // DeepSeek V3
        'grace' => ['input' => 0.00, 'output' => 0.00],            // Llama FREE!
        'grace_fallback' => ['input' => 0.15, 'output' => 0.60],   // GPT-4o-mini
        'agent' => ['input' => 3.00, 'output' => 15.00],           // Grok 3 Beta
        'grok_tools' => ['input' => 3.00, 'output' => 15.00],    // Grok 4.1 Fast (Background)
        // Legacy tiers
        'fast' => ['input' => 0.80, 'output' => 4.00],
        'deep' => ['input' => 3.00, 'output' => 15.00],
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
        'tier' => 'cheap_coder', // Updated for role-based
        'budget_class' => 'cheap',
    ],
];
