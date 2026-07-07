<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Classification for the data an {@see \App\Entity\Assistant}'s knowledge
 * base and prompt handle.
 *
 * Curators pick one entry on the create-wizard's metadata step; the value
 * is persisted verbatim on the row and drives reviewer guidance around
 * whether the assistant is safe to share with a wider audience. Every
 * case's short label and longer descriptive text is exposed as a
 * translation key via {@see self::label()} / {@see self::description()}
 * so form widgets and detail-page copy can call the translator without
 * duplicating the mapping.
 */
enum DataSensitivity: string
{
    /**
     * Ordinary personal information covered by GDPR articles 6 and 7 —
     * routine, non-sensitive personal data.
     */
    case OrdinaryPersonal = 'ordinary_personal';

    /**
     * Confidential data — not personal but requires organisational
     * safeguards (contracts, tender material, internal memos).
     */
    case Confidential = 'confidential';

    /**
     * Special-category personal data covered by GDPR article 9
     * (health, ethnicity, religious beliefs, etc.).
     */
    case SensitivePersonal = 'sensitive_personal';

    /**
     * Translation key for this case's short label.
     *
     * @return string a translator id under the default catalogue
     */
    public function label(): string
    {
        return 'assistant.data_sensitivity.'.$this->value.'.label';
    }

    /**
     * Translation key for this case's longer descriptive text.
     *
     * @return string a translator id under the default catalogue
     */
    public function description(): string
    {
        return 'assistant.data_sensitivity.'.$this->value.'.description';
    }
}
