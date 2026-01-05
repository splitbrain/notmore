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
     * Construct an immutable message node with attachments and children.
     *
     * @param string $id Message id
     * @param array $headers Raw headers array from notmuch
     * @param array $tags Tag list for the message
     * @param int $timestamp Unix timestamp (seconds)
     * @param string $dateRelative Human readable relative date
     * @param string|null $body Purified HTML body content
     * @param Attachment[] $attachments Collected attachments for this message
     * @param Message[] $children Child message nodes
     */
    private function __construct(
        string $id,
        array $headers,
        array $tags,
        int $timestamp,
        string $dateRelative,
        ?string $body,
        array $attachments,
        array $children
    ) {
        $this->id = $id;
        $this->headers = $headers;
        $this->tags = $tags;
        $this->timestamp = $timestamp;
        $this->date_relative = $dateRelative;
        $this->body = $body;
        $this->attachments = $attachments;
        $this->children = $children;
    }

    /**
     * Build a Message tree from the nested notmuch entry.
     *
     * @param array $entry Typically [messageData, childrenArray]
     * @return Message|null A normalized message node, or null when data is invalid
     */
    public static function fromNotmuchEntry(array $entry): ?self
    {
        $converter = new BodyConverter();

        return self::fromNotmuchEntryWithConverter($entry, $converter);
    }

    /**
     * Build a list of Message nodes from a thread entry.
     *
     * @param mixed $entries notmuch thread entries (array of [message, children] tuples)
     * @return Message[] Flattened list of normalized message roots
     */
    public static function listFromNotmuchThread(array $entries): array
    {
        $converter = new BodyConverter();
        $messages = [];
        foreach ($entries as $entry) {
            $message = self::fromNotmuchEntryWithConverter($entry, $converter);
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Collect attachment metadata from body parts (any part with a filename or content-id).
     *
     * @param array[] $parts Body parts array from notmuch
     * @return Attachment[]
     */
    private static function collectAttachments(array $parts): array
    {
        $attachments = [];

        foreach ($parts as $part) {
            if (($part['filename'] ?? '') !== '' || array_key_exists('content-id', $part)) {
                $attachments[] = new Attachment(
                    (string)($part['filename'] ?? ''),
                    (string)($part['content-type'] ?? ''),
                    (string)($part['content-disposition'] ?? ''),
                    $part['content-id'] ?? null,
                    isset($part['id']) ? (int)$part['id'] : null
                );
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $attachments = array_merge($attachments, self::collectAttachments($part['content']));
            }
        }

        return $attachments;
    }

    /**
     * Internal recursive builder that reuses a single converter instance.
     *
     * @param array $entry Typically [messageData, childrenArray]
     * @param BodyConverter $converter Converter used to normalize message bodies
     * @return Message|null A normalized message node, or null when data is invalid
     */
    private static function fromNotmuchEntryWithConverter(array $entry, BodyConverter $converter): ?self
    {
        $message = $entry[0];
        $children = $entry[1] ?? [];

        $attachments = self::collectAttachments($message['body'] ?? []);
        $body = self::normalizeBody(
            $message['body'] ?? [],
            (string)($message['id'] ?? ''),
            $attachments,
            $converter
        );

        $childMessages = [];
        foreach ($children as $childEntry) {
            $child = self::fromNotmuchEntryWithConverter($childEntry, $converter);
            if ($child !== null) {
                $childMessages[] = $child;
            }
        }

        return new self(
            (string)($message['id'] ?? ''),
            (array)($message['headers'] ?? []),
            (array)($message['tags'] ?? []),
            (int)($message['timestamp'] ?? 0),
            (string)($message['date_relative'] ?? ''),
            $body,
            $attachments,
            $childMessages
        );
    }

    /**
     * Pick and normalize the preferred body using provided helpers.
     *
     * @param array[] $parts Body parts array from notmuch
     * @param string $messageId Message id
     * @param Attachment[] $attachments Collected attachments
     * @param BodyConverter $converter Converter used to normalize message bodies
     * @return string|null Purified HTML body or null when missing
     */
    private static function normalizeBody(
        array $parts,
        string $messageId,
        array $attachments,
        BodyConverter $converter
    ): ?string {
        $selection = self::selectPreferredBody($parts);
        if ($selection === null) {
            return null;
        }

        if ($selection['type'] === 'html') {
            return $converter->convertHtml((string)$selection['content'], $messageId, $attachments);
        }

        return $converter->convertText((string)$selection['content']);
    }

    /**
     * Choose the preferred body content from notmuch parts (HTML first, then plain text).
     *
     * @param array[] $parts Body parts array from notmuch
     * @return array{type:string,content:string}|null Selected body type and content
     */
    private static function selectPreferredBody(array $parts): ?array
    {
        $html = self::findBodyByMime($parts, 'text/html');
        if ($html !== null) {
            return ['type' => 'html', 'content' => $html];
        }

        $plain = self::findBodyByMime($parts, 'text/plain');
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
    private static function findBodyByMime(array $parts, string $mime): ?string
    {
        foreach ($parts as $part) {
            $contentType = strtolower((string)($part['content-type'] ?? ''));
            if ($contentType !== '' && str_starts_with($contentType, $mime) && isset($part['content'])) {
                return (string)$part['content'];
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $found = self::findBodyByMime($part['content'], $mime);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
