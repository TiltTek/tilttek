<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Package;

use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Text;

/**
 * The manifest of a data package: one source, in one version, imported into the library's format — its Composer name,
 * the system it holds, its source with the licence and the attribution a page displays, its documents with their
 * digests, and the upstream files it was imported from.
 */
final readonly class DataPackage
{
    /** The format a manifest declares: `format: tilttek-rpg-data/1`. */
    public const string FORMAT = 'tilttek-rpg-data/1';

    /** The name of the manifest file, at the root of the package. */
    public const string MANIFEST = 'rpg-data.json';

    /** The most documents one package holds. */
    public const int MAX_DOCUMENTS = 64;

    /** The most upstream files one manifest names. */
    public const int MAX_UPSTREAM = 64;

    private const string NAME = '{^[a-z0-9](?:[_.-]?[a-z0-9]+)*/[a-z0-9](?:(?:[_.]|-{1,2})?[a-z0-9]+)*$}D';

    private const string SEGMENT = '/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,63}$/D';

    /** @var array<string, string> the SHA-256 digest of each document, by its path in the package */
    public array $documents;

    /** @var list<UpstreamFile> */
    public array $upstream;

    /**
     * @param string                $name      the package's Composer name, `vendor/rpg-data-srd-5.2`
     * @param string                $system    the key of the system its documents hold
     * @param string                $version   the version of the source, `5.2.1`
     * @param Provenance            $source    the source of the system, with its licence and attribution
     * @param array<string, string> $documents the SHA-256 digest of each document, by its path relative to the root
     * @param list<UpstreamFile>    $upstream  the files the documents were imported from
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $name,
        public string $system,
        public string $version,
        public Provenance $source,
        array $documents,
        array $upstream = [],
    ) {
        if (1 !== preg_match(self::NAME, $name)) {
            throw InvalidRecord::because(\sprintf('"%s" is not a Composer package name such as "vendor/rpg-data-srd-5.2".', $name));
        }

        Text::key($system, 'a system');
        Text::check($version, \sprintf('The version of the package "%s"', $name));

        if ([] === $documents || \count($documents) > self::MAX_DOCUMENTS) {
            throw InvalidRecord::because(\sprintf('The package "%s" must hold 1 to %d documents.', $name, self::MAX_DOCUMENTS));
        }

        if (\count($upstream) > self::MAX_UPSTREAM) {
            throw InvalidRecord::because(\sprintf('The package "%s" names more than %d upstream files.', $name, self::MAX_UPSTREAM));
        }

        foreach ($documents as $path => $digest) {
            self::path($path);

            if (1 !== preg_match('/^[0-9a-f]{64}$/D', $digest)) {
                throw InvalidRecord::because(\sprintf('The digest of the document "%s" is not 64 lowercase hexadecimal digits.', $path));
            }
        }

        $this->documents = $documents;
        $this->upstream = $upstream;
    }

    /**
     * A path inside a package: segments of letters, digits, `.`, `_` and `-` separated by `/`, none starting with a
     * dot, at most eight, ending in `.yaml`, `.yml` or `.json`.
     *
     * @throws InvalidRecord
     */
    public static function path(string $path): string
    {
        $segments = explode('/', $path);
        $valid = \count($segments) <= 8 && 1 === preg_match('/\.(?:ya?ml|json)$/D', $path);

        foreach ($segments as $segment) {
            $valid = $valid && 1 === preg_match(self::SEGMENT, $segment);
        }

        if (!$valid) {
            throw InvalidRecord::because(\sprintf('"%s" is not a relative path of a document inside a package, ending in .yaml, .yml or .json.', $path));
        }

        return $path;
    }
}
