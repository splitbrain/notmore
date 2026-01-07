<?php

namespace splitbrain\notmore\Notmuch;

class SearchClient extends Client
{
    /**
     * Run a notmuch search and return decoded JSON results.
     *
     * @param string $query Notmuch search expression
     * @return array
     * @throws \Exception when the notmuch command fails or JSON cannot be decoded
     */
    public function search(string $query): array
    {
        $args = ['search', '--format=json'];
        $args[] = $query;
        return $this->runJson($args);
    }
}
