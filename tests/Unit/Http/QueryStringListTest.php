<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\QueryStringList;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class QueryStringListTest extends TestCase
{
    private QueryStringList $helper;

    protected function setUp(): void
    {
        $this->helper = new QueryStringList();
    }

    public function testReturnsEmptyListWhenKeyMissing(): void
    {
        $request = Request::create('/search');

        self::assertSame([], $this->helper->fromRequest($request, 'language_model'));
    }

    public function testReturnsAllStringValues(): void
    {
        $request = Request::create('/search', 'GET', ['language_model' => ['gpt-4o', 'mistral-large']]);

        self::assertSame(
            ['gpt-4o', 'mistral-large'],
            $this->helper->fromRequest($request, 'language_model'),
        );
    }

    public function testDropsEmptyStrings(): void
    {
        $request = Request::create('/search', 'GET', ['language_model' => ['', 'gpt-4o', '']]);

        self::assertSame(['gpt-4o'], $this->helper->fromRequest($request, 'language_model'));
    }

    public function testDropsNonStringEntries(): void
    {
        // A nested-array value (e.g. `?language_model[foo]=bar`) reaches the
        // helper as an array entry inside the outer all() result. The helper
        // must skip it instead of crashing on the type mismatch.
        $request = Request::create('/search', 'GET', ['language_model' => ['gpt-4o', ['nested' => 'value']]]);

        self::assertSame(['gpt-4o'], $this->helper->fromRequest($request, 'language_model'));
    }
}
