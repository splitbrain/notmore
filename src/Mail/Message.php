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
    public int|string|null $date_relative;
    public ?string $body;
    public bool $body_is_html;
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
     * @param int|string|null $dateRelative Relative date or timestamp
     * @param string|null $body Preferred body content
     * @param bool $bodyIsHtml True when body contains HTML
     * @param Attachment[] $attachments Collected attachments for this message
     * @param Message[] $children Child message nodes
     */
    private function __construct(
        string $id,
        array $headers,
        array $tags,
        int|string|null $dateRelative,
        ?string $body,
        bool $bodyIsHtml,
        array $attachments,
        array $children
    ) {
        $this->id = $id;
        $this->headers = $headers;
        $this->tags = $tags;
        $this->date_relative = $dateRelative;
        $this->body = $body;
        $this->body_is_html = $bodyIsHtml;
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
        $message = $entry[0];
        $children = $entry[1] ?? [];

        $attachments = self::collectAttachments($message['body'] ?? []);
        $body = self::preferredBody($message['body'] ?? []);
        if ($body['is_html']) {
            $body['content'] = self::rewriteCidSources(
                (string)$body['content'],
                (string)($message['id'] ?? ''),
                $attachments
            );
        }

        $childMessages = [];
        foreach ($children as $childEntry) {
            $child = self::fromNotmuchEntry($childEntry);
            if ($child !== null) {
                $childMessages[] = $child;
            }
        }

        return new self(
            (string)($message['id'] ?? ''),
            (array)($message['headers'] ?? []),
            (array)($message['tags'] ?? []),
            $message['date_relative'] ?? ($message['timestamp'] ?? null),
            $body['content'],
            $body['is_html'],
            $attachments,
            $childMessages
        );
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
     * Pick a preferred body part (HTML first, then plain text).
     *
     * @param array $parts Body parts array from notmuch
     * @return array{content:?string,is_html:bool}
     */
    private static function preferredBody(array $parts): array
    {
        $html = self::findBodyByMime($parts, 'text/html');
        if ($html !== null) {
            return ['content' => $html, 'is_html' => true];
        }

        $plain = self::findBodyByMime($parts, 'text/plain');
        if ($plain !== null) {
            return ['content' => trim($plain), 'is_html' => false];
        }

        return ['content' => null, 'is_html' => false];
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
     * Replace cid: URLs in HTML bodies with links to the attachment endpoint.
     *
     * @param string $html Raw HTML body
     * @param string $messageId Message id (used to build attachment URLs)
     * @param Attachment[] $attachments Attachments collected for the message
     * @return string HTML with cid: links rewritten when possible
     */
    private static function rewriteCidSources(string $html, string $messageId, array $attachments): string
    {
        if ($messageId === '') {
            return $html;
        }

        $cidToPart = [];
        foreach ($attachments as $attachment) {
            if (!isset($attachment->content_id, $attachment->part)) {
                continue;
            }
            $cid = self::normalizeContentId((string)$attachment->content_id);
            if ($cid !== '') {
                $cidToPart[$cid] = $attachment->part;
            }
        }

        if ($cidToPart === []) {
            return $html;
        }

        return (string)preg_replace_callback(
            '/\b(src|href)=(["\'])cid:([^"\']+)\2/i',
            function (array $matches) use ($cidToPart, $messageId): string {
                $cid = self::normalizeContentId($matches[3]);
                if ($cid === '' || !isset($cidToPart[$cid])) {
                    return $matches[0];
                }

                $url = '/attachment?id=' . rawurlencode($messageId) . '&part=' . $cidToPart[$cid];
                return $matches[1] . '=' . $matches[2] . $url . $matches[2];
            },
            $html
        );
    }

    /**
     * Normalize a content-id for matching (trim angle brackets, lower-case).
     *
     * @param string $contentId Raw content-id
     * @return string Normalized content-id
     */
    private static function normalizeContentId(string $contentId): string
    {
        $contentId = trim($contentId);
        $contentId = trim($contentId, '<>');
        return strtolower($contentId);
    }
}
