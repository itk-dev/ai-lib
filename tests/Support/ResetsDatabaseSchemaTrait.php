<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Drop and recreate the Doctrine schema before each test.
 *
 * Tests that touch the database call {@see self::resetSchema()} from
 * their `setUp()` to guarantee they start from an empty `_test` DB.
 * Cheaper than running the full migration history and avoids state
 * leakage between tests without pulling in a transactional bundle.
 */
trait ResetsDatabaseSchemaTrait
{
    /**
     * Drop every table managed by the entity manager and recreate the
     * schema from the current ORM metadata.
     *
     * @param EntityManagerInterface $em the application's entity manager
     *                                   (resolved from the test container)
     */
    protected static function resetSchema(EntityManagerInterface $em): void
    {
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }
}
