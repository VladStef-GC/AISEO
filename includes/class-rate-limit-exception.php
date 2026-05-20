<?php

namespace AI_SEO_Captain;

/**
 * Thrown when the AI provider returns HTTP 429 (rate limit exceeded).
 */
class RateLimitException extends \RuntimeException
{
    /** @var int Seconds the caller should wait before retrying. */
    private $retry_after;

    public function __construct(string $message, int $retry_after = 5, ?\Throwable $previous = null)
    {
        $this->retry_after = max(1, $retry_after);
        parent::__construct($message, 429, $previous);
    }

    public function get_retry_after(): int
    {
        return $this->retry_after;
    }
}
