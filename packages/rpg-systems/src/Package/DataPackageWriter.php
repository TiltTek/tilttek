<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Package;

use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Format\SystemWriter;
use Tilttek\RpgSystems\GameSystem;
use UnexpectedValueException;

/**
 * Writes a system — imported from an open source, or written by hand — as a data package: its document
 * `system.json`, the manifest `rpg-data.json` with the digest of the document, and `ATTRIBUTION.txt`, the notices a
 * page that shows the material displays.
 */
final readonly class DataPackageWriter
{
    /** The document a package written by the writer holds. */
    public const string DOCUMENT = 'system.json';

    /** The notices of the package, as text. */
    public const string ATTRIBUTION = 'ATTRIBUTION.txt';

    public function __construct(
        private SystemWriter $writer = new SystemWriter(),
        private AttributionRenderer $renderer = new AttributionRenderer(),
    ) {
    }

    /**
     * The manifest of a system written as a package, without writing anything.
     *
     * @param list<UpstreamFile> $upstream the files the system was imported from
     *
     * @throws InvalidRecord when the system has no version
     */
    public function manifest(string $name, GameSystem $system, array $upstream = []): DataPackage
    {
        if (null === $system->version) {
            throw InvalidRecord::because(\sprintf('The system "%s" has no version: a data package holds one version of its source.', $system->key));
        }

        return new DataPackage($name, $system->key, $system->version, $system->provenance, [self::DOCUMENT => hash('sha256', $this->writer->toJson($system))], $upstream);
    }

    /**
     * Writes the package into an existing directory, replacing its three files.
     *
     * @param list<UpstreamFile> $upstream the files the system was imported from
     *
     * @throws InvalidRecord            when the system has no version
     * @throws UnexpectedValueException when a file cannot be written
     */
    public function write(string $directory, string $name, GameSystem $system, array $upstream = []): DataPackage
    {
        $package = $this->manifest($name, $system, $upstream);

        if (!is_dir($directory)) {
            throw new UnexpectedValueException(\sprintf('"%s" is not a directory.', $directory));
        }

        $this->put($directory.'/'.self::DOCUMENT, $this->writer->toJson($system));
        $this->put($directory.'/'.DataPackage::MANIFEST, $this->json($package));
        $this->put($directory.'/'.self::ATTRIBUTION, $this->renderer->text($system, all: true)."\n");

        return $package;
    }

    /**
     * The manifest as the JSON text of `rpg-data.json`.
     */
    public function json(DataPackage $package): string
    {
        $manifest = [
            'format' => DataPackage::FORMAT,
            'name' => $package->name,
            'system' => $package->system,
            'version' => $package->version,
            'source' => SystemWriter::source($package->source),
            'documents' => $package->documents,
        ];

        if ([] !== $package->upstream) {
            $manifest['upstream'] = array_map(static fn (UpstreamFile $file): array => array_filter([
                'title' => $file->title,
                'url' => $file->url->toString(),
                'sha256' => $file->sha256,
                'bytes' => $file->bytes,
                'locale' => $file->locale,
            ], static fn (mixed $value): bool => null !== $value), $package->upstream);
        }

        return json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    private function put(string $path, string $contents): void
    {
        if (false === @file_put_contents($path, $contents)) {
            throw new UnexpectedValueException(\sprintf('"%s" cannot be written.', $path));
        }
    }
}
