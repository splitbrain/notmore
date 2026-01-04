<?php

namespace splitbrain\notmore\Mail;

class Attachment
{
    public readonly string $filename;
    public readonly string $content_type;
    public readonly string $disposition;
    public readonly ?int $part;
    public readonly ?string $content_id;

    /**
     * @param string $filename May be empty when not provided by notmuch
     * @param string $contentType MIME type; defaults should be handled by callers
     * @param string $disposition Content disposition, if any
     * @param int|null $part Part id as reported by notmuch
     * @param string|null $contentId Raw content-id, if present
     */
    public function __construct(
        string $filename,
        string $contentType,
        string $disposition,
        ?int $part,
        ?string $contentId
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
    public static function fromNotmuchPart(array $metadata, ?int $part = null): self
    {
        $contentType = (string)($metadata['content-type'] ?? 'application/octet-stream');
        $filename = '';
        if (isset($metadata['filename']) && $metadata['filename'] !== '') {
            $filename = str_replace(['"', "\r", "\n"], '', (string)$metadata['filename']);
        }

        $disposition = (string)($metadata['content-disposition'] ?? '');
        $contentId = isset($metadata['content-id']) ? (string)$metadata['content-id'] : null;

        return new self($filename, $contentType, $disposition, $part, $contentId);
    }
}
