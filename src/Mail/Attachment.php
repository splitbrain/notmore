<?php

namespace splitbrain\notmore\Mail;

readonly class Attachment
{
    public string $filename;
    public string $content_type;
    public string $disposition;
    public ?int $part;
    public ?string $content_id;

    /**
     * @param string $filename May be empty when not provided by notmuch
     * @param string $contentType MIME type; defaults should be handled by callers
     * @param string $disposition Content disposition (inline/attachment)
     * @param string $contentId Raw content-id
     * @param int|null $part Part id as reported by notmuch
     */
    public function __construct(
        string $filename,
        string $contentType,
        string $disposition,
        string $contentId,
        ?int $part = null

    ) {
        $this->filename = $filename;
        $this->content_type = $contentType;
        $this->disposition = $disposition;
        $this->part = $part;
        $this->content_id = $contentId;
    }

    /**
     * Build an Attachment from part metadata returned by notmuch.
     */
    public static function fromNotmuchPart(array $metadata, int $part): self
    {
        $contentType = (string)($metadata['content-type'] ?? 'application/octet-stream');
        $filename = $metadata['filename'] ?? '';
        $filename = str_replace(['"', "\r", "\n"], '', (string) $filename);

        $disposition = (string)($metadata['content-disposition'] ?? 'attachment');
        $contentId = (string)($metadata['content-id'] ?? '');

        return new self($filename, $contentType, $disposition, $contentId, $part);
    }
}
