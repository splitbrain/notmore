<?php

namespace splitbrain\notmore\Mail;

/**
 * Thread metadata as returned by notmuch search results.
 */
readonly class SearchResult
{
    public string $id;
    public string $subject;
    public ?string $authors;
    public array $tags;
    public int $timestamp;
    public string $date_relative;
    public int $total;
    public int $matched;
    public array $matches;

    /**
     * Construct an immutable search result representing a notmuch thread summary.
     *
     * @param array $payload Raw notmuch search row (single thread entry)
     */
    private function __construct(array $payload)
    {
        $this->id = (string)($payload['thread'] ?? '');

        $subject = $payload['subject'] ?? '[No Subject]';
        $this->subject = $subject !== null ? (string)$subject : null;

        $authors = $payload['authors'] ?? null;
        $this->authors = $authors !== null ? (string)$authors : null;

        $this->tags = (array)($payload['tags'] ?? []);
        $this->timestamp = (int)($payload['timestamp'] ?? 0);
        $this->date_relative = (string)($payload['date_relative'] ?? '');
        $this->total = (int)($payload['total'] ?? 0);
        $this->matched = (int)($payload['matched'] ?? 0);

        $matches = explode(' ', (string)($payload['query'][0] ?? ''));
        $this->matches = array_map(
            fn ($match): string => str_starts_with($match, 'id:') ? substr($match, 3) : $match,
            $matches
        );
    }

    /**
     * Build a SearchResult from a notmuch search row.
     *
     * @param array $payload Single thread entry from notmuch search (no messages included).
     * @return SearchResult Parsed search row containing thread metadata.
     */
    public static function fromNotmuch(array $payload): self
    {
        return new self($payload);
    }
}
