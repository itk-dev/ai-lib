<?php

declare(strict_types=1);

namespace App\Settings;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Typed read/write surface for admin-editable runtime settings.
 *
 * Wraps the generic `setting` table so callers operate on real
 * values (e.g. an admin recipient `?string`) instead of poking at
 * `Setting` rows. Each setting key has a dedicated `get*`/`set*`
 * pair; add a new pair when adding a new setting rather than
 * exposing string keys to the call sites.
 *
 * Writes flush immediately — admin settings are low-volume and the
 * UX expectation is that a successful save takes effect on the
 * next request.
 */
class SettingsManager
{
    /**
     * Canonical key for the admin notification recipient address.
     *
     * Matched against {@see Setting::getName()}.
     */
    public const string ADMIN_RECIPIENT = 'admin_recipient';

    /**
     * Canonical key for the public-facing brand name (full title).
     */
    public const string BRAND_NAME = 'brand_name';

    /**
     * Canonical key for the public-facing brand tagline.
     */
    public const string BRAND_TAGLINE = 'brand_tagline';

    /**
     * Canonical key for the public-facing brand initials / logo glyph.
     */
    public const string BRAND_INITIALS = 'brand_initials';

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(BRAND_NAME)%')]
        private readonly string $defaultBrandName,
        #[Autowire('%env(BRAND_TAGLINE)%')]
        private readonly string $defaultBrandTagline,
        #[Autowire('%env(BRAND_INITIALS)%')]
        private readonly string $defaultBrandInitials,
    ) {
    }

    /**
     * Read the configured admin notification recipient address.
     *
     * Returns the address an administrator typed into the
     * `/admin/settings` form, or `null` when no value has been
     * saved yet. Callers that need a guaranteed address should
     * fall back to a deploy-time `MAILER_FROM`/`MAILER_TO` env
     * convention.
     *
     * @return string|null configured admin recipient, or null when unset
     */
    public function getAdminRecipient(): ?string
    {
        return $this->getString(self::ADMIN_RECIPIENT);
    }

    /**
     * Persist the admin notification recipient address.
     *
     * Inserts a new `setting` row when the key is unset, otherwise
     * updates the existing one. Pass `null` to clear the setting
     * (the row stays but its value becomes `NULL`). Flushes
     * immediately so the next request sees the new value.
     *
     * @param string|null $email recipient address to store, or null to clear
     */
    public function setAdminRecipient(?string $email): void
    {
        $this->setString(self::ADMIN_RECIPIENT, $email);
    }

    /**
     * Read the configured brand name.
     *
     * Returns the value an administrator typed into
     * `/admin/settings/site` when set, otherwise falls back to the
     * deploy-time `BRAND_NAME` env var. So a fresh install renders
     * the env-baked default until an admin overrides it through
     * the UI.
     *
     * @return string current brand name
     */
    public function getBrandName(): string
    {
        return $this->getString(self::BRAND_NAME) ?? $this->defaultBrandName;
    }

    /**
     * Persist the brand name.
     *
     * Inserts a new `setting` row when the key is unset, otherwise
     * updates the existing one. Pass `null` to revert to the
     * `BRAND_NAME` env-var default — the row stays but its value
     * becomes `NULL` so the fallback wins again.
     *
     * @param string|null $name brand name to store, or null to clear
     */
    public function setBrandName(?string $name): void
    {
        $this->setString(self::BRAND_NAME, $name);
    }

    /**
     * Read the configured brand tagline.
     *
     * Returns the value an administrator typed into
     * `/admin/settings/site` when set, otherwise falls back to the
     * deploy-time `BRAND_TAGLINE` env var.
     *
     * @return string current brand tagline
     */
    public function getBrandTagline(): string
    {
        return $this->getString(self::BRAND_TAGLINE) ?? $this->defaultBrandTagline;
    }

    /**
     * Persist the brand tagline.
     *
     * Inserts a new `setting` row when the key is unset, otherwise
     * updates the existing one. Pass `null` to revert to the
     * `BRAND_TAGLINE` env-var default.
     *
     * @param string|null $tagline brand tagline to store, or null to clear
     */
    public function setBrandTagline(?string $tagline): void
    {
        $this->setString(self::BRAND_TAGLINE, $tagline);
    }

    /**
     * Read the configured brand initials / logo glyph.
     *
     * Returns the value an administrator typed into
     * `/admin/settings/site` when set, otherwise falls back to the
     * deploy-time `BRAND_INITIALS` env var.
     *
     * @return string current brand initials
     */
    public function getBrandInitials(): string
    {
        return $this->getString(self::BRAND_INITIALS) ?? $this->defaultBrandInitials;
    }

    /**
     * Persist the brand initials.
     *
     * Inserts a new `setting` row when the key is unset, otherwise
     * updates the existing one. Pass `null` to revert to the
     * `BRAND_INITIALS` env-var default.
     *
     * @param string|null $initials brand initials to store, or null to clear
     */
    public function setBrandInitials(?string $initials): void
    {
        $this->setString(self::BRAND_INITIALS, $initials);
    }

    /**
     * Look up a stored string setting by key.
     *
     * Returns the row's `value`, or `null` when the row doesn't
     * exist or its value is `NULL`. Internal helper — callers go
     * through the typed accessors instead.
     *
     * @param string $name canonical setting key
     *
     * @return string|null stored value, or null when unset
     */
    private function getString(string $name): ?string
    {
        return $this->repository->findOneByName($name)?->getValue();
    }

    /**
     * Persist (insert or update) a string setting.
     *
     * Looks up the row by name, creates one when missing, sets its
     * value, and flushes. Internal helper — callers go through the
     * typed accessors instead.
     *
     * @param string      $name  canonical setting key
     * @param string|null $value value to store, or null to clear
     */
    private function setString(string $name, ?string $value): void
    {
        $setting = $this->repository->findOneByName($name);
        if (null === $setting) {
            $setting = new Setting($name, $value);
            $this->em->persist($setting);
        } else {
            $setting->setValue($value);
        }
        $this->em->flush();
    }
}
