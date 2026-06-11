<?php
// This file is copied from config/symfony/php/.php-cs-fixer.dist.php in https://github.com/itk-dev/devops_itkdev-docker.
// Feel free to edit the file, but consider making a pull request if you find a general issue with the file.

// https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst

$finder = new PhpCsFixer\Finder();
// Check all files …
$finder->in(__DIR__);
// … that are not ignored by VCS
$finder->ignoreVCSIgnored(true);

$config = new PhpCsFixer\Config();
$config->setFinder($finder);

$config->setRules([
  '@Symfony' => true,
  // Override the Symfony default that vertically aligns @param / @return
  // columns by padding names and descriptions with spaces. We keep tags
  // left-aligned so descriptions don't get pushed into hard-to-read
  // multi-line wraps.
  'phpdoc_align' => ['align' => 'left'],
]);

return $config;
