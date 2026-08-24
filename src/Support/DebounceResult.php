<?php

namespace TelegramKit\Support;

final class DebounceResult
{
    public function __construct(
        public readonly bool $shouldSend,
        public readonly int $suppressedInPriorWindow = 0,
    ) {}
}
