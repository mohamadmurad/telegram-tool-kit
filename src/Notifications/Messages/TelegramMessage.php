<?php

namespace TelegramKit\Notifications\Messages;

use Stringable;

final class TelegramMessage implements Stringable
{
    public function __construct(
        public string $content = '',
        public ?string $parseMode = null,
    ) {}

    public static function make(string $content = '', ?string $parseMode = null): self
    {
        return new self($content, $parseMode);
    }

    public function line(string $line): static
    {
        $this->content .= ($this->content === '' ? '' : "\n").$line;

        return $this;
    }

    public function parseMode(string $parseMode): static
    {
        $this->parseMode = $parseMode;

        return $this;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
