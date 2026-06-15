<?php

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// The integration suite needs the schema in
// place before DAMA starts wrapping tests in transactions, so build it
// here from the current ORM metadata.
if ('1' !== ($_SERVER['TESTS_SKIP_SCHEMA'] ?? '')) {
    $kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
    $kernel->boot();
    $em = $kernel->getContainer()->get('doctrine')->getManager();
    \assert($em instanceof EntityManagerInterface);
    $schemaTool = new SchemaTool($em);
    $schemaTool->dropDatabase();
    $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
    $kernel->shutdown();
}
