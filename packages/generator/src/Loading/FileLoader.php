<?php

declare(strict_types=1);

namespace Tilttek\Generator\Loading;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * Reads lists and generators from YAML (`.yaml`, `.yml`) and JSON (`.json`) files, given one by one or by directory,
 * in the order of their paths.
 */
final readonly class FileLoader
{
    private const array EXTENSIONS = ['yaml', 'yml', 'json'];

    public function __construct(private Reader $reader = new Reader())
    {
    }

    /**
     * @param list<string> $paths files and directories
     *
     * @throws InvalidDefinition
     */
    public function catalog(array $paths): Catalog
    {
        return $this->reader->catalog($this->read($paths));
    }

    /**
     * The decoded documents, by file path.
     *
     * @param list<string> $paths files and directories
     *
     * @return array<string, mixed>
     *
     * @throws InvalidDefinition
     */
    public function read(array $paths): array
    {
        $documents = [];

        foreach ($this->files($paths) as $file) {
            $documents[$file] = $this->decode($file);
        }

        return $documents;
    }

    /**
     * The files of these paths, the directories searched in depth.
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (!is_dir($path)) {
                throw InvalidDefinition::at($path, 'no such file or directory');
            }

            $found = [];

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (\in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                    $found[] = $file->getPathname();
                }
            }

            sort($found);
            array_push($files, ...$found);
        }

        return $files;
    }

    private function decode(string $file): mixed
    {
        try {
            if ('json' === strtolower(pathinfo($file, \PATHINFO_EXTENSION))) {
                return json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
            }

            return Yaml::parseFile($file);
        } catch (JsonException|ParseException $exception) {
            throw InvalidDefinition::at($file, $exception->getMessage());
        }
    }
}
