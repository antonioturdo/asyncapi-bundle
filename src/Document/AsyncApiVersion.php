<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * Supported AsyncAPI specification versions targeted by the generated document.
 */
enum AsyncApiVersion: string
{
    case V3_0_0 = '3.0.0';
    case V3_1_0 = '3.1.0';

    public static function default(): self
    {
        return self::V3_1_0;
    }

    /**
     * @throws \ValueError When the given version string is not supported.
     */
    public static function fromVersion(string $version): self
    {
        return self::from($version);
    }
}
