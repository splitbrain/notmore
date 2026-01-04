<?php

namespace splitbrain\notmore\Notmuch;

class SearchClient extends Client
{
    private int $limit = 0;
    private int $offset = 0;

    /**
     * Set the maximum number of threads to return.
     */
    public function setLimit(int $limit): void
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('Limit must be >= 0');
        }

        $this->limit = $limit;
    }

    /**
     * Set the offset for result pagination.
     */
    public function setOffset(int $offset): void
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Offset must be >= 0');
        }

        $this->offset = $offset;
    }

    /**
     * Run a notmuch search and return decoded JSON results.
     *
     * @param string $query Notmuch search expression
     * @return SearchResult[]
     * @throws \Exception when the notmuch command fails or JSON cannot be decoded
     */
    public function search(string $query): array
    {
        $args = ['search', '--format=json'];

        $args[] = '--limit=' . $this->limit;
        $args[] = '--offset=' . $this->offset;

        $args[] = $query;

        return $this->runJson($args);
    }
}
