<?php

namespace splitbrain\notmore\Controller;

use splitbrain\notmore\HttpException;
use splitbrain\notmore\Mail\Attachment;
use splitbrain\notmore\Mail\Message;
use splitbrain\notmore\Mail\SearchResult;
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
        $threads = array_map([SearchResult::class, 'fromNotmuch'], $threads);

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
            throw new HttpException('Missing thread id', 404);
        }

        $client = new ShowClient($this->app);

        $payload = $client->show('thread:' . $thread);
        $threadEntries = $payload[0] ?? $payload;
        $messages = Message::listFromNotmuchThread($threadEntries);
        $subject = $this->extractSubjectFromMessages($messages);

        return $this->render('mail/thread.html.twig', [
            'threadId' => $thread,
            'subject' => $subject,
            'messages' => $messages,
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
        $attachment = Attachment::fromNotmuchPart($metadata, $part);

        header('Content-Type: ' . $attachment->content_type);
        if ($attachment->filename !== '') {
            header('Content-Disposition: attachment; filename="' . $attachment->filename . '"');
        }

        $client->streamPart($query, $part);

        return null;
    }

    /**
     * Extract the thread subject from the first message, if available.
     *
     * @param Message[] $messages
     */
    private function extractSubjectFromMessages(array $messages): ?string
    {
        if ($messages === []) {
            return null;
        }

        $first = $messages[0];
        if (isset($first->headers['Subject']) && is_string($first->headers['Subject'])) {
            return $first->headers['Subject'];
        }

        return null;
    }

}
