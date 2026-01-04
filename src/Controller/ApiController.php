<?php

namespace splitbrain\notmore\Controller;

use splitbrain\notmore\HttpException;
use splitbrain\notmore\Notmuch\SearchClient;
use splitbrain\notmore\Notmuch\ShowClient;

class ApiController extends AbstractController
{
    /**
     * Search threads via notmuch.
     *
     * @param array $data expects:
     *  - query: string, required; notmuch search expression
     *  - limit: int >= 0, optional, defaults to 50
     *  - offset: int >= 0, optional, defaults to 0
     */
    public function search(array $data): array
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

        return $client->search($query);
    }

    /**
     * Fetch a full thread by notmuch thread id.
     *
     * @param array $data expects:
     *  - thread: string, required; notmuch thread id (without the thread: prefix)
     */
    public function thread(array $data): array
    {
        $thread = trim((string)($data['thread'] ?? $data['id'] ?? ''));
        if ($thread === '') {
            throw new HttpException('Missing thread id', 400);
        }

        $client = new ShowClient($this->app);

        return $client->show('thread:' . $thread);
    }
}
