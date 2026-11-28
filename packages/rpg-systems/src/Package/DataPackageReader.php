<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Package;

use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Format\Fields;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\GameSystem;

/**
 * Reads a data package from its directory: the manifest, then its documents, each checked against its digest and kept
 * inside the package, and the system they hold checked against the manifest's system, version and source.
 */
final readonly class DataPackageReader
{
    private const array FIELDS = ['format', 'name', 'system', 'version', 'source', 'documents', 'upstream'];

    private const array UPSTREAM = ['title', 'url', 'sha256', 'bytes', 'locale'];

    /**
     * @param int $maxManifestBytes the longest manifest read, in bytes
     */
    public function __construct(
        private SystemReader $reader = new SystemReader(),
        private int $maxManifestBytes = 100_000,
    ) {
    }

    /**
     * The manifest of the package in a directory.
     *
     * @throws InvalidDocument
     */
    public function manifest(string $directory): DataPackage
    {
        $path = $this->root($directory).'/'.DataPackage::MANIFEST;
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size || $size > $this->maxManifestBytes) {
            throw InvalidDocument::at(DataPackage::MANIFEST, false === $size ? 'the package has no manifest.' : \sprintf('the manifest is longer than %d bytes.', $this->maxManifestBytes));
        }

        return $this->decode((string) file_get_contents($path));
    }

    /**
     * A manifest from its JSON text.
     *
     * @throws InvalidDocument
     */
    public function decode(string $json): DataPackage
    {
        if (\strlen($json) > $this->maxManifestBytes) {
            throw InvalidDocument::at('', \sprintf('The manifest is longer than %d bytes.', $this->maxManifestBytes));
        }

        $fields = Fields::of(new SystemReader($this->maxManifestBytes)->decode($json, Syntax::Json), '')->only(self::FIELDS);

        if (DataPackage::FORMAT !== $fields->string('format', required: false)) {
            throw InvalidDocument::at('format', \sprintf('the manifest must declare "format": "%s".', DataPackage::FORMAT));
        }

        // A source that is not a mapping becomes a list, which `provenance()` refuses with the place of the source.
        $source = $this->reader->provenance((array) $fields->value('source'));
        $documents = $fields->map('documents');
        $digests = [];

        foreach ($documents->names() as $document) {
            $digests[$document] = $documents->string($document);
        }

        $upstream = [];

        foreach ($fields->list('upstream', DataPackage::MAX_UPSTREAM) as $index => $item) {
            $file = Fields::of($item, \sprintf('upstream[%d]', $index))->only(self::UPSTREAM);
            $upstream[] = $file->record(static fn (): UpstreamFile => new UpstreamFile(
                $file->string('title'),
                $file->string('url'),
                $file->string('sha256'),
                $file->int('bytes') ?? 0,
                $file->string('locale', required: false),
            ));
        }

        return $fields->record(static fn (): DataPackage => new DataPackage(
            $fields->string('name'),
            $fields->string('system'),
            $fields->string('version'),
            $source,
            $digests,
            $upstream,
        ));
    }

    /**
     * The system of the package in a directory, with the manifest's checks: every document inside the package and
     * equal to its digest, one system of the manifest's key and version, and its source the manifest's.
     *
     * @throws InvalidDocument
     */
    public function load(string $directory): GameSystem
    {
        $root = $this->root($directory);
        $package = $this->manifest($root);
        $paths = [];

        foreach ($package->documents as $document => $digest) {
            $path = realpath($root.'/'.$document);

            if (false === $path || !is_file($path) || !str_starts_with($path, $root.\DIRECTORY_SEPARATOR)) {
                throw InvalidDocument::at($document, 'the document is not a file inside the package.');
            }

            if (!hash_equals($digest, (string) hash_file('sha256', $path))) {
                throw InvalidDocument::at($document, 'the document does not match the digest of the manifest.');
            }

            $paths[] = $path;
        }

        $systems = $this->reader->load(...$paths)->all();

        if (1 !== \count($systems) || $systems[0]->key !== $package->system) {
            throw InvalidDocument::at('system', \sprintf('the documents must hold the one system "%s".', $package->system));
        }

        $system = $systems[0];

        if ($system->version !== $package->version) {
            throw InvalidDocument::at('version', \sprintf('the system is in the version "%s", not "%s".', $system->version ?? '', $package->version));
        }

        if (!$system->provenance->equals($package->source)) {
            throw InvalidDocument::at('source', 'the source of the system is not the source of the manifest.');
        }

        return $system;
    }

    /**
     * @throws InvalidDocument
     */
    private function root(string $directory): string
    {
        $root = realpath($directory);

        if (false === $root || !is_dir($root)) {
            throw InvalidDocument::at($directory, 'the package is not a directory.');
        }

        return $root;
    }
}
