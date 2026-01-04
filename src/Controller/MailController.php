<?php

namespace splitbrain\notmore\Controller;

use splitbrain\notmore\HttpException;
use splitbrain\notmore\Notmuch\SearchClient;
use splitbrain\notmore\Notmuch\ShowClient;

class MailController extends AbstractController
{
    /**
     * Render an HTML fragment with the search result list.
     *
     * @param array $data expects:
     *  - query: string, required; notmuch search expression
     *  - limit: int >= 0, optional, defaults to 50
     *  - offset: int >= 0, optional, defaults to 0
     */
    public function search(array $data): string
    {
        $query = trim((string)($data['query'] ?? ''));
        if ($query === '') {
            throw new HttpException('Missing search query', 400);
        }

        $limit = (int)($data['limit'] ?? 50);
        $offset = (int)($data['offset'] ?? 0);

        $client = new SearchClient($this->app);
        $client->setLimit($limit);
        $client->setOffset($offset);

        $threads = $client->search($query);

        return $this->render('mail/search.html.twig', [
            'query' => $query,
            'threads' => $threads,
            'offset' => $offset,
        ]);
    }

    /**
     * Render an HTML fragment for a thread view.
     *
     * @param array $data expects:
     *  - thread: string, required; notmuch thread id (without the thread: prefix)
     */
    public function thread(array $data): string
    {
        $thread = trim((string)($data['thread'] ?? $data['id'] ?? ''));
        if ($thread === '') {
            throw new HttpException('Missing thread id', 400);
        }

        $client = new ShowClient($this->app);

        $payload = $client->show('thread:' . $thread);

        return $this->render('mail/thread.html.twig', [
            'threadId' => $thread,
            'messages' => $this->normalizeThread($payload),
            'subject' => $this->extractSubject($payload),
        ]);
    }

    /**
     * Stream a single attachment (by part id) directly to the client.
     *
     * @param array $data expects:
     *  - id: string, required; message id (with or without id: prefix)
     *  - part: int >= 0, required; part id reported by notmuch
     */
    public function attachment(array $data): ?string
    {
        $messageId = trim((string)($data['id'] ?? ''));
        if ($messageId === '') {
            throw new HttpException('Missing message id', 400);
        }

        $part = (int)($data['part'] ?? -1);
        if ($part < 0) {
            throw new HttpException('Missing or invalid part id', 400);
        }

        // Always enforce an id: query to avoid arbitrary notmuch queries
        $query = 'id:' . ltrim($messageId, 'id:');
        $client = new ShowClient($this->app);

        $metadata = $client->showPartMetadata($query, $part);
        $headers = $this->extractPartHeaders($metadata);

        header('Content-Type: ' . $headers['content_type']);
        if ($headers['filename'] !== null) {
            header('Content-Disposition: attachment; filename="' . $headers['filename'] . '"');
        }

        $client->streamPart($query, $part);

        return null;
    }

    /**
     * Normalize the notmuch thread structure into a simple message/children tree.
     *
     * @param array $payload Raw notmuch show payload.
     * @return array<array{
     *     id:string,
     *     headers:array,
     *     tags:array,
     *     date_relative:mixed,
     *     body:?string,
     *     body_is_html:bool,
     *     attachments:array<int,array{filename:string,content_type:string,disposition:string,part:?int}>,
     *     children:array
     * }>
     */
    private function normalizeThread(array $payload): array
    {
        // When queried by thread id, the payload contains a single thread.
        $thread = $payload[0] ?? $payload;
        if (!is_array($thread)) {
            return [];
        }

        return $this->normalizeMessages($thread);
    }

