<?php

namespace splitbrain\notmore\Mail;

use Asika\Autolink\Autolink;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Utility for converting notmuch message bodies into purified HTML.
 */
class BodyConverter
{
    private HTMLPurifier $purifier;

    /**
     * Initialize a converter with a dedicated HTMLPurifier instance.
     */
    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.ForbiddenElements', ['font', 'center', 'marquee', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
        $config->set('CSS.ForbiddenProperties', ['font-family', 'font', 'font-size', 'color']);
        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Convert an HTML body into purified, cid-rewritten HTML.
     *
     * @param string $html Raw HTML body
     * @param string $messageId Message id
     * @param Attachment[] $attachments Attachments collected for the message
     * @return string Purified HTML body
     */
    public function convertHtml(string $html, string $messageId, array $attachments): string
    {
        $rewritten = $this->rewriteCidSources($html, $messageId, $attachments);
        return $this->sanitizeHtml($rewritten);
    }

    /**
     * Convert plain text to sanitized HTML with blockquotes, auto-linked URLs, and preserved newlines.
     *
     * @param string $text Plain text body
     * @return string HTML with links, blockquotes, and <br> line breaks
     */
    public function convertText(string $text): string
    {
        $html = $this->convertTextToHtml($text);
        return $this->sanitizeHtml($html);
    }

    /**
     * Replace cid: URLs in HTML bodies with links to the attachment endpoint.
     *
     * @param string $html Raw HTML body
     * @param string $messageId Message id (used to build attachment URLs)
     * @param Attachment[] $attachments Attachments collected for the message
     * @return string HTML with cid: links rewritten when possible
     */
    private function rewriteCidSources(string $html, string $messageId, array $attachments): string
    {
        if ($messageId === '') {
            return $html;
        }

        $cidToPart = [];
        foreach ($attachments as $attachment) {
            if (!isset($attachment->content_id, $attachment->part)) {
                continue;
            }
            $cid = $this->normalizeContentId((string)$attachment->content_id);
            if ($cid !== '') {
                $cidToPart[$cid] = $attachment->part;
            }
        }

        if ($cidToPart === []) {
            return $html;
        }

        return (string)preg_replace_callback(
            '/\\b(src|href)=(["\'])cid:([^"\']+)\\2/i',
            function (array $matches) use ($cidToPart, $messageId): string {
                $cid = $this->normalizeContentId($matches[3]);
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
    private function normalizeContentId(string $contentId): string
    {
        $contentId = trim($contentId);
        $contentId = trim($contentId, '<>');
        return strtolower($contentId);
    }

    /**
     * Convert plain text to HTML with blockquotes, auto-linked URLs, and preserved newlines (no sanitization).
     *
     * @param string $text Plain text body
     * @return string HTML with links, blockquotes, and <br> line breaks
     */
    private function convertTextToHtml(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $autolink = new Autolink();
        $quoted = $this->convertQuoteMarkers($text, $autolink);

        return nl2br($quoted, false);
    }

    /**
     * Convert leading ">" quote markers to nested blockquotes.
     *
     * @param string $text Plain text body
     * @param Autolink $autolink Autolink instance used to transform URLs
     * @return string HTML with blockquote wrappers and escaped line content
     */
    private function convertQuoteMarkers(string $text, Autolink $autolink): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $text);
        $depth = 0;
        $buffer = '';
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            [$lineDepth, $content] = $this->parseQuotePrefix($line);

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
    private function parseQuotePrefix(string $line): array
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
    private function sanitizeHtml(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
