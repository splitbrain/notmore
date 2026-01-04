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
     * Normalize the notmuch thread structure into a simple message/children tree.
     *
     * @param array $payload Raw notmuch show payload.
     * @return array<array{id:string,headers:array,tags:array,date_relative:mixed,body:?string,children:array}>
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

            $messages[] = [
                'id' => (string)($message['id'] ?? ''),
                'headers' => $message['headers'] ?? [],
                'tags' => $message['tags'] ?? [],
                'date_relative' => $message['date_relative'] ?? ($message['timestamp'] ?? null),
                'body' => $this->firstPlainText($message['body'] ?? []),
                'children' => $this->normalizeMessages($children),
            ];
        }

        return $messages;
    }

    /**
     * Find the first text/plain body part in a message body list.
     */
    private function firstPlainText(array $parts): ?string
    {
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            if (($part['content-type'] ?? '') === 'text/plain' && isset($part['content'])) {
                return trim((string)$part['content']);
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $found = $this->firstPlainText($part['content']);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
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
