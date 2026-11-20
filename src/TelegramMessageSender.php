<?php

namespace TelegramKit;

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use TelegramKit\Support\FormattedMessage;

/**
 * Sends a FormattedMessage to a single Telegram chat, chunking the body
 * on safe boundaries (never inside an HTML entity or a Markdown escape
 * sequence) and falling back to a text-file attachment when the body is
 * too large to reasonably send as a run of messages.
 */
class TelegramMessageSender
{
    private const MAX_MESSAGE_LENGTH = 4096;

    private const MAX_BODY_CHUNKS_AS_MESSAGES = 3;

    public function __construct(private Api $api) {}

    public function send(string $chatId, FormattedMessage $message, string $parseMode): void
    {
        if ($message->header === '' && $message->body === '') {
            return;
        }

        if ($message->header === '') {
            foreach ($this->splitBody($message->body, self::MAX_MESSAGE_LENGTH, $parseMode) as $chunk) {
                $this->sendRawText($chatId, $chunk, $parseMode);
            }

            return;
        }

        if ($message->body === '') {
            $this->sendRawText($chatId, $message->header, $parseMode);

            return;
        }

        [$wrapOpen, $wrapClose] = $this->wrapper($parseMode);

        $overhead = mb_strlen($message->header) + 2 + mb_strlen($wrapOpen) + mb_strlen($wrapClose);
        $chunkSize = max(500, self::MAX_MESSAGE_LENGTH - $overhead);

        $chunks = $this->splitBody($message->body, $chunkSize, $parseMode);

        if (count($chunks) > self::MAX_BODY_CHUNKS_AS_MESSAGES) {
            $asMessages = array_slice($chunks, 0, self::MAX_BODY_CHUNKS_AS_MESSAGES - 1);
            $remainder = implode('', array_slice($chunks, self::MAX_BODY_CHUNKS_AS_MESSAGES - 1));

            $this->sendChunks($chatId, $message->header, $asMessages, $wrapOpen, $wrapClose, $parseMode);
            $this->sendDocumentFallback($chatId, $message->header, $remainder);

            return;
        }

        $this->sendChunks($chatId, $message->header, $chunks, $wrapOpen, $wrapClose, $parseMode);
    }

    /**
     * @param  array<int, string>  $chunks
     */
    private function sendChunks(string $chatId, string $header, array $chunks, string $wrapOpen, string $wrapClose, string $parseMode): void
    {
        foreach ($chunks as $i => $chunk) {
            $text = $wrapOpen.$chunk.$wrapClose;
            $text = $i === 0 ? $header."\n\n".$text : $text;

            $this->sendRawText($chatId, $text, $parseMode);
        }
    }

    private function sendRawText(string $chatId, string $text, string $parseMode): void
    {
        $this->api->sendMessage([
            'chat_id' => $chatId,
            'parse_mode' => $parseMode,
            'text' => $text,
        ]);
    }

    private function sendDocumentFallback(string $chatId, string $header, string $remainder): void
    {
        $this->api->sendDocument([
            'chat_id' => $chatId,
            'document' => InputFile::createFromContents($remainder, 'log-details.txt'),
            'caption' => mb_substr(strip_tags($header), 0, 1024),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function wrapper(string $parseMode): array
    {
        return $this->isMarkdown($parseMode)
            ? ["```\n", "\n```"]
            : ['<pre>', '</pre>'];
    }

    private function isMarkdown(string $parseMode): bool
    {
        return strtolower($parseMode) === 'markdownv2';
    }

    /**
     * Split $body into chunks no longer than $chunkSize, preferring to
     * break on a newline so stack frames aren't sliced mid-line, and
     * never breaking inside an HTML entity or Markdown escape sequence.
     *
     * @return array<int, string>
     */
    private function splitBody(string $body, int $chunkSize, string $parseMode): array
    {
        $chunks = [];

        while (mb_strlen($body) > $chunkSize) {
            $breakAt = $this->findBreakPoint($body, $chunkSize, $parseMode);
            $chunks[] = mb_substr($body, 0, $breakAt);
            $body = ltrim(mb_substr($body, $breakAt), "\n");
        }

        $chunks[] = $body;

        return $chunks;
    }

    private function findBreakPoint(string $body, int $chunkSize, string $parseMode): int
    {
        $slice = mb_substr($body, 0, $chunkSize);
        $newline = mb_strrpos($slice, "\n");
        $breakAt = ($newline !== false && $newline >= $chunkSize * 0.5) ? $newline : $chunkSize;

        if ($this->isMarkdown($parseMode)) {
            // Never split between a backslash and the character it escapes.
            if (mb_substr($body, $breakAt - 1, 1) === '\\') {
                $breakAt -= 1;
            }
        } else {
            // Never split inside an HTML entity (&amp; &lt; &gt; &quot; &#039;).
            $tail = mb_substr($body, max(0, $breakAt - 6), 6);
            if (preg_match('/&[a-zA-Z#0-9]*$/', $tail, $m)) {
                $breakAt -= mb_strlen($m[0]);
            }
        }

        return max(1, $breakAt);
    }
}
