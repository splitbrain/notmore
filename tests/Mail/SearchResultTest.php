<?php

namespace splitbrain\notmore\Tests\Mail;

use PHPUnit\Framework\TestCase;
use splitbrain\notmore\Mail\SearchResult;

class SearchResultTest extends TestCase
{
    public function testFromNotmuchParsesAndNormalizesFields(): void
    {
        $payload = [
            'thread' => 'thread-1',
            'subject' => 'Subject',
            'authors' => 'Author <a@example.com>',
            'tags' => ['inbox', 'unread'],
            'timestamp' => 1700001000,
            'date_relative' => 'today',
            'total' => '5',
            'matched' => 3,
            'query' => ['id:abc def'],
        ];

        $result = SearchResult::fromNotmuch($payload);

        $this->assertSame('thread-1', $result->id);
        $this->assertSame('Subject', $result->subject);
        $this->assertSame('Author <a@example.com>', $result->authors);
        $this->assertSame(['inbox', 'unread'], $result->tags);
        $this->assertSame(1700001000, $result->timestamp);
        $this->assertSame('today', $result->date_relative);
        $this->assertSame(5, $result->total);
        $this->assertSame(3, $result->matched);
        $this->assertSame(['abc', 'def'], $result->matches);
    }

    public function testFromNotmuchHandlesMissingOptionalFields(): void
    {
        $payload = [
            'thread' => 't-1',
            'query' => [],
        ];

        $result = SearchResult::fromNotmuch($payload);

        $this->assertSame('t-1', $result->id);
        $this->assertSame('[No Subject]', $result->subject);
        $this->assertNull($result->authors);
        $this->assertSame([], $result->tags);
        $this->assertSame(0, $result->timestamp);
        $this->assertSame('', $result->date_relative);
        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->matched);
        $this->assertSame([''], $result->matches);
    }
}
