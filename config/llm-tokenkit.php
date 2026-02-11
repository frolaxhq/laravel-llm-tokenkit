<?php

// config for Frolax/LlmTokenKit
return [

    /*
    |--------------------------------------------------------------------------
    | Token Estimation Settings
    |--------------------------------------------------------------------------
    */
    'estimation' => [

        /*
         * Multiplier applied when code or JSON content is detected.
         * Code/JSON typically tokenizes less efficiently than natural language.
         */
        'code_json_penalty' => 1.3,

        /*
         * Enable or disable the code/JSON penalty.
         */
        'enable_code_json_penalty' => true,

        /*
         * Language-specific multipliers for non-Latin scripts.
         * These account for the fact that non-Latin characters often
         * require more tokens per character.
         */
        'language_multipliers' => [
            'bn' => 2.5,  // Bangla / Bengali
            'hi' => 2.0,  // Hindi / Devanagari
            'ar' => 2.0,  // Arabic
            'zh' => 1.5,  // Chinese
            'ja' => 1.5,  // Japanese
            'ko' => 1.5,  // Korean
            'th' => 2.0,  // Thai
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Builder Defaults
    |--------------------------------------------------------------------------
    */
    'context' => [

        /*
         * Default number of tokens reserved for the model's output.
         */
        'reserved_output_tokens' => 4096,

        /*
         * Default rolling window size (number of messages to keep).
         */
        'window_size' => 50,

        /*
         * Default context strategy: 'rolling_window' or 'truncate_by_tokens'.
         */
        'default_strategy' => 'rolling_window',

        /*
         * Maximum token count for tool messages when using 'include_small' policy.
         * Tool messages exceeding this threshold will be replaced with a placeholder.
         */
        'tool_token_threshold' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Define per-provider, per-model pricing in cost per 1 million tokens.
    | Resolution order: exact model → wildcard → provider default → global default.
    |
    */
    'pricing' => [

        /*
         * Number of decimal places when rounding cost calculations.
         */
        'rounding_precision' => 6,

        /*
         * Global fallback pricing if no provider/model match is found.
         */
        'default' => [
            'input_per_1m' => 1.00,
            'output_per_1m' => 2.00,
            'currency' => 'USD',
        ],

        /*
         * Provider-specific pricing.
         * Each provider can have specific models, wildcard patterns, and a default.
         */
        'providers' => [

            'openai' => [
                'models' => [
                    'gpt-4o' => [
                        'input_per_1m' => 2.50,
                        'output_per_1m' => 10.00,
                        'currency' => 'USD',
                    ],
                    'gpt-4o-mini' => [
                        'input_per_1m' => 0.15,
                        'output_per_1m' => 0.60,
                        'currency' => 'USD',
                    ],
                    'gpt-4.1*' => [
                        'input_per_1m' => 2.00,
                        'output_per_1m' => 8.00,
                        'currency' => 'USD',
                    ],
                    'o1*' => [
                        'input_per_1m' => 15.00,
                        'output_per_1m' => 60.00,
                        'reasoning_per_1m' => 60.00,
                        'currency' => 'USD',
                    ],
                ],
                'default' => [
                    'input_per_1m' => 2.50,
                    'output_per_1m' => 10.00,
                    'currency' => 'USD',
                ],
            ],

            'anthropic' => [
                'models' => [
                    'claude-sonnet-4-20250514' => [
                        'input_per_1m' => 3.00,
                        'output_per_1m' => 15.00,
                        'currency' => 'USD',
                    ],
                    'claude-3-5-haiku*' => [
                        'input_per_1m' => 0.80,
                        'output_per_1m' => 4.00,
                        'currency' => 'USD',
                    ],
                ],
                'default' => [
                    'input_per_1m' => 3.00,
                    'output_per_1m' => 15.00,
                    'currency' => 'USD',
                ],
            ],

        ],
    ],
];
