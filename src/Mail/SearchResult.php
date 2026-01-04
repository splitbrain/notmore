<?php

namespace splitbrain\notmore\Mail;

/**
 * Thread metadata as returned by notmuch search results.
 */
class SearchResult
{
    public readonly string $id;
    public readonly ?string $subject;
    public readonly ?string $authors;
    public readonly array $tags;
    public readonly int|string|null $date_relative;
    public readonly int|string|null $date;

    /**
     * Construct an immutable search result representing a notmuch thread summary.
     *
     * @param string $id notmuch thread id
     * @param string|null $subject Thread subject (if present in the search row)
     * @param string|null $authors Authors string provided by notmuch
     * @param array $tags Tag list for the thread
     * @param int|string|null $dateRelative Human readable relative date (if available)
     * @param int|string|null $date Absolute date (if available)
     */
    private function __construct(
        string $id,
        ?string $subject,
        ?string $authors,
        array $tags,
        int|string|null $dateRelative,
        int|string|null $date
    )
    {
        $this->id = $id;
        $this->subject = $subject;
        $this->authors = $authors;
        $this->tags = $tags;
        $this->date_relative = $dateRelative;
        $this->date = $date;
    }

    /**
     * Build a SearchResult from a notmuch search row.
     *
     * @param array $payload Single thread entry from notmuch search (no messages included).
     * @return SearchResult Parsed search row containing thread metadata.
     */
    public static function fromNotmuch(array $payload): self
    {
        $subject = isset($payload['subject']) && is_string($payload['subject'])
            ? $payload['subject']
            : null;

        $id = isset($payload['thread']) ? (string)$payload['thread'] : '';

        $authors = null;
        if (isset($payload['authors'])) {
            $authors = (string)$payload['authors'];
        }

        $tags = [];
        if (isset($payload['tags']) && is_array($payload['tags'])) {
            $tags = $payload['tags'];
        }

        $dateRelative = $payload['date_relative'] ?? null;
        $date = $payload['date'] ?? null;

        return new self($id, $subject, $authors, $tags, $dateRelative, $date);
    }
}
