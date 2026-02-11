<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Commands;

use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Estimators\EstimatorChain;
use Illuminate\Console\Command;

class LlmTokenKitCommand extends Command
{
    public $signature = 'tokenkit:check';

    public $description = 'Report which estimator backend is active and run sample estimates';

    public function handle(EstimatorChain $chain): int
    {
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║       TokenKit Diagnostics           ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->newLine();

        // 1. Active estimators
        $estimators = $chain->registeredEstimators();
        $this->components->twoColumnDetail('<fg=cyan>Registered estimators</>', implode(', ', $estimators));

        // 2. Check for optional tokenizer libraries
        $tokenizerDetected = false;
        $tokenizerLibs = [
            'Yethee\\Tiktoken\\Encoder' => 'yethee/tiktoken',
        ];

        foreach ($tokenizerLibs as $class => $package) {
            if (class_exists($class)) {
                $this->components->twoColumnDetail('<fg=cyan>Tokenizer lib detected</>', "<fg=green>{$package}</>");
                $tokenizerDetected = true;
            }
        }

        if (! $tokenizerDetected) {
            $this->components->twoColumnDetail('<fg=cyan>Tokenizer lib detected</>', '<fg=yellow>None (using heuristic only)</>');
        }

        $this->newLine();

        // 3. Sample estimates
        $this->info('─── Sample Estimates ───');
        $this->newLine();

        $model = new ModelRef(provider: 'openai', model: 'gpt-4');

        // English text
        $englishText = 'The quick brown fox jumps over the lazy dog. This is a sample sentence to test token estimation.';
        $englishEstimate = $chain->estimateText($englishText, $model);
        $this->components->twoColumnDetail(
            'English text ('.mb_strlen($englishText).' chars)',
            sprintf('<fg=green>~%d tokens</> (confidence: %s)', $englishEstimate->inputTokensEstimated, $englishEstimate->confidence->value),
        );

        // JSON
        $jsonText = '{"users": [{"name": "John", "age": 30}, {"name": "Jane", "age": 25}]}';
        $jsonEstimate = $chain->estimateText($jsonText, $model);
        $this->components->twoColumnDetail(
            'JSON payload ('.mb_strlen($jsonText).' chars)',
            sprintf('<fg=green>~%d tokens</> (confidence: %s)', $jsonEstimate->inputTokensEstimated, $jsonEstimate->confidence->value),
        );

        // Bangla text
        $banglaText = 'আমি বাংলায় গান গাই। বাংলা আমার মাতৃভাষা।';
        $banglaEstimate = $chain->estimateText($banglaText, $model);
        $this->components->twoColumnDetail(
            'Bangla text ('.mb_strlen($banglaText).' chars)',
            sprintf('<fg=green>~%d tokens</> (confidence: %s)', $banglaEstimate->inputTokensEstimated, $banglaEstimate->confidence->value),
        );

        $this->newLine();

        // 4. Pricing source
        $this->info('─── Pricing Configuration ───');
        $this->newLine();

        $providers = config('llm-tokenkit.pricing.providers', []);
        if (empty($providers)) {
            $this->components->twoColumnDetail('<fg=cyan>Pricing providers</>', '<fg=yellow>None configured (using global default)</>');
        } else {
            foreach ($providers as $providerName => $providerConfig) {
                $modelCount = count($providerConfig['models'] ?? []);
                $hasDefault = isset($providerConfig['default']) ? 'yes' : 'no';
                $this->components->twoColumnDetail(
                    "<fg=cyan>{$providerName}</>",
                    sprintf('%d model(s), provider default: %s', $modelCount, $hasDefault),
                );
            }
        }

        $globalDefault = config('llm-tokenkit.pricing.default');
        $this->components->twoColumnDetail(
            '<fg=cyan>Global default pricing</>',
            $globalDefault
                ? sprintf('$%.2f / $%.2f per 1M tokens (in/out)', $globalDefault['input_per_1m'] ?? 0, $globalDefault['output_per_1m'] ?? 0)
                : '<fg=yellow>Not configured</>',
        );

        $this->newLine();
        $this->info('All checks passed. No network calls were made.');

        return self::SUCCESS;
    }
}
