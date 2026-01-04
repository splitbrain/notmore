<?php

namespace splitbrain\notmore\Notmuch;

class ShowClient extends Client
{
    /**
     * Run a notmuch show and return decoded JSON results.
     *
     * @param string $query Notmuch show expression (e.g. 'thread:XYZ')
     * @return array
     * @throws \Exception when the notmuch command fails or JSON cannot be decoded
     */
    public function show(string $query): array
    {
        $args = [
            'show',
            '--format=json',
            '--entire-thread=true',
            '--include-html',
            $query
        ];

        return $this->runJson($args);
    }
}
