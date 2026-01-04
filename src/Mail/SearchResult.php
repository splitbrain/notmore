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

    /**
     * Construct an immutable search result representing a notmuch thread summary.
     *
     * @param string $id notmuch thread id
     * @param string|null $subject Thread subject (if present in the search row)
     * @param string|null $authors Authors string provided by notmuch
     * @param array $tags Tag list for the thread
     * @param int $timestamp Unix timestamp (seconds)
     * @param string $dateRelative Human readable relative date
     */
    private function __construct(
        string $id,
        ?string $subject,
        ?string $authors,
        array $tags,
        int $timestamp,
        string $dateRelative
    )
    {
        $this->id = $id;
        $this->subject = $subject;
        $this->authors = $authors;
        $this->tags = $tags;
        $this->timestamp = $timestamp;
        $this->date_relative = $dateRelative;
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

        return new self($id, $subject, $authors, $tags, $timestamp, $dateRelative);
    }
}
