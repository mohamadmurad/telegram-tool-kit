# telegram-tool-kit — Package Study & Modernization Plan

Repo: `mohamadmurad/telegram-tool-kit` — a tiny Laravel package that forwards `Log::` calls to a Telegram chat via a bot, plus a Composer post-install "deploy notification" command.

## 1. What the package actually does today

**Files (7 total, ~150 lines of code):**

| File | Role |
|---|---|
| `composer.json` | Package metadata, deps: PHP ≥8.0, monolog `^2.5\|^3.3`, `irazasyed/telegram-bot-sdk ^3.13` |
| `config/config.php` | Two keys: `bot_token`, `chat_id`, from env |
| `src/TelegramKitServiceProvider.php` | Merges config, registers the Artisan command, publishes config |
| `src/TelegramLogger.php` | Monolog "custom" channel factory — `__invoke()` returns a `Logger` with one handler |
| `src/TelegramLoggerHandler.php` | Monolog handler: formats a log record as HTML, sends to Telegram, splits messages >4096 chars |
| `src/Console/sendTelegramAutoLoadNotification.php` | Artisan command fired from `composer.json`'s `post-autoload-dump`, sends a "deploy" message with git commit info |

**How it's wired in a consuming app:** register a `logging.php` channel with `driver: custom`, `via: TelegramLogger::class` — standard Laravel custom Monolog channel pattern. That part is idiomatic and still works today.

## 2. Problems found (bugs, not just staleness)

