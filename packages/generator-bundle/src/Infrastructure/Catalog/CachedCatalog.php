<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Catalog;

use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\ResourceCheckerConfigCache;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\Generator\Loading\Reader;

/**
 * The application's lists and generators, read from their files once and kept as a PHP file in the cache; in debug,
 * the cache is written again when a file or a directory changes, even in the second the cache was written.
 */
final readonly class CachedCatalog
{
    /**
     * @param list<string> $paths files and directories of YAML and JSON documents
     */
    public function __construct(
        private array $paths,
        private string $cacheFile,
        private bool $debug,
        private FileLoader $loader = new FileLoader(),
        private Reader $reader = new Reader(),
    ) {
    }

    /**
     * @throws InvalidDefinition
     */
    public function catalog(): Catalog
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile, $this->debug ? [new FileChanges()] : []);

        if (!$cache->isFresh()) {
            $documents = $this->loader->read($this->paths);
            $resources = array_map(static fn (string $path): DirectoryResource|FileResource => is_dir($path) ? new DirectoryResource($path, '/\.(ya?ml|json)$/i') : new FileResource($path), $this->paths);
            $json = addcslashes(json_encode($documents, \JSON_THROW_ON_ERROR), "'\\");
            $cache->write("<?php\n\nreturn json_decode('".$json."', true, flags: \\JSON_THROW_ON_ERROR);\n", $resources);
        }

        return $this->reader->catalog($this->documents(require $cache->getPath()));
    }

    /**
     * Reads the source of the documents from the files, not from the cache.
     *
     * @throws InvalidDefinition
     */
    public function fresh(): Catalog
    {
        return $this->loader->catalog($this->paths);
    }

    /**
     * The first directory of the paths, where a new generator is written.
     */
    public function directory(): ?string
    {
        foreach ($this->paths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function documents(mixed $cached): array
    {
        $documents = [];

        foreach (\is_array($cached) ? $cached : [] as $source => $document) {
            $documents[(string) $source] = $document;
        }

        return $documents;
    }
}
