<?php

namespace splitbrain\notmore\Mail;

/**
 * Normalized message representation built from notmuch show output.
 */
readonly class Message
{
    public string $id;
    public array $headers;
    public array $tags;
    public int $timestamp;
    public string $date_relative;
    /** @var string|null Purified HTML body */
    public ?string $body;
    /** @var Attachment[] */
    public array $attachments;
    /** @var Message[] */
    public array $children;

    /**
     * Build a Message tree from the nested notmuch entry.
     *
     * @param array $entry Typically [messageData, childrenArray]
     * @return Message|null A normalized message node, or null when data is invalid
     */
    public static function fromNotmuchEntry(array $entry): ?self
    {
        if (!isset($entry[0]) || !is_array($entry[0])) {
            return null;
        }

        $message = $entry[0];
        $childrenEntries = $entry[1] ?? [];

        return new self($message, $childrenEntries);
    }

    /**
     * Build a list of Message nodes from a thread entry.
     *
     * @param mixed $entries notmuch thread entries (array of [message, children] tuples)
     * @return Message[] Flattened list of normalized message roots
     */
    public static function listFromNotmuchThread(array $entries): array
    {
        $messages = [];
        foreach ($entries as $entry) {
            $message = self::fromNotmuchEntry($entry);
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Construct an immutable message node with attachments and children.
     *
     * @param array $message Raw notmuch message payload
     * @param array $childrenEntries Nested children entries
     */
    private function __construct(array $message, array $childrenEntries)
    {
        $this->id = (string)($message['id'] ?? '');
        $this->headers = (array)($message['headers'] ?? []);
        $this->tags = (array)($message['tags'] ?? []);
        $this->timestamp = (int)($message['timestamp'] ?? 0);
        $this->date_relative = (string)($message['date_relative'] ?? '');

        $parts = (array)($message['body'] ?? []);
        $this->attachments = $this->collectAttachments($parts);
        $this->body = $this->normalizeBody($parts);

        $children = [];
        foreach ($childrenEntries as $childEntry) {
            $child = self::fromNotmuchEntry($childEntry);
            if ($child !== null) {
                $children[] = $child;
            }
        }
        $this->children = $children;
    }

    /**
     * Pick and normalize the preferred body using provided helpers.
     *
     * @param array[] $parts Body parts array from notmuch
     * @return string|null Purified HTML body or null when missing
     */
    private function normalizeBody(array $parts): ?string
    {
        $selection = $this->selectPreferredBody($parts);
        if ($selection === null) {
            return null;
        }

        $converter = new BodyConverter();
        if ($selection['type'] === 'html') {
            return $converter->convertHtml((string)$selection['content'], $this->id, $this->attachments);
        }

        return $converter->convertText((string)$selection['content']);
    }

    /**
     * Choose the preferred body content from notmuch parts (HTML first, then plain text).
     *
     * @param array[] $parts Body parts array from notmuch
     * @return array{type:string,content:string}|null Selected body type and content
     */
    private function selectPreferredBody(array $parts): ?array
    {
        $html = $this->findBodyByMime($parts, 'text/html');
        if ($html !== null) {
            return ['type' => 'html', 'content' => $html];
        }

        $plain = $this->findBodyByMime($parts, 'text/plain');
        if ($plain !== null) {
            return ['type' => 'text', 'content' => $plain];
        }

        return null;
    }

    /**
     * Find the first body part matching the given MIME type.
     *
     * @param array[] $parts Body parts array from notmuch
     * @param string $mime MIME type to search for
     * @return string|null
     */
    private function findBodyByMime(array $parts, string $mime): ?string
    {
        foreach ($parts as $part) {
            $contentType = strtolower((string)($part['content-type'] ?? ''));
            if ($contentType !== '' && str_starts_with($contentType, $mime) && isset($part['content'])) {
                return (string)$part['content'];
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $found = $this->findBodyByMime($part['content'], $mime);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Collect attachment metadata from body parts (any part with a filename or content-id).
     *
     * @param array[] $parts Body parts array from notmuch
     * @return Attachment[]
     */
    private function collectAttachments(array $parts): array
    {
        $attachments = [];

        foreach ($parts as $part) {
            if (($part['filename'] ?? '') !== '' || array_key_exists('content-id', $part)) {
                $attachments[] = Attachment::fromNotmuchPart($part);
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $attachments = array_merge($attachments, $this->collectAttachments($part['content']));
            }
        }

        return $attachments;
    }
}
