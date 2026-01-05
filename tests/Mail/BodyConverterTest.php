<?php

namespace splitbrain\notmore\Tests\Mail;

use PHPUnit\Framework\TestCase;
use splitbrain\notmore\Mail\Attachment;
use splitbrain\notmore\Mail\BodyConverter;

class BodyConverterTest extends TestCase
{
    public function testConvertHtmlRewritesCidLinks(): void
    {
        $converter = new BodyConverter();
        $attachments = [
            new Attachment('image.png', 'image/png', 'inline', '<CID-IMG>', 3),
            new Attachment('ignored.txt', 'text/plain', 'attachment', null, 4),
        ];

        $html = '<img src="cid:cid-img"><a href=\'cid:CID-IMG\'>link</a><img src="cid:missing">';
        $result = $converter->convertHtml($html, 'msg/id', $attachments);

        $this->assertStringContainsString('/attachment?id=msg%2Fid&amp;part=3', $result);
        $this->assertStringContainsString('<a href="/attachment?id=msg%2Fid&amp;part=3">link</a>', $result);
    }

    public function testConvertHtmlSanitizesDangerousElements(): void
    {
        $converter = new BodyConverter();

        $html = '<h1>Title</h1><p>Safe</p><script>alert(1)</script>';
        $result = $converter->convertHtml($html, 'mid', []);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('<h1', $result);
        $this->assertStringContainsString('Title', $result);
        $this->assertStringContainsString('<p>Safe</p>', $result);
    }

    public function testConvertTextHandlesQuotesAndLinks(): void
    {
        $converter = new BodyConverter();

        $text = "> quoted\n>> deeper\nnormal line with http://example.com and user@example.com";
        $result = $converter->convertText($text);

        $expected = <<<HTML
<blockquote>quoted<br />
<blockquote>deeper<br />
</blockquote></blockquote>normal line with <a href="http://example.com">http://example.com</a> and <a href="mailto:user@example.com">user@example.com</a>
HTML;

        $this->assertSame($expected, $result);
    }

    public function testConvertTextReturnsEmptyStringForBlankInput(): void
    {
        $converter = new BodyConverter();
        $this->assertSame('', $converter->convertText('   '));
    }
}
