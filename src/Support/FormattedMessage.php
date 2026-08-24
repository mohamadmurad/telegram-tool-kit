<?php

namespace TelegramKit\Support;

/**
 * A formatted log message split into a short header (always sent whole,
 * never chunked) and a body (arbitrary length — the raw, un-tagged log
 * message/stack trace/context — safe to split at any point).
 */
final class FormattedMessage
{
    public function __construct(
        public readonly string $header,
        public readonly string $body = '',
    ) {}
}
