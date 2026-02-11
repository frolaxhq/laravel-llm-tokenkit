# LLM TokenKit for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/frolax/laravel-llm-tokenkit.svg?style=flat-square)](https://packagist.org/packages/frolax/laravel-llm-tokenkit)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/frolax/laravel-llm-tokenkit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/frolax/laravel-llm-tokenkit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/frolax/laravel-llm-tokenkit.svg?style=flat-square)](https://packagist.org/packages/frolax/laravel-llm-tokenkit)

**Token estimation, cost calculation & context-window utilities for LLM-powered Laravel apps.**

TokenKit is a **stateless** Laravel package that helps you:

- 📊 **Estimate tokens** for text and chat message arrays
- 💰 **Calculate costs** based on provider-specific pricing
- 📐 **Build context windows** with rolling window and token-budget truncation strategies

No database, no migrations, no external API calls. Works offline.

## Installation

```bash
composer require frolax/laravel-llm-tokenkit
```

Publish the config file:

```bash
php artisan vendor:publish --tag="llm-tokenkit-config"
```

## Quick Start

```php
use Frolax\LlmTokenKit\Facades\LlmTokenKit;
use Frolax\LlmTokenKit\Data\ModelRef;

$model = new ModelRef(provider: 'openai', model: 'gpt-4o');

// Or use a simple array:
$model = ['provider' => 'openai', 'model' => 'gpt-4o'];
```

### Estimate Tokens for Text

```php
$estimate = LlmTokenKit::estimateText('Hello, world!', $model);

$estimate->inputTokensEstimated; // ~4
$estimate->confidence;           // Confidence::Medium
$estimate->method;               // EstimationMethod::Heuristic
$estimate->notes;                // ['...']
```

### Estimate Tokens for Chat Messages

```php
$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is Laravel?'],
];

$estimate = LlmTokenKit::estimateMessages($messages, $model);

$estimate->inputTokensEstimated; // ~18
```

### Calculate Cost

```php
use Frolax\LlmTokenKit\Data\TokenUsage;

$cost = LlmTokenKit::cost(
    usage: new TokenUsage(promptTokens: 1500, completionTokens: 500),
    model: $model,
);

$cost->inputCost;   // 0.00375
$cost->outputCost;  // 0.005
$cost->totalCost;   // 0.00875
$cost->currency;    // 'USD'
```

You can also pass arrays:

```php
$cost = LlmTokenKit::cost(
    usage: ['prompt_tokens' => 1500, 'completion_tokens' => 500],
    model: ['provider' => 'openai', 'model' => 'gpt-4o'],
);
```

Or provide explicit pricing:

```php
use Frolax\LlmTokenKit\Data\Pricing;

$cost = LlmTokenKit::cost(
    usage: new TokenUsage(promptTokens: 1000, completionTokens: 200),
    pricing: new Pricing(inputPer1m: 15.00, outputPer1m: 60.00, reasoningPer1m: 60.00),
);
```

### Build Context Window

```php
use Frolax\LlmTokenKit\Data\ContextBuildRequest;
use Frolax\LlmTokenKit\Enums\ContextStrategy;

$result = LlmTokenKit::buildContext(new ContextBuildRequest(
    system: 'You are a helpful assistant.',
    memorySummary: 'User prefers dark mode.',
    historyMessages: $chatHistory,
    newUserMessage: 'How do I use Eloquent?',
    modelRef: $model,
    tokenBudget: 4000,
    reservedOutputTokens: 1024,
    strategy: ContextStrategy::TruncateByTokens,
));

$result->messages;            // Ready-to-send message array
$result->tokenEstimate;       // TokenEstimate DTO
$result->wasTruncated;        // true if messages were dropped
$result->droppedMessageCount; // Number of dropped messages
$result->warnings;            // Any warnings
```

Or pass an array:

```php
$result = LlmTokenKit::buildContext([
    'system' => 'You are helpful.',
    'history_messages' => $chatHistory,
    'new_user_message' => 'Hello!',
    'model_ref' => ['provider' => 'openai', 'model' => 'gpt-4o'],
    'token_budget' => 4000,
    'strategy' => 'truncate_by_tokens',
]);
```

## Configuration

### Pricing

Pricing is resolved in this order:

1. **Exact model match** — `pricing.providers.{provider}.models.{model}`
2. **Wildcard match** — e.g. `gpt-4.1*` matches `gpt-4.1-mini`
3. **Provider default** — `pricing.providers.{provider}.default`
4. **Global default** — `pricing.default`

```php
// config/llm-tokenkit.php
'pricing' => [
    'default' => [
        'input_per_1m' => 1.00,
        'output_per_1m' => 2.00,
        'currency' => 'USD',
    ],
    'providers' => [
        'openai' => [
            'models' => [
                'gpt-4o' => ['input_per_1m' => 2.50, 'output_per_1m' => 10.00],
                'gpt-4.1*' => ['input_per_1m' => 2.00, 'output_per_1m' => 8.00],
            ],
            'default' => ['input_per_1m' => 2.50, 'output_per_1m' => 10.00],
        ],
    ],
],
```

### Estimation

```php
'estimation' => [
    'code_json_penalty' => 1.3,       // Multiplier for code/JSON
    'enable_code_json_penalty' => true,
    'language_multipliers' => [
        'bn' => 2.5,  // Bangla
        'hi' => 2.0,  // Hindi
        'ar' => 2.0,  // Arabic
        'zh' => 1.5,  // Chinese
    ],
],
```

### Context Builder

```php
'context' => [
    'reserved_output_tokens' => 4096,
    'window_size' => 50,
    'default_strategy' => 'rolling_window',
    'tool_token_threshold' => 200,
],
```

## Artisan Command

Check which estimation backend is active:

```bash
php artisan tokenkit:check
```

This will report active estimators, detected tokenizer libraries, sample estimates, and pricing configuration. **No network calls are made.**

## ⚠️ Important Notes

- Token estimates are **approximations**. Different providers/models tokenize differently.
- The heuristic estimator uses a `chars/4` base rule, which is reasonable for GPT models but may vary.
- For non-Latin scripts (Bangla, Hindi, Arabic, CJK), multipliers are applied but accuracy is lower.
- **Always compare estimates against actual provider usage** before relying on them for billing.

### Suggested Defaults for SaaS

If building a SaaS product, consider:
- Adding a **10-15% buffer** to cost estimates for safety
- Using `Confidence::Medium` or higher estimates for billing purposes
- Regularly updating pricing in your config to match provider changes
- Implementing usage tracking with **actual provider-reported tokens** alongside estimates

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
