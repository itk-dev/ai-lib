<?php

declare(strict_types=1);

namespace App\Assistant;

/**
 * The result of exporting an assistant to a target format: the
 * serialised payload plus the metadata a download response needs.
 */
final class ExportedConfig
{
    /**
     * @param string $payload   the serialised, re-importable config
     * @param string $mediaType MIME type for the response `Content-Type`
     * @param string $extension filename extension (without the dot)
     */
    public function __construct(
        public readonly string $payload,
        public readonly string $mediaType,
        public readonly string $extension,
    ) {
    }
}
