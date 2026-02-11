<?php

declare(strict_types=1);

use Frolax\LlmTokenKit\Context\ContextBuilder;
use Frolax\LlmTokenKit\Data\ContextBuildRequest;
use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Enums\ContextStrategy;
use Frolax\LlmTokenKit\Enums\ToolMessagePolicy;
use Frolax\LlmTokenKit\Estimators\EstimatorChain;
use Frolax\LlmTokenKit\Estimators\HeuristicEstimator;

beforeEach(function () {
    $this->chain = new EstimatorChain(new HeuristicEstimator);
    $this->builder = new ContextBuilder($this->chain);
    $this->model = new ModelRef(provider: 'openai', model: 'gpt-4', contextLimit: 128_000);
});

describe('no duplicate new user message', function () {
    it('does not duplicate the new user message in output', function () {
        $request = new ContextBuildRequest(
            system: 'You are a helpful assistant.',
            historyMessages: [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi! How can I help?'],
            ],
            newUserMessage: 'What is PHP?',
            modelRef: $this->model,
        );

        $result = $this->builder->build($request);

        // Count occurrences of the new user message
        $newMsgCount = 0;
        foreach ($result->messages as $msg) {
            if ($msg['role'] === 'user' && $msg['content'] === 'What is PHP?') {
                $newMsgCount++;
            }
        }

        expect($newMsgCount)->toBe(1);
    });

    it('places new user message at the end', function () {
        $request = new ContextBuildRequest(
            historyMessages: [
                ['role' => 'user', 'content' => 'Previous message'],
            ],
            newUserMessage: 'Latest message',
            modelRef: $this->model,
        );

        $result = $this->builder->build($request);
        $messages = $result->messages;
        $lastMessage = end($messages);

        expect($lastMessage['role'])->toBe('user');
        expect($lastMessage['content'])->toBe('Latest message');
    });
});

describe('system and memory messages', function () {
    it('keeps system message at the top', function () {
        $request = new ContextBuildRequest(
            system: 'You are a coding assistant.',
            historyMessages: [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            newUserMessage: 'Help me',
            modelRef: $this->model,
        );

        $result = $this->builder->build($request);

        expect($result->messages[0]['role'])->toBe('system');
        expect($result->messages[0]['content'])->toBe('You are a coding assistant.');
    });

    it('keeps memory summary after system', function () {
        $request = new ContextBuildRequest(
            system: 'System prompt',
            memorySummary: 'User prefers dark mode',
            historyMessages: [],
            newUserMessage: 'Hello',
            modelRef: $this->model,
        );

        $result = $this->builder->build($request);

        expect($result->messages[0]['role'])->toBe('system');
        expect($result->messages[0]['content'])->toBe('System prompt');
        expect($result->messages[1]['role'])->toBe('system');
        expect($result->messages[1]['content'])->toContain('User prefers dark mode');
    });
});

describe('truncate_by_tokens strategy', function () {
    it('reduces messages until under budget', function () {
        // Create many messages to exceed a small budget
        $history = [];
        for ($i = 0; $i < 20; $i++) {
            $history[] = ['role' => 'user', 'content' => "Message number {$i} with some additional text to increase the token count"];
            $history[] = ['role' => 'assistant', 'content' => "Response number {$i} with matching content to increase total"];
        }

        $request = new ContextBuildRequest(
            system: 'System',
            historyMessages: $history,
            newUserMessage: 'Final question',
            modelRef: $this->model,
            tokenBudget: 100,
            strategy: ContextStrategy::TruncateByTokens,
        );

        $result = $this->builder->build($request);

        expect($result->wasTruncated)->toBeTrue();
        expect($result->droppedMessageCount)->toBeGreaterThan(0);
        expect($result->tokenEstimate->inputTokensEstimated)->toBeLessThanOrEqual(100);
        // Should still have the new user message
        $messages = $result->messages;
        $lastMsg = end($messages);
        expect($lastMsg['content'])->toBe('Final question');
    });
});

describe('rolling window strategy', function () {
    it('keeps only the last N messages', function () {
        $history = [];
        for ($i = 0; $i < 10; $i++) {
            $history[] = ['role' => 'user', 'content' => "Message {$i}"];
        }

        $request = new ContextBuildRequest(
            historyMessages: $history,
            newUserMessage: 'Latest',
            modelRef: $this->model,
            windowSize: 3,
            strategy: ContextStrategy::RollingWindow,
        );

        $result = $this->builder->build($request);

        expect($result->droppedMessageCount)->toBe(7); // 10 - 3 = 7
        expect($result->wasTruncated)->toBeTrue();
        // 3 history + 1 new user message = 4
        expect(count($result->messages))->toBe(4);
    });
});

describe('tool message policies', function () {
    it('excludes tool messages when includeToolMessages is false', function () {
        $request = new ContextBuildRequest(
            historyMessages: [
                ['role' => 'user', 'content' => 'Search for data'],
                ['role' => 'tool', 'content' => '{"result": "found data"}'],
                ['role' => 'assistant', 'content' => 'Here is the data'],
            ],
            newUserMessage: 'Thanks',
            modelRef: $this->model,
            includeToolMessages: false,
        );

        $result = $this->builder->build($request);

        $toolMessages = array_filter($result->messages, fn ($m) => $m['role'] === 'tool');
        expect($toolMessages)->toBeEmpty();
    });

    it('summarizes tool messages with summarize_only policy', function () {
        $request = new ContextBuildRequest(
            historyMessages: [
                ['role' => 'user', 'content' => 'Search for data'],
                ['role' => 'tool', 'content' => '{"result": "very long tool result with lots of data"}'],
                ['role' => 'assistant', 'content' => 'Here is the data'],
            ],
            newUserMessage: 'Thanks',
            modelRef: $this->model,
            includeToolMessages: true,
            toolMessagePolicy: ToolMessagePolicy::SummarizeOnly,
        );

        $result = $this->builder->build($request);

        $toolMessages = array_filter($result->messages, fn ($m) => $m['role'] === 'tool');
        $toolMessage = array_values($toolMessages)[0] ?? null;

        expect($toolMessage)->not->toBeNull();
        expect($toolMessage['content'])->toBe('Tool result omitted; available in app.');
    });
});

describe('fromArray support', function () {
    it('builds context from an array', function () {
        $result = $this->builder->build(ContextBuildRequest::fromArray([
            'system' => 'Be helpful',
            'history_messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'new_user_message' => 'World',
            'model_ref' => ['provider' => 'openai', 'model' => 'gpt-4'],
            'strategy' => 'rolling_window',
        ]));

        expect($result->messages)->not->toBeEmpty();
        $msgs = $result->messages;
        expect(end($msgs)['content'])->toBe('World');
    });
});
