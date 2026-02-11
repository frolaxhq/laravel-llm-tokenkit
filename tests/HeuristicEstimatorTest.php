<?php

declare(strict_types=1);

use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Enums\Confidence;
use Frolax\LlmTokenKit\Enums\EstimationMethod;
use Frolax\LlmTokenKit\Estimators\HeuristicEstimator;

beforeEach(function () {
    $this->estimator = new HeuristicEstimator();
    $this->model = new ModelRef(provider: 'openai', model: 'gpt-4');
});

describe('English text estimation', function () {
    it('estimates English text at approximately chars/4', function () {
        $text = 'The quick brown fox jumps over the lazy dog';
        $result = $this->estimator->estimateText($text, $this->model);

        $charCount = mb_strlen($text);
        $expectedApprox = (int) round($charCount / 4);

        expect($result->inputTokensEstimated)->toBeGreaterThan(0);
        expect($result->inputTokensEstimated)->toBeBetween(
            (int) ($expectedApprox * 0.7),
            (int) ($expectedApprox * 1.5),
        );
        expect($result->method)->toBe(EstimationMethod::Heuristic);
    });

    it('returns zero for empty text', function () {
        $result = $this->estimator->estimateText('', $this->model);

        expect($result->inputTokensEstimated)->toBe(0);
        expect($result->confidence)->toBe(Confidence::High);
    });

    it('estimates messages with per-message overhead', function () {
        $messages = [
            ['role' => 'user', 'content' => 'Hello world'],
            ['role' => 'assistant', 'content' => 'Hi there! How can I help?'],
        ];

        $result = $this->estimator->estimateMessages($messages, $this->model);

        // Should be more than just the text content due to overhead
        $textOnly = 'Hello worldHi there! How can I help?';
        $bareEstimate = (int) round(mb_strlen($textOnly) / 4);

        expect($result->inputTokensEstimated)->toBeGreaterThan($bareEstimate);
        expect($result->method)->toBe(EstimationMethod::Heuristic);
    });
});

describe('Bangla text estimation', function () {
    it('applies higher multiplier for Bangla script', function () {
        $banglaText = 'আমি বাংলায় গান গাই। বাংলা আমার মাতৃভাষা।';
        $englishText = 'I sing in Bangla. Bangla is my mother tongue.';

        $banglaResult = $this->estimator->estimateText($banglaText, $this->model);
        $englishResult = $this->estimator->estimateText($englishText, $this->model);

        // Bangla should produce higher token estimates due to multiplier
        // The ratio should reflect the configured multiplier (~2.5x)
        $banglaChars = mb_strlen($banglaText);
        $baseEstimate = $banglaChars / 4;

        expect($banglaResult->inputTokensEstimated)->toBeGreaterThan((int) $baseEstimate);
        expect($banglaResult->confidence)->toBe(Confidence::Low);
    });

    it('applies multiplier when language hint is provided', function () {
        $text = 'This is a mixed text with some content';
        $resultWithHint = $this->estimator->estimateText($text, $this->model, ['language_hint' => 'bn']);
        $resultWithoutHint = $this->estimator->estimateText($text, $this->model);

        // With Bangla hint, estimate should be higher
        expect($resultWithHint->inputTokensEstimated)->toBeGreaterThan($resultWithoutHint->inputTokensEstimated);
    });
});

describe('JSON and code estimation', function () {
    it('applies penalty for JSON content', function () {
        $jsonText = '{"users": [{"name": "John", "age": 30}, {"name": "Jane", "age": 25}]}';
        $plainText = 'users name John age 30 name Jane age 25';

        $jsonResult = $this->estimator->estimateText($jsonText, $this->model);
        $plainResult = $this->estimator->estimateText($plainText, $this->model);

        // JSON should produce higher estimates due to code penalty
        // Normalize by character count for fair comparison
        $jsonPerChar = $jsonResult->inputTokensEstimated / mb_strlen($jsonText);
        $plainPerChar = $plainResult->inputTokensEstimated / mb_strlen($plainText);

        expect($jsonPerChar)->toBeGreaterThan($plainPerChar);
        expect($jsonResult->confidence)->toBe(Confidence::Medium);
    });

    it('applies penalty for code content', function () {
        $codeText = <<<'CODE'
        function calculateTotal($items) {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['price'] * $item['quantity'];
            }
            return $total;
        }
        CODE;

        $result = $this->estimator->estimateText($codeText, $this->model);

        expect($result->inputTokensEstimated)->toBeGreaterThan(0);

        // Check notes mention code detection
        $codeNotes = array_filter($result->notes, fn ($n) => str_contains($n, 'Code/JSON'));
        expect($codeNotes)->not->toBeEmpty();
    });
});

describe('estimator supports all models', function () {
    it('supports any model', function () {
        $models = [
            new ModelRef(provider: 'openai', model: 'gpt-4'),
            new ModelRef(provider: 'anthropic', model: 'claude-3-opus'),
            new ModelRef(provider: 'unknown', model: 'some-model'),
        ];

        foreach ($models as $model) {
            expect($this->estimator->supports($model))->toBeTrue();
        }
    });
});