1. **Config namespace mismatch.** `mergeConfigFrom` merges into `TelegramKit`, and the publish target is `config_path('TelegramKit.php')` — but every `config('TelegramKit.bot_token')` call assumes the *published* file is loaded as `TelegramKit`. If the user never publishes (common — many just set env vars), `config('TelegramKit.*')` is **null**, silently no-oping the bot token. There's no fallback and no validation.
2. **Swallowed failures.** `TelegramLoggerHandler::write()` catches *all* exceptions and logs them to the `single` channel — but that means a bad bot token, network failure, or rate limit is invisible unless someone is tailing `storage/logs/laravel.log`. No retry, no circuit breaker, no way to know Telegram delivery is broken.
3. **No rate-limiting / flood protection.** Telegram allows ~30 msgs/sec to different chats but ~1/sec to the *same* chat, with 429 backoff required. A burst of exceptions (e.g. a queue worker crash-looping) will get the bot throttled or banned from the chat, and every throttled message is just silently dropped by the catch-all.
4. **`str_split` on formatted HTML breaks tags.** Splitting the final HTML string into 4096-byte chunks can slice a message mid-tag (e.g. `<b>Error L` / `og</b>`), which Telegram will reject as invalid HTML with `parse_mode: html`, so the whole chunk fails — not just gets sent unstyled.
5. **No context/extra data.** Monolog `$record['context']` and `$record['extra']` are dropped entirely — stack traces, request IDs, user IDs, exception class are never included. This is the single biggest feature gap for an error-reporting tool.
6. **Hardcoded `chat_id` / `bot_token`.** No support for routing different log levels or environments to different chats (e.g. `critical` → on-call chat, `debug` → dev chat), which is the #1 feature people want from Telegram log integrations.
7. **`sendTelegramAutoLoadNotification` shells out to `git`** via `exec()` on every `composer install`/`update` in *every* environment, including production deploys where `.git` may not exist (deploy artifacts often exclude it) — `exec()` returns empty/false silently, producing garbage output, and executing shell commands from a Composer hook is also a footgun on shared hosting where `exec` is disabled.
8. **Command is hardcoded to `master` branch** (`$branch = 'master'` parameter that's never actually used in the git commands — dead parameter, and the log command itself always reads `HEAD` regardless of branch name, so the parameter is misleading and unused).
9. **No tests.** `require-dev` lists `orchestra/testbench` but there isn't a single test in the repo.
10. **Class naming convention.** `sendTelegramAutoLoadNotification` (lowercase-leading class name) violates PSR-1/PSR-12 and Laravel conventions.
11. **No queueing.** Every log call blocks the request on a synchronous HTTP call to Telegram's API. On a slow network this adds real latency to user-facing requests, especially bad since `debug`/`info` level messages could be routed through this channel.
12. **`irazasyed/telegram-bot-sdk` requirement is stale.** `^3.13` in composer.json is fine loosely, but README/repo hasn't been touched to reflect Laravel 10/11/12/13 compatibility testing, and `illuminate/support` support windows should be explicit.

## 3. Current vs. target compatibility

| Dependency | In repo now | Current (Aug 2026) | Action |
|---|---|---|---|
| PHP | `>=8.0` | 8.3 required by Laravel 13, 8.4/8.5 also current | Bump floor to `^8.2\|^8.3`, test against 8.3/8.4 |
| Laravel (`illuminate/support`) | untested / implicit | Laravel 13.x is current; 12.x still in support until Feb 2027 | Explicitly require `^10.0\|^11.0\|^12.0\|^13.0` |
| `monolog/monolog` | `^2.5\|^3.3` | 3.x current | Drop Monolog 2 support (Laravel 10+ ships Monolog 3 only), simplifies code |
| `irazasyed/telegram-bot-sdk` | `^3.13` | **v3.16.0** current; a **v4** is in active development at `telegram-bot-sdk/telegram-bot-sdk` | Bump to `^3.16`, track v4 for a future major |
| `orchestra/testbench` | `^8.0` (dev) | testbench versions track Laravel majors | Bump to match whichever Laravel majors you support (e.g. `^8.0\|^9.0\|^10.0`) |
| Telegram Bot API | not version-pinned anywhere | Bot API is continuously updated (topics, boosts, business connections, etc. added through 2025–2026) | No SDK-breaking action needed — `sendMessage` is stable — but worth adding support for newer Bot API features (see §5) |

## 4. Recommended phased plan

### Phase 0 — Housekeeping (low risk, do first)
- Add a CI workflow (GitHub Actions) running PHPUnit/Pest against a matrix of PHP 8.2/8.3/8.4 × Laravel 11/12/13.
- Add `phpstan`/`larastan` and `laravel/pint` for static analysis + style, run in CI.
- Fix `composer.json` version constraints per the table above.
- Rename `sendTelegramAutoLoadNotification` → `SendTelegramAutoLoadNotificationCommand` (PSR-12), keep old class as a deprecated alias for one major version.
- Write the first real test suite using `orchestra/testbench` (service provider boots, config merges, handler formats a record correctly) — this unblocks safely refactoring everything else.

### Phase 1 — Fix correctness bugs
- Fix the config-namespace mismatch: either auto-load `config/config.php` under a consistent key regardless of publish state, or add a boot-time check that warns (via `Log::warning` once) if `bot_token`/`chat_id` are unset.
- Fix chunking: split on Telegram's **entity-safe** boundaries (never mid-tag) — either use `parse_mode: 'MarkdownV2'`-safe splitting helpers, or switch to sending plain text with a separate "details" mechanism (see Phase 2), or wrap each chunk in balanced tags.
- Add explicit handling for Telegram API errors (`TelegramSDKException`, HTTP 429 `retry_after`) instead of swallowing everything the same way — surface delivery failures distinctly from formatting failures.
- Remove the unused `$branch` parameter or actually use it; fix `exec()` calls to check `shell_exec`/`proc_open` availability and fail gracefully (return early with an info message) when disabled or `.git` is absent.

### Phase 2 — Feature additions (this is the "add features" part)
1. **Include exception & context data.** When `$record['context']['exception']` is present, include exception class, message, file:line, and a trimmed stack trace (respecting Telegram's 4096-char limit per message — send the trace as a follow-up message or a `.txt` document via `sendDocument` if long).
2. **Per-level chat routing.** Support `chat_id` as either a string or an array keyed by level (`['critical' => '-100...', 'default' => '-100...']`), so `emergency`/`critical` can page a different chat than `debug`.
3. **Queueable delivery.** Add a config flag `queue => true` that dispatches a `SendTelegramLogMessage` job instead of calling the Telegram API inline, so logging never blocks the request/response cycle. Support configuring the queue connection/name.
4. **Rate limiting / batching.** Debounce bursts — e.g. collapse N identical exceptions within a rolling window into one "this error occurred 47 times in the last minute" message, using cache-based throttling (`Cache::has`/`put` with a TTL). This is the most-requested feature for any chat-based log integration and prevents bot bans.
5. **Retry with backoff on 429.** Respect Telegram's `retry_after` header/response field; requeue rather than drop.
6. **Message formatting options.** Let users supply a custom formatter (interface + config binding) instead of the hardcoded `messageFormatter()`, and support `MarkdownV2` as an alternative to HTML.
7. **Multiple bots/channels.** Allow more than one Telegram destination per app (e.g. errors to team chat, audit events to a channel) by supporting multiple named log channels off the same package, not just one global bot/chat pair.
8. **Environment/app-context enrichment.** Add current environment (`app.env`), server hostname, and optionally the authenticated user id/email (if available and enabled via config, off by default for privacy) to the message.
9. **Structured "deploy notification" upgrade.** Fix the Composer-hook command to: detect `.git` presence before shelling out, support any branch (use `git rev-parse --abbrev-ref HEAD` instead of the unused parameter), and make it opt-in via config rather than assumed wired into every consumer's `composer.json`.
10. **Testing/mock mode.** A config flag to no-op sends (log locally instead) for local/testing environments, so devs don't accidentally spam the team chat.
11. **Newer Bot API surface (optional, stretch).** Expose an optional "interactive" mode using Telegram inline keyboards on alert messages (e.g. "Acknowledge" / "Mute for 1h" buttons) — this requires a small webhook endpoint to receive callback_query updates, which is a bigger addition (Phase 3 candidate) but is the kind of feature that differentiates this from a bare webhook.

### Phase 3 — Bigger/optional additions
- Webhook endpoint + route for handling Telegram callback buttons (ack/mute), persisting state (e.g. mute windows) via cache or a small DB table.
- Optional Slack-style "digest" mode: batch non-critical logs and send a periodic summary via a scheduled command instead of real-time per-message.
- First-class support for `irazasyed/telegram-bot-sdk` v4 once it's stable, behind a version constraint that allows either v3 or v4.

## 5. Suggested new `composer.json` shape

```json
{
  "require": {
    "php": "^8.2|^8.3",
    "illuminate/support": "^10.0|^11.0|^12.0|^13.0",
    "monolog/monolog": "^3.3",
    "irazasyed/telegram-bot-sdk": "^3.16"
  },
  "require-dev": {
    "orchestra/testbench": "^8.0|^9.0|^10.0",
    "pestphp/pest": "^2.0|^3.0",
    "phpstan/phpstan": "^1.10",
    "laravel/pint": "^1.13"
  }
}
```

## 6. Suggested execution order (practical, PR-sized chunks)

1. PR 1: CI + Pint + PHPStan scaffolding, no behavior change.
2. PR 2: Test suite covering current behavior (locks in a baseline before refactoring).
3. PR 3: Fix config namespace bug + chunking bug (Phase 1 correctness fixes).
4. PR 4: Bump composer constraints (Phase 0 table), drop Monolog 2, confirm Laravel 11/12/13 matrix green.
5. PR 5: Exception/context enrichment + custom formatter interface (biggest single value-add).
6. PR 6: Queueable delivery + rate-limit/debounce.
7. PR 7: Per-level chat routing + multi-channel support.
8. PR 8: Composer-hook command hardening (git detection, branch fix).
9. PR 9+: Phase 3 stretch items as separate minor/major releases.

---

Want me to start implementing any of this — e.g. write the updated `composer.json` + fixed `TelegramLoggerHandler` with exception context and safe chunking as a first working PR?