    /**
     * Convert the recursive notmuch response format into a cleaner structure.
     *
     * Each message entry is [messageData, childrenArray].
     */
    private function normalizeMessages(array $entries): array
    {
        $messages = [];

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry[0]) || !is_array($entry[0])) {
                continue;
            }

            $message = $entry[0];
            $children = is_array($entry[1] ?? null) ? $entry[1] : [];

            $attachments = $this->collectAttachments($message['body'] ?? []);
            $body = $this->preferredBody($message['body'] ?? []);
            if ($body['is_html']) {
                $body['content'] = $this->rewriteCidSources(
                    (string)$body['content'],
                    (string)($message['id'] ?? ''),
                    $attachments
                );
            }

            $messages[] = [
                'id' => (string)($message['id'] ?? ''),
                'headers' => $message['headers'] ?? [],
                'tags' => $message['tags'] ?? [],
                'date_relative' => $message['date_relative'] ?? ($message['timestamp'] ?? null),
                'body' => $body['content'],
                'body_is_html' => $body['is_html'],
                'attachments' => $attachments,
                'children' => $this->normalizeMessages($children),
            ];
        }

        return $messages;
    }

    /**
     * Pick a preferred body part (HTML first, then plain text).
     */
    private function preferredBody(array $parts): array
    {
        $html = $this->findBodyByMime($parts, 'text/html');
        if ($html !== null) {
            return ['content' => $html, 'is_html' => true];
        }

        $plain = $this->findBodyByMime($parts, 'text/plain');
        if ($plain !== null) {
            return ['content' => trim($plain), 'is_html' => false];
        }

        return ['content' => null, 'is_html' => false];
    }

    /**
     * Find the first body part matching the given MIME type.
     */
    private function findBodyByMime(array $parts, string $mime): ?string
    {
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

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
     * Collect attachment metadata from body parts (any part with a filename).
     *
     * @param array $parts
     * @return array<int,array{filename:string,content_type:string,disposition:string,part:int|null,content_id:?string}>
     */
    private function collectAttachments(array $parts): array
    {
        $attachments = [];

        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            if ((isset($part['filename']) && $part['filename'] !== '') || isset($part['content-id'])) {
                $attachments[] = [
                    'filename' => (string)$part['filename'],
                    'content_type' => (string)($part['content-type'] ?? ''),
                    'disposition' => (string)($part['content-disposition'] ?? ''),
                    'part' => isset($part['id']) ? (int)$part['id'] : null,
                    'content_id' => isset($part['content-id']) ? (string)$part['content-id'] : null,
                ];
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $attachments = array_merge($attachments, $this->collectAttachments($part['content']));
            }
        }

        return $attachments;
    }

    /**
     * Replace cid: URLs in HTML bodies with links to the attachment endpoint.
     */
    private function rewriteCidSources(string $html, string $messageId, array $attachments): string
    {
        if ($messageId === '') {
            return $html;
        }

        $cidToPart = [];
        foreach ($attachments as $attachment) {
            if (!isset($attachment['content_id'], $attachment['part'])) {
                continue;
            }
            $cid = $this->normalizeContentId((string)$attachment['content_id']);
            if ($cid !== '') {
                $cidToPart[$cid] = $attachment['part'];
            }
        }

        if ($cidToPart === []) {
            return $html;
        }

        return (string)preg_replace_callback(
            '/\\b(src|href)=("|\')cid:([^"\']+)\\2/i',
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
     */
    private function normalizeContentId(string $contentId): string
    {
        $contentId = trim($contentId);
        $contentId = trim($contentId, '<>');
        return strtolower($contentId);
    }

    /**
     * Extract useful headers from part metadata returned by notmuch.
     */
    private function extractPartHeaders(array $metadata): array
    {
        $contentType = (string)($metadata['content-type'] ?? 'application/octet-stream');
        $filename = null;
        if (isset($metadata['filename']) && $metadata['filename'] !== '') {
            $filename = str_replace(['"', "\r", "\n"], '', (string)$metadata['filename']);
        }

        return [
            'content_type' => $contentType,
            'filename' => $filename,
        ];
    }

    private function extractSubject(array $payload): ?string
    {
        if (isset($payload['subject']) && is_string($payload['subject'])) {
            return $payload['subject'];
        }

        if (isset($payload[0]['subject']) && is_string($payload[0]['subject'])) {
            return $payload[0]['subject'];
        }

        if (isset($payload['headers']['Subject']) && is_string($payload['headers']['Subject'])) {
            return $payload['headers']['Subject'];
        }

        return null;
    }
}
