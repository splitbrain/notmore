<?php

namespace splitbrain\notmore\Tests\Mail;

use PHPUnit\Framework\TestCase;
use splitbrain\notmore\Mail\Message;

class MessageTest extends TestCase
{
    public function testFromNotmuchEntryBuildsMessageTree(): void
    {
        $entry = [
            [
                'id' => 'msg-1',
                'headers' => ['subject' => 'Hello'],
                'tags' => ['inbox'],
                'timestamp' => 1700000000,
                'date_relative' => 'today',
                'body' => [
                    ['id' => 1, 'content-type' => 'text/html', 'content' => '<img src="cid:CID1">'],
                    ['id' => 2, 'content-type' => 'text/plain', 'content' => 'plain fallback'],
                    ['id' => 3, 'content-type' => 'image/png', 'filename' => 'img.png', 'content-id' => '<CID1>'],
                    [
                        'id' => 4,
                        'content-type' => 'multipart/mixed',
                        'content' => [
                            ['id' => 5, 'content-type' => 'text/plain', 'content' => 'nested text'],
                            ['id' => 6, 'content-type' => 'image/jpeg', 'content-id' => 'nested@id'],
                        ],
                    ],
                ],
            ],
            [
                [
                    ['id' => 'child-1', 'body' => []],
                    [],
                ],
            ],
        ];

        $message = Message::fromNotmuchEntry($entry);

        $this->assertNotNull($message);
        $this->assertSame('msg-1', $message->id);
        $this->assertSame(['inbox'], $message->tags);
        $this->assertSame(1700000000, $message->timestamp);
        $this->assertSame('today', $message->date_relative);
        $this->assertCount(2, $message->attachments);
        $this->assertSame(3, $message->attachments[0]->part);
        $this->assertSame(6, $message->attachments[1]->part);
        $this->assertNotNull($message->body);
        $this->assertStringContainsString('/attachment?id=msg-1&amp;part=3', $message->body);
        $this->assertCount(1, $message->children);
        $this->assertSame('child-1', $message->children[0]->id);
    }

    public function testFromNotmuchEntryReturnsNullForInvalidEntry(): void
    {
        $this->assertNull(Message::fromNotmuchEntry(['invalid']));
        $this->assertNull(Message::fromNotmuchEntry([]));
    }

    public function testListFromNotmuchThreadSkipsInvalidEntries(): void
    {
        $validEntry = [
            ['id' => 'msg-valid', 'body' => []],
            [],
        ];

        $entries = [
            $validEntry,
            ['bogus'],
        ];

        $messages = Message::listFromNotmuchThread($entries);

        $this->assertCount(1, $messages);
        $this->assertSame('msg-valid', $messages[0]->id);
    }

    public function testNormalizeBodyPrefersPlainTextWhenNoHtml(): void
    {
        $entry = [
            [
                'id' => 'msg-text',
                'body' => [
                    ['content-type' => 'text/plain', 'content' => 'hello'],
                ],
            ],
            [],
        ];

        $message = Message::fromNotmuchEntry($entry);

        $this->assertNotNull($message);
        $this->assertSame('hello', $message->body);
    }

    public function testNormalizeBodyReturnsNullWhenMissing(): void
    {
        $entry = [
            [
                'id' => 'msg-nobody',
                'body' => [],
            ],
            [],
        ];

        $message = Message::fromNotmuchEntry($entry);

        $this->assertNotNull($message);
        $this->assertNull($message->body);
    }

    public function testCollectAttachmentsIncludesContentIdOnlyAndNullPart(): void
    {
        $entry = [
            [
                'id' => 'msg-attach',
                'body' => [
                    ['content-type' => 'image/png', 'content-id' => '<IMGID>'],
                    ['content-type' => 'text/plain', 'content' => 'content'],
                ],
            ],
            [],
        ];

        $message = Message::fromNotmuchEntry($entry);

        $this->assertNotNull($message);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('<IMGID>', $message->attachments[0]->content_id);
        $this->assertNull($message->attachments[0]->part);
    }
}
