<?php

namespace splitbrain\notmore\Mail;

use Asika\Autolink\Autolink;
use HTMLPurifier;
use HTMLPurifier_Config;

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
        $message = $entry[0];
        $children = $entry[1] ?? [];

        $attachments = self::collectAttachments($message['body'] ?? []);
        $body = self::preferredBodyHtml(
            $message['body'] ?? [],
            (string)($message['id'] ?? ''),
            $attachments
        );

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
            (int)($message['timestamp'] ?? 0),
            (string)($message['date_relative'] ?? ''),
            $body,
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
     * Pick a preferred body part and return purified HTML (HTML preferred, then converted text).
     *
     * @param array $parts Body parts array from notmuch
     * @param string $messageId Message id
     * @param Attachment[] $attachments Collected attachments for cid rewriting
     * @return string|null Purified HTML body or null when missing
     */
    private static function preferredBodyHtml(array $parts, string $messageId, array $attachments): ?string
    {
        $html = self::findBodyByMime($parts, 'text/html');
        if ($html !== null) {
            $html = self::rewriteCidSources((string)$html, $messageId, $attachments);
            return self::sanitizeHtml($html);
        }

        $plain = self::findBodyByMime($parts, 'text/plain');
        if ($plain !== null) {
            $converted = self::convertTextToHtml((string)$plain);
            return self::sanitizeHtml($converted);
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

    /**
     * Convert plain text to HTML with blockquotes, auto-linked URLs, and preserved newlines.
     *
     * @param string $text Plain text body
     * @return string HTML with links, blockquotes, and <br> line breaks
     */
    private static function convertTextToHtml(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $autolink = new Autolink();
        $quoted = self::convertQuoteMarkers($text, $autolink);

        return nl2br($quoted, false);
    }

    /**
     * Convert leading ">" quote markers to nested blockquotes.
     *
     * @param string $text Plain text body
     * @param Autolink $autolink Autolink instance used to transform URLs
     * @return string HTML with blockquote wrappers and escaped line content
     */
    private static function convertQuoteMarkers(string $text, Autolink $autolink): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $text);
        $depth = 0;
        $buffer = '';
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            [$lineDepth, $content] = self::parseQuotePrefix($line);

            while ($depth > $lineDepth) {
                $buffer .= '</blockquote>';
                $depth--;
            }

            while ($depth < $lineDepth) {
                $buffer .= '<blockquote>';
                $depth++;
            }

            $escaped = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE);
            $linked = $autolink->convertEmail($autolink->convert($escaped));
            $buffer .= $linked;

            if ($index !== $lastIndex) {
                $buffer .= "\n";
            }
        }

        while ($depth > 0) {
            $buffer .= '</blockquote>';
            $depth--;
        }

        return $buffer;
    }

    /**
     * Determine quote depth for a line and strip the leading markers.
     *
     * @param string $line Raw line content
     * @return array{int,string} Depth count and remaining content
     */
    private static function parseQuotePrefix(string $line): array
    {
        if (!preg_match('/^((?:\\s*>)+)(.*)$/', $line, $matches)) {
            return [0, $line];
        }

        $depth = substr_count(str_replace(' ', '', $matches[1]), '>');
        $content = ltrim($matches[2]);

        return [$depth, $content];
    }

    /**
     * Sanitize an HTML snippet using HTMLPurifier (no caching used).
     *
     * @param string $html Raw HTML body
     * @return string Purified HTML safe for rendering
     */
    private static function sanitizeHtml(string $html): string
    {
        static $purifier = null;

        if ($purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.ForbiddenElements', ['font', 'center', 'marquee', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
            $purifier = new HTMLPurifier($config);
        }

        return $purifier->purify($html);
    }
}
