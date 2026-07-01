<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant;

use App\Assistant\OpenWebUiConfigSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the PII / instance-data stripper applied to an
 * OpenWebUI model before it is stored or re-exported.
 */
final class OpenWebUiConfigSanitizerTest extends TestCase
{
    // Verifies the allowlist keeps functional fields and drops PII, instance state, and knowledge.
    public function testKeepsAllowlistedFieldsAndDropsTheRest(): void
    {
        $model = [
            'id' => 'demo',
            'user_id' => 'redacted',
            'name' => 'Demo',
            'base_model_id' => 'gpt-4o',
            'params' => ['system' => 'prompt', 'temperature' => 0.5],
            'meta' => [
                'description' => 'd',
                'profile_image_url' => null,
                'capabilities' => ['vision' => false],
                'suggestion_prompts' => [['content' => 'hi']],
                'tags' => [['name' => 'alpha']],
                'knowledge' => [['user' => ['email' => 'a@b.dk']]],
            ],
            'access_grants' => [],
            'is_active' => false,
            'created_at' => 1,
            'updated_at' => 2,
            'user' => ['email' => 'a@b.dk'],
            'write_access' => true,
        ];

        self::assertSame([
            'id' => 'demo',
            'name' => 'Demo',
            'base_model_id' => 'gpt-4o',
            'params' => ['system' => 'prompt'],
            'meta' => [
                'description' => 'd',
                'profile_image_url' => null,
                'capabilities' => ['vision' => false],
                'suggestion_prompts' => [['content' => 'hi']],
                'tags' => [['name' => 'alpha']],
            ],
        ], (new OpenWebUiConfigSanitizer())->sanitize($model));
    }

    // Verifies a model without params or meta yields only the top-level allowlisted keys.
    public function testOmitsParamsAndMetaWhenAbsent(): void
    {
        self::assertSame(
            ['name' => 'Demo', 'base_model_id' => 'gpt-4o'],
            (new OpenWebUiConfigSanitizer())->sanitize(['name' => 'Demo', 'base_model_id' => 'gpt-4o']),
        );
    }

    // Verifies the legacy top-level `model` key is retained (it backs the base-model fallback).
    public function testKeepsLegacyModelKey(): void
    {
        self::assertSame(
            ['name' => 'Demo', 'model' => 'llama3.1:70b'],
            (new OpenWebUiConfigSanitizer())->sanitize(['name' => 'Demo', 'model' => 'llama3.1:70b']),
        );
    }

    // Verifies params/meta are dropped entirely when they carry no allowlisted keys.
    public function testDropsParamsAndMetaWithNoAllowlistedKeys(): void
    {
        $model = [
            'name' => 'Demo',
            'params' => ['temperature' => 0.5],
            'meta' => ['knowledge' => [['x' => 1]]],
        ];

        self::assertSame(['name' => 'Demo'], (new OpenWebUiConfigSanitizer())->sanitize($model));
    }
}
