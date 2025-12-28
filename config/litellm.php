<?php

/**
 * LiteLLM Proxy Configuration
 * 
 * This file contains all settings for connecting to the LiteLLM proxy
 * and managing the role-based model aliases.
 * 
 * Version: 3.0 (Role-Based Pipeline Edition)
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
    | Role-Based Pipeline aliases:
    | - Triage & Planning: cf-triage, cf-planner
    | - Coding Tiers: cf-cheap-coder, cf-balanced-coder, cf-premium-coder
    | - Review: cf-budget-reviewer
    | - Fallback: cf-oss-fallback, cf-grace, cf-grace-fallback
    | - Legacy: cf-fast, cf-deep (backward compatibility)
    |
    */
    'aliases' => [
        // === TRIAGE & PLANNING ===
        'triage' => 'cf-triage',                    // GPT-4o-mini (cheap classification)
        'planner' => 'cf-planner',                  // GPT-4o-mini (JSON plan generation)
        
        // === CODING TIERS ===
        'cheap_coder' => 'cf-cheap-coder',          // Claude Haiku 3.5 (quick fixes)
        'balanced_coder' => 'cf-balanced-coder',    // Claude Sonnet 4 (medium tasks)
        'premium_coder' => 'cf-premium-coder',      // Claude Sonnet 4.5 (critical)
        
        // === REVIEW ===
        'budget_reviewer' => 'cf-budget-reviewer',  // DeepSeek V3 (cheap review)
        
        // === FALLBACK ===
        'oss_fallback' => 'cf-oss-fallback',        // DeepSeek V3 (provider issues)
        'grace' => 'cf-grace',                      // Llama 405B FREE (quota exhausted)
        'grace_fallback' => 'cf-grace-fallback',    // GPT-4o-mini (if Llama fails)
        
        // === AGENT ===
        'agent' => 'cf-agent',                      // Grok 3 Beta (2M context!)
        'grok_tools' => 'cf-grok-tools',            // Grok 4.1 Fast (Background tool ops)
        
        // === LEGACY (backward compatibility) ===
        'fast' => 'cf-fast',                        // → cf-cheap-coder
        'deep' => 'cf-deep',                        // → cf-balanced-coder
    ],

    /*
    |--------------------------------------------------------------------------
    | Role-Based Tier Configurations
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        // === TRIAGE ===
        'triage' => [
            'timeout' => 15,
            'max_input_tokens' => 8000,
            'max_output_tokens' => 800,
            'description' => 'Task classification and risk scoring',
        ],
        
        // === PLANNER ===
        'planner' => [
            'timeout' => 45,
            'max_input_tokens' => 32000,
            'max_output_tokens' => 2000,
            'description' => 'Step plan generation (JSON)',
        ],
        
        // === CHEAP CODER ===
        'cheap_coder' => [
            'timeout' => 60,
            'max_input_tokens' => 16000,
            'max_output_tokens' => 3000,
            'description' => 'Small fixes, tests, simple tasks',
        ],
        
        // === BALANCED CODER ===
        'balanced_coder' => [
            'timeout' => 120,
            'max_input_tokens' => 64000,
            'max_output_tokens' => 6000,
            'description' => 'Medium complexity, 2-5 file changes',
        ],
        
        // === PREMIUM CODER ===
        'premium_coder' => [
            'timeout' => 180,
            'max_input_tokens' => 64000,
            'max_output_tokens' => 6000,
            'description' => 'High/critical risk, auth/billing/webhooks',
        ],
        
        // === BUDGET REVIEWER ===
        'budget_reviewer' => [
            'timeout' => 90,
            'max_input_tokens' => 32000,
            'max_output_tokens' => 2500,
            'description' => 'Code review for low/medium risk',
        ],
        
        // === OSS FALLBACK ===
        'oss_fallback' => [
            'timeout' => 90,
            'max_input_tokens' => 32000,
            'max_output_tokens' => 4000,
            'description' => 'Fallback when providers fail',
        ],
        
        // === GRACE ===
        'grace' => [
            'timeout' => 90,
            'max_input_tokens' => 16000,
            'max_output_tokens' => 2000,
            'description' => 'Quota exhausted fallback (FREE)',
        ],
        
        // === AGENT ===
        'agent' => [
            'timeout' => 180,
            'max_input_tokens' => 100000,  // 100K input (2M available!)
            'max_output_tokens' => 4000,
            'description' => 'Agent/tool operations with huge context',
        ],
        
        // === GROK TOOLS (Background Agent) ===
        'grok_tools' => [
            'timeout' => 300,              // 5 min for heavy operations
            'max_input_tokens' => 500000,  // 500K input (2M available!)
            'max_output_tokens' => 8000,
            'reasoning_default' => false,  // Disabled for cost efficiency
            'description' => 'Background tool operations with 2M context',
        ],
        
        // === LEGACY TIERS (backward compatibility) ===
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Costs (USD per 1M tokens) - For cost calculation
    |--------------------------------------------------------------------------
    */
    'costs' => [
        'triage' => ['input' => 0.15, 'output' => 0.60],           // GPT-4o-mini
        'planner' => ['input' => 0.15, 'output' => 0.60],          // GPT-4o-mini
        'cheap_coder' => ['input' => 0.80, 'output' => 4.00],      // Claude Haiku 3.5
        'balanced_coder' => ['input' => 3.00, 'output' => 15.00],  // Claude Sonnet 4
        'premium_coder' => ['input' => 3.00, 'output' => 15.00],   // Claude Sonnet 4.5
        'budget_reviewer' => ['input' => 0.14, 'output' => 0.28],  // DeepSeek V3
        'oss_fallback' => ['input' => 0.14, 'output' => 0.28],     // DeepSeek V3
        'grace' => ['input' => 0.00, 'output' => 0.00],            // Llama 405B FREE!
        'grace_fallback' => ['input' => 0.15, 'output' => 0.60],   // GPT-4o-mini
        'agent' => ['input' => 3.00, 'output' => 15.00],           // Grok 3 Beta
        'grok_tools' => ['input' => 3.00, 'output' => 15.00],    // Grok 4.1 Fast
        // Legacy
        'fast' => ['input' => 0.80, 'output' => 4.00],
        'deep' => ['input' => 3.00, 'output' => 15.00],
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
