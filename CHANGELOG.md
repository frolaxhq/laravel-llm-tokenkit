# Changelog

All notable changes to `laravel-llm-tokenkit` will be documented in this file.

## v1.0.0 - 2026-02-11

### 🎉 Initial Release

A stateless Laravel package for token estimation, cost calculation, and context-window building for LLM-powered apps.

#### Features

- 📊 **Token Estimation** — Heuristic estimator (~chars/4) with code/JSON penalties and non-Latin script multipliers (Bangla, Hindi, Arabic, CJK)
- 💰 **Cost Calculation** — Per-provider pricing with 4-level precedence (exact → wildcard → provider default → global)
- 📐 **Context Builder** — Rolling window & token-budget truncation strategies with system/memory pinning and tool message policies
- 🔌 **Pluggable Architecture** — Bring your own tokenizer via `TokenEstimatorInterface`
- 🛠️ **Artisan Command** — `tokenkit:check` for diagnostics (no network calls)
- ⚙️ **Fully Configurable** — Estimation penalties, pricing, context strategies all via config

#### Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

#### Installation

```bash
composer require frolax/laravel-llm-tokenkit

```
#### Links

- [Documentation](https://github.com/frolaxhq/laravel-llm-tokenkit#readme)
- [Configuration Guide](https://github.com/frolaxhq/laravel-llm-tokenkit#configuration)
