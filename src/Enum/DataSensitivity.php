<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Classification for the data an {@see \App\Entity\Assistant}'s knowledge
 * base and prompt handle.
 *
 * Curators pick one entry on the create-wizard's metadata step; the value
 * is persisted verbatim on the row and drives reviewer guidance around
 * whether the assistant is safe to share with a wider audience. The
 * ordering (public → sensitive_personal) is intentional: it goes from the
 * least to the most restrictive category.
 */
enum DataSensitivity: string
{
    /**
     * No personal or sensitive information — freely shareable content.
     */
    case Public = 'public';

    /**
     * Internal-use information — sharable across the municipality but not
     * with the public.
     */
    case Internal = 'internal';

    /**
     * Ordinary personal information covered by GDPR articles 6 & 7.
     */
    case Personal = 'personal';

    /**
     * Special-category personal information covered by GDPR article 9
     * (health, ethnicity, religious beliefs, etc.).
     */
    case SensitivePersonal = 'sensitive_personal';
}
