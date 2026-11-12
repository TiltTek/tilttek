<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Fixture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Temporary directories of generators for the tests, removed afterwards.
 */
final class Files
{
    private function __construct()
    {
    }

    /**
     * A new directory holding a copy of the fixture's generators.
     */
    public static function directory(): string
    {
        $directory = sys_get_temp_dir().'/tilttek-generator-bundle-'.bin2hex(random_bytes(6));
        mkdir($directory.'/generators', 0o777, true);
        copy(__DIR__.'/generators/names.yaml', $directory.'/generators/names.yaml');

        return $directory;
    }

    public static function remove(string $directory): void
    {
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
