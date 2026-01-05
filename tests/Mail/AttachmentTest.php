<?php

namespace splitbrain\notmore\Tests\Mail;

use PHPUnit\Framework\TestCase;
use splitbrain\notmore\Mail\Attachment;

class AttachmentTest extends TestCase
{
    public function testFromNotmuchPartMapsAndSanitizesFields(): void
    {
        $metadata = [
            'content-type' => 'text/plain',
            'filename' => "\"weird\nname\"",
            'content-disposition' => 'inline',
            'content-id' => '<CID-123>',
        ];

        $attachment = Attachment::fromNotmuchPart($metadata, 5);

        $this->assertSame('text/plain', $attachment->content_type);
        $this->assertSame('weirdname', $attachment->filename);
        $this->assertSame('inline', $attachment->disposition);
        $this->assertSame('<CID-123>', $attachment->content_id);
        $this->assertSame(5, $attachment->part);
    }
}
