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

    /**
     * Fetch metadata for a specific part using JSON output.
     *
     * @param string $query Notmuch show expression (e.g. 'id:XYZ')
     * @param int $part Part id as reported by notmuch
     * @return array
     * @throws \Exception
     */
    public function showPartMetadata(string $query, int $part): array
    {
        $args = [
            'show',
            '--format=json',
            '--part=' . $part,
            $query
        ];

        return $this->runJson($args);
    }

    /**
     * Stream a specific part in raw format directly to the output buffer.
     *
     * @param string $query Notmuch show expression (e.g. 'id:XYZ')
     * @param int $part Part id as reported by notmuch
     * @throws \Exception
     */
    public function streamPart(string $query, int $part): void
    {
        $args = [
            'show',
            '--format=raw',
            '--part=' . $part,
            $query
        ];

        $this->stream($args);
    }
}
