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
     * Initialize an attachment from raw notmuch part metadata.
     *
     * @param array $metadata Raw part metadata from notmuch
     * @param int|null $part Explicit part id (falls back to metadata id when omitted)
     */
    private function __construct(array $metadata, ?int $part = null)
    {
        $filename = (string)($metadata['filename'] ?? '');
        $this->filename = str_replace(['"', "\r", "\n"], '', $filename);

        $this->content_type = (string)($metadata['content-type'] ?? 'application/octet-stream');
        $this->disposition = (string)($metadata['content-disposition'] ?? 'attachment');
        $this->content_id = isset($metadata['content-id']) ? (string)$metadata['content-id'] : null;

        $metadataPart = $metadata['id'] ?? null;
        $this->part = $part ?? ($metadataPart !== null ? (int)$metadataPart : null);
    }

    /**
     * Build an Attachment from part metadata returned by notmuch.
     *
     * @param array $metadata Part metadata payload from notmuch
     * @param int|null $part Explicit part id when provided by the caller
     * @return self Normalized attachment
     */
    public static function fromNotmuchPart(array $metadata, ?int $part = null): self
    {
        return new self($metadata, $part);
    }
}
