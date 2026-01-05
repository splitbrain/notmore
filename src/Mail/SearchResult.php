<?php

namespace splitbrain\notmore\Mail;

/**
 * Thread metadata as returned by notmuch search results.
 */
readonly class SearchResult
{
    public string $id;
    public ?string $subject;
    public ?string $authors;
    public array $tags;
    public int $timestamp;
    public string $date_relative;
    public int $total;
    public int $matched;

    /**
     * Construct an immutable search result representing a notmuch thread summary.
     *
     * @param string $id notmuch thread id
     * @param string|null $subject Thread subject (if present in the search row)
     * @param string|null $authors Authors string provided by notmuch
     * @param array $tags Tag list for the thread
     * @param int $timestamp Unix timestamp (seconds)
     * @param string $dateRelative Human readable relative date
     * @param int $total Total number of messages in the thread
     * @param int $matched Number of matched messages in the thread
     */
    private function __construct(
        string $id,
        ?string $subject,
        ?string $authors,
        array $tags,
        int $timestamp,
        string $dateRelative,
        int $total,
        int $matched
    )
    {
        $this->id = $id;
        $this->subject = $subject;
        $this->authors = $authors;
        $this->tags = $tags;
        $this->timestamp = $timestamp;
        $this->date_relative = $dateRelative;
        $this->total = $total;
        $this->matched = $matched;
    }

    /**
     * Build a SearchResult from a notmuch search row.
     *
     * @param array $payload Single thread entry from notmuch search (no messages included).
     * @return SearchResult Parsed search row containing thread metadata.
     */
    public static function fromNotmuch(array $payload): self
    {
        $subject = $payload['subject'] ?? null;
        $id = (string)($payload['thread'] ?? '');
        $authors = (string)($payload['authors'] ?? null);
        $tags = $payload['tags'] ?? [];
        $timestamp = (int)($payload['timestamp'] ?? 0);
        $dateRelative = (string)($payload['date_relative'] ?? '');
        $total = (int)($payload['total'] ?? 0);
        $matched = (int)($payload['matched'] ?? 0);

        return new self($id, $subject, $authors, $tags, $timestamp, $dateRelative, $total, $matched);
    }
}
