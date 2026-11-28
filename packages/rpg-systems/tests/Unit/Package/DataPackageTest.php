<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Package;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Format\SystemWriter;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Package\DataPackage;
use Tilttek\RpgSystems\Package\DataPackageReader;
use Tilttek\RpgSystems\Package\DataPackageWriter;
use Tilttek\RpgSystems\Package\UpstreamFile;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;
use UnexpectedValueException;

#[CoversClass(DataPackage::class)]
#[CoversClass(DataPackageReader::class)]
#[CoversClass(DataPackageWriter::class)]
#[CoversClass(UpstreamFile::class)]
#[CoversClass(SystemReader::class)]
final class DataPackageTest extends TestCase
{
    private const string DIGEST = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private string $directory;

    private Provenance $source;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/rpg-data-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->source = new Provenance('Open Ranges', Licence::CcBy40, 'Ranges from Open Ranges — under CC BY 4.0.', 'A publisher', 'https://example.org/ranges');
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->files($this->directory)) as $file) {
            is_dir($file) && !is_link($file) ? rmdir($file) : unlink($file);
        }

        rmdir($this->directory);
    }

    public function testAWrittenPackageReadsBackToTheSameSystem(): void
    {
        $system = $this->system();
        $upstream = [new UpstreamFile('Open Ranges, English', 'https://example.org/ranges.pdf', self::DIGEST, 1234, 'en-ca')];
        $package = new DataPackageWriter()->write($this->directory, 'acme/rpg-data-open-ranges', $system, $upstream);

        self::assertSame(['acme/rpg-data-open-ranges', 'open-ranges', '1.2.1'], [$package->name, $package->system, $package->version]);
        self::assertSame([DataPackageWriter::DOCUMENT => hash_file('sha256', $this->directory.'/system.json')], $package->documents);
        self::assertSame($upstream, $package->upstream);
        self::assertSame('en_CA', $package->upstream[0]->locale);
        self::assertEquals($system, new DataPackageReader()->load($this->directory));
        self::assertEquals($package, new DataPackageReader()->manifest($this->directory));
        self::assertSame(
            "Ranges from Open Ranges — under CC BY 4.0. (Creative Commons Attribution 4.0 International: https://creativecommons.org/licenses/by/4.0/)\n",
            file_get_contents($this->directory.'/ATTRIBUTION.txt'),
        );

        $manifest = json_decode((string) file_get_contents($this->directory.'/rpg-data.json'), true, 8, \JSON_THROW_ON_ERROR);
        self::assertSame([
            'format' => 'tilttek-rpg-data/1',
            'name' => 'acme/rpg-data-open-ranges',
            'system' => 'open-ranges',
            'version' => '1.2.1',
            'source' => ['title' => 'Open Ranges', 'licence' => 'CC-BY-4.0', 'attribution' => 'Ranges from Open Ranges — under CC BY 4.0.', 'author' => 'A publisher', 'url' => 'https://example.org/ranges'],
            'documents' => ['system.json' => $package->documents['system.json']],
            'upstream' => [['title' => 'Open Ranges, English', 'url' => 'https://example.org/ranges.pdf', 'sha256' => self::DIGEST, 'bytes' => 1234, 'locale' => 'en_CA']],
        ], $manifest);
        self::assertStringEndsWith("\n}\n", (string) file_get_contents($this->directory.'/rpg-data.json'));
        self::assertStringContainsString('Open Ranges — under', (string) file_get_contents($this->directory.'/rpg-data.json'));
        self::assertStringContainsString("/rpg-data-open-ranges\",\n    \"system\"", (string) file_get_contents($this->directory.'/rpg-data.json'));
    }

    public function testAManifestWithoutUpstreamFilesLeavesTheFieldOut(): void
    {
        $writer = new DataPackageWriter();
        $json = $writer->json($writer->manifest('acme/data', $this->system()));

        self::assertStringNotContainsString('upstream', $json);
        self::assertSame([], new DataPackageReader()->decode($json)->upstream);
        self::assertStringNotContainsString('"locale"', $writer->json($writer->manifest('acme/data', $this->system(), [new UpstreamFile('File', 'https://example.org/f.pdf', self::DIGEST, 1)])));
    }

    public function testTheAttributionFileListsTheSourcesThatAskForNone(): void
    {
        $open = new Provenance('Free Ranges', Licence::Cc0, 'Ranges from Free Ranges.', 'A publisher', 'https://example.org/free');
        $system = new GameSystem('free-ranges', Translations::of(['en' => 'Free Ranges']), $open, '1.0');

        new DataPackageWriter()->write($this->directory, 'acme/free', $system);

        self::assertStringContainsString('Free Ranges', (string) file_get_contents($this->directory.'/ATTRIBUTION.txt'));
    }

    public function testTheDefaultLimitOfAManifestIs100000Bytes(): void
    {
        $writer = new DataPackageWriter();
        $json = $writer->json($writer->manifest('acme/data', $this->system()));
        $reader = new DataPackageReader();

        self::assertSame('acme/data', $reader->decode($json.str_repeat(' ', 100_000 - \strlen($json)))->name);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The manifest is longer than 100000 bytes.');

        $reader->decode($json.str_repeat(' ', 100_001 - \strlen($json)));
    }

    public function testADocumentPathOfAPackageIsCheckedWhenThePackageIsMade(): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('"../a.json" is not a relative path of a document');

        new DataPackage('a/b', 'open-ranges', '1.0', $this->source, ['../a.json' => self::DIGEST]);
    }

    public function testASystemWithoutVersionIsNotAPackage(): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The system "open-ranges" has no version: a data package holds one version of its source.');

        new DataPackageWriter()->manifest('acme/data', new GameSystem('open-ranges', Translations::of(['en' => 'Open Ranges']), $this->source));
    }

    public function testTheWriterNeedsAnExistingDirectory(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('is not a directory.');

        new DataPackageWriter()->write($this->directory.'/missing', 'acme/data', $this->system());
    }

    public function testAFileThatCannotBeWrittenIsReported(): void
    {
        mkdir($this->directory.'/system.json');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('system.json" cannot be written.');

        new DataPackageWriter()->write($this->directory, 'acme/data', $this->system());
    }

    public function testADocumentChangedAfterTheManifestIsRefused(): void
    {
        new DataPackageWriter()->write($this->directory, 'acme/data', $this->system());
        file_put_contents($this->directory.'/system.json', ' ', \FILE_APPEND);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('system.json: the document does not match the digest of the manifest.');

        new DataPackageReader()->load($this->directory);
    }

    public function testADocumentOutsideThePackageIsRefused(): void
    {
        $outside = $this->directory.'/../'.basename($this->directory).'-outside.json';
        file_put_contents($outside, new SystemWriter()->toJson($this->system()));
        new DataPackageWriter()->write($this->directory, 'acme/data', $this->system());
        unlink($this->directory.'/system.json');
        symlink($outside, $this->directory.'/system.json');

        try {
            new DataPackageReader()->load($this->directory);
            self::fail('A document linked from outside the package is read.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('system.json: the document is not a file inside the package.', $invalidDocument->getMessage());
        } finally {
            unlink($outside);
        }
    }

    public function testAMissingDocumentIsRefused(): void
    {
        new DataPackageWriter()->write($this->directory, 'acme/data', $this->system());
        unlink($this->directory.'/system.json');

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('system.json: the document is not a file inside the package.');

        new DataPackageReader()->load($this->directory);
    }

    public function testADirectoryNamedAsADocumentIsRefused(): void
    {
        $this->manifestWith(['documents' => ['data.json' => self::DIGEST]]);
        mkdir($this->directory.'/data.json');

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('data.json: the document is not a file inside the package.');

        new DataPackageReader()->load($this->directory);
    }

    /**
     * @param array<string, mixed> $manifest the fields that differ from the written manifest
     */
    #[DataProvider('mismatches')]
    public function testTheSystemMustBeTheOneTheManifestNames(array $manifest, string $message): void
    {
        $this->manifestWith($manifest);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DataPackageReader()->load($this->directory);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function mismatches(): iterable
    {
        yield 'another system' => [['system' => 'other'], 'system: the documents must hold the one system "other".'];
        yield 'another version' => [['version' => '2.0'], 'version: the system is in the version "1.2.1", not "2.0".'];
        yield 'another source' => [['source' => ['title' => 'Another', 'licence' => 'CC0-1.0']], 'source: the source of the system is not the source of the manifest.'];
    }

    public function testTwoSystemsInOnePackageAreRefused(): void
    {
        $writer = new SystemWriter();
        file_put_contents($this->directory.'/a.json', $writer->toJson($this->system()));
        file_put_contents($this->directory.'/b.json', $writer->toJson(new GameSystem('other', Translations::of(['en' => 'Other']), $this->source, '1.2.1')));
        $this->manifestWith(['documents' => ['a.json' => hash_file('sha256', $this->directory.'/a.json'), 'b.json' => hash_file('sha256', $this->directory.'/b.json')]]);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('system: the documents must hold the one system "open-ranges".');

        new DataPackageReader()->load($this->directory);
    }

    public function testASystemWithoutVersionInAPackageIsRefused(): void
    {
        file_put_contents($this->directory.'/a.json', new SystemWriter()->toJson(new GameSystem('open-ranges', Translations::of(['en' => 'Open Ranges']), $this->source)));
        $this->manifestWith(['documents' => ['a.json' => hash_file('sha256', $this->directory.'/a.json')]]);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('version: the system is in the version "", not "1.2.1".');

        new DataPackageReader()->load($this->directory);
    }

    public function testADirectoryWithoutManifestIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('rpg-data.json: the package has no manifest.');

        new DataPackageReader()->manifest($this->directory);
    }

    public function testAPackageMustBeADirectory(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('missing: the package is not a directory.');

        new DataPackageReader()->load($this->directory.'/missing');
    }

    public function testAFileIsNotAPackage(): void
    {
        file_put_contents($this->directory.'/file', '');

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('file: the package is not a directory.');

        new DataPackageReader()->manifest($this->directory.'/file');
    }

    public function testALongManifestIsRefusedBeforeBeingRead(): void
    {
        file_put_contents($this->directory.'/rpg-data.json', str_repeat(' ', 101));

        try {
            new DataPackageReader(maxManifestBytes: 100)->manifest($this->directory);
            self::fail('A long manifest is read.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('rpg-data.json: the manifest is longer than 100 bytes.', $invalidDocument->getMessage());
        }

        file_put_contents($this->directory.'/rpg-data.json', str_repeat(' ', 100));

        $this->expectExceptionMessageIsOrContains('The document is not valid JSON');
        new DataPackageReader(maxManifestBytes: 100)->manifest($this->directory);
    }

    public function testALongManifestTextIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The manifest is longer than 10 bytes.');

        new DataPackageReader(maxManifestBytes: 10)->decode(str_repeat(' ', 11));
    }

    /**
     * @param array<string, mixed> $manifest
     */
    #[DataProvider('invalidManifests')]
    public function testAManifestThatCannotBeReadIsRefusedWithItsPlace(array $manifest, string $message): void
    {
        $base = [
            'format' => 'tilttek-rpg-data/1',
            'name' => 'acme/data',
            'system' => 'open-ranges',
            'version' => '1.0',
            'source' => ['title' => 'Ours', 'licence' => 'owned'],
            'documents' => ['system.json' => self::DIGEST],
        ];

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DataPackageReader()->decode(json_encode(array_filter([...$base, ...$manifest], static fn (mixed $value): bool => null !== $value), \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'no format' => [['format' => null], 'format: the manifest must declare "format": "tilttek-rpg-data/1".'];
        yield 'another format' => [['format' => 'tilttek-rpg-data/2'], 'format: the manifest must declare'];
        yield 'an unknown field' => [['licence' => 'MIT'], '"licence" is not one of format, name, system, version, source, documents, upstream.'];
        yield 'a source that is not a mapping' => [['source' => 'Ours'], 'source: a mapping of names to values is expected.'];
        yield 'a source that is a list' => [['source' => ['Ours']], 'source: a mapping of names to values is expected.'];
        yield 'no source' => [['source' => null], 'source.licence: a text is expected.'];
        yield 'a licence outside the list' => [['source' => ['title' => 'Ours', 'licence' => 'OGL-1.0a']], 'source.licence: the licence must be one of'];
        yield 'a digest that is not a text' => [['documents' => ['system.json' => ['x']]], 'documents.system.json: a text is expected.'];
        yield 'a bad digest' => [['documents' => ['system.json' => 'abc']], 'The digest of the document "system.json" is not 64 lowercase hexadecimal digits.'];
        yield 'no documents' => [['documents' => null], 'The package "acme/data" must hold 1 to 64 documents.'];
        yield 'a bad name' => [['name' => 'Acme Data'], '"Acme Data" is not a Composer package name such as "vendor/rpg-data-srd-5.2".'];
        yield 'no system' => [['system' => null], 'system: a text is expected.'];
        yield 'no version' => [['version' => null], 'version: a text is expected.'];
        yield 'upstream not a list' => [['upstream' => ['a' => 1]], 'upstream: a list is expected.'];
        yield 'an upstream file with an unknown field' => [['upstream' => [['size' => 1]]], 'upstream[0]: "size" is not one of title, url, sha256, bytes, locale.'];
        yield 'an upstream file without size' => [['upstream' => [['title' => 'F', 'url' => 'https://example.org/f', 'sha256' => self::DIGEST]]], 'upstream[0]: The size of the upstream file "F" is not between 1 and 2000000000 bytes.'];
        yield 'an upstream size that is not a number' => [['upstream' => [['title' => 'F', 'url' => 'https://example.org/f', 'sha256' => self::DIGEST, 'bytes' => '12']]], 'upstream[0].bytes: a whole number is expected.'];
        yield 'too many upstream files' => [['upstream' => array_fill(0, 65, [])], 'upstream: more than 64 items.'];
    }

    public function testAManifestDecodesItsUpstreamFiles(): void
    {
        $package = new DataPackageReader()->decode(json_encode([
            'format' => 'tilttek-rpg-data/1',
            'name' => 'acme/data',
            'system' => 'open-ranges',
            'version' => '1.0',
            'source' => ['title' => 'Ours', 'licence' => 'owned'],
            'documents' => ['data/system.yaml' => self::DIGEST, 'data/es.yml' => self::DIGEST],
            'upstream' => [['title' => 'F', 'url' => 'https://example.org/f.pdf', 'sha256' => self::DIGEST, 'bytes' => 12]],
        ], \JSON_THROW_ON_ERROR));

        self::assertSame(['data/system.yaml' => self::DIGEST, 'data/es.yml' => self::DIGEST], $package->documents);
        self::assertSame(['F', 'https://example.org/f.pdf', 12, null], [$package->upstream[0]->title, $package->upstream[0]->url->toString(), $package->upstream[0]->bytes, $package->upstream[0]->locale]);
        self::assertSame(Licence::Owned, $package->source->licence);
    }

    #[DataProvider('paths')]
    public function testADocumentPathStaysInsideThePackage(string $path, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidRecord::class);
            $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a relative path of a document inside a package, ending in .yaml, .yml or .json.', $path));
        }

        self::assertSame($path, DataPackage::path($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'a document' => ['system.json', true];
        yield 'YAML' => ['system.yaml', true];
        yield 'YML' => ['es.yml', true];
        yield 'a folder' => ['data/fr_CA/system-1.0.json', true];
        yield 'eight segments' => ['a/b/c/d/e/f/g/h.json', true];
        yield 'nine segments' => ['a/b/c/d/e/f/g/h/i.json', false];
        yield 'a parent' => ['../system.json', false];
        yield 'a parent inside' => ['data/../system.json', false];
        yield 'absolute' => ['/etc/system.json', false];
        yield 'a hidden file' => ['.system.json', false];
        yield 'a double slash' => ['data//system.json', false];
        yield 'a backslash' => ['data\system.json', false];
        yield 'another extension' => ['system.php', false];
        yield 'an extension inside' => ['system.json.php', false];
        yield 'a trailing line break' => ["system.json\n", false];
        yield 'a space' => ['my system.json', false];
        yield 'empty' => ['', false];
        yield 'a long segment' => [str_repeat('a', 60).'.json', false];
        yield 'the longest segment' => [str_repeat('a', 59).'.json', true];
    }

    /**
     * @param list<UpstreamFile> $upstream
     */
    #[DataProvider('packageLimits')]
    public function testAPackageHasLimits(int $documents, array $upstream, string $message): void
    {
        $digests = [];

        for ($index = 0; $index < $documents; ++$index) {
            $digests[\sprintf('d%d.json', $index)] = self::DIGEST;
        }

        if ('' === $message) {
            self::assertCount($documents, new DataPackage('acme/data', 'open-ranges', '1.0', $this->source, $digests, $upstream)->documents);

            return;
        }

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DataPackage('acme/data', 'open-ranges', '1.0', $this->source, $digests, $upstream);
    }

    /**
     * @return iterable<string, array{int, list<UpstreamFile>, string}>
     */
    public static function packageLimits(): iterable
    {
        $file = new UpstreamFile('F', 'https://example.org/f', self::DIGEST, 1);

        yield 'sixty-four documents' => [64, [], ''];
        yield 'sixty-five documents' => [65, [], 'The package "acme/data" must hold 1 to 64 documents.'];
        yield 'sixty-four upstream files' => [1, array_fill(0, 64, $file), ''];
        yield 'sixty-five upstream files' => [1, array_fill(0, 65, $file), 'The package "acme/data" names more than 64 upstream files.'];
    }

    public function testAPackageChecksItsFields(): void
    {
        foreach ([
            'vendor/name' => true,
            'vendor-1/rpg-data-srd-5.2' => true,
            'v.e_n/a--b' => true,
            'vendor' => false,
            'Vendor/name' => false,
            'vendor/name/' => false,
            'vendor/-name' => false,
            "vendor/name\n" => false,
        ] as $name => $valid) {
            try {
                new DataPackage($name, 'open-ranges', '1.0', $this->source, ['a.json' => self::DIGEST]);
                self::assertTrue($valid, $name);
            } catch (InvalidRecord) {
                self::assertFalse($valid, $name);
            }
        }

        foreach ([
            static fn (Provenance $source): DataPackage => new DataPackage('a/b', 'Open Ranges', '1.0', $source, ['a.json' => self::DIGEST]),
            static fn (Provenance $source): DataPackage => new DataPackage('a/b', 'open-ranges', ' ', $source, ['a.json' => self::DIGEST]),
            static fn (Provenance $source): DataPackage => new DataPackage('a/b', 'open-ranges', '1.0', $source, ['a.json' => strtoupper(self::DIGEST)]),
            static fn (Provenance $source): DataPackage => new DataPackage('a/b', 'open-ranges', '1.0', $source, ['a.json' => self::DIGEST."\n"]),
            static fn (Provenance $source): DataPackage => new DataPackage('a/b', 'open-ranges', '1.0', $source, ['a.json' => 'g'.self::DIGEST]),
        ] as $index => $make) {
            $refused = false;

            try {
                $make($this->source);
            } catch (InvalidRecord) {
                $refused = true;
            }

            self::assertTrue($refused, \sprintf('The package %d is made.', $index));
        }
    }

    public function testAnUpstreamFileChecksItsFields(): void
    {
        $file = new UpstreamFile('SRD', 'https://example.org/srd.pdf', self::DIGEST, UpstreamFile::MAX_BYTES, 'fr-ca');

        self::assertSame(['SRD', 'https://example.org/srd.pdf', self::DIGEST, 2_000_000_000, 'fr_CA'], [$file->title, $file->url->toString(), $file->sha256, $file->bytes, $file->locale]);

        foreach ([
            ['', 'https://example.org/f', self::DIGEST, 1, null, 'The title of an upstream file is required.'],
            ['F', 'ftp://example.org/f', self::DIGEST, 1, null, 'The address of the upstream file "F" is not an absolute http or https address'],
            ['F', 'https://example.org/f', 'abc', 1, null, 'The digest of the upstream file "F" is not 64 lowercase hexadecimal digits.'],
            ['F', 'https://example.org/f', self::DIGEST."\n", 1, null, 'The digest of the upstream file "F"'],
            ['F', 'https://example.org/f', 'g'.self::DIGEST, 1, null, 'The digest of the upstream file "F"'],
            ['F', 'https://example.org/f', self::DIGEST, 0, null, 'The size of the upstream file "F" is not between 1 and 2000000000 bytes.'],
            ['F', 'https://example.org/f', self::DIGEST, 2_000_000_001, null, 'The size of the upstream file "F"'],
            ['F', 'https://example.org/f', self::DIGEST, 1, 'english', '"english" is not a locale'],
        ] as [$title, $url, $digest, $bytes, $locale, $message]) {
            try {
                new UpstreamFile($title, $url, $digest, $bytes, $locale);
                self::fail($message);
            } catch (InvalidRecord $invalidRecord) {
                self::assertStringStartsWith($message, $invalidRecord->getMessage());
            }
        }

        self::assertSame(1, new UpstreamFile('F', 'https://example.org/f', self::DIGEST, 1)->bytes);
    }

    public function testTheReaderReadsAProvenanceFromAMapping(): void
    {
        self::assertTrue(new SystemReader()->provenance(['title' => 'Open Ranges', 'licence' => 'CC-BY-4.0', 'attribution' => 'Ranges from Open Ranges — under CC BY 4.0.', 'author' => 'A publisher', 'url' => 'https://example.org/ranges'])->equals($this->source));

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('origin.licence: the licence must be one of');

        new SystemReader()->provenance(['title' => 'Open Ranges', 'licence' => 'OGL'], 'origin');
    }

    private function system(): GameSystem
    {
        return new GameSystem(
            'open-ranges',
            Translations::of(['en' => 'Open Ranges', 'fr' => 'Portées ouvertes']),
            $this->source,
            '1.2.1',
            terms: [new Term('range.near', Translations::of(['en' => 'Near']), $this->source, 'range')],
        );
    }

    /**
     * Writes the package, then its manifest with some fields changed.
     *
     * @param array<string, mixed> $fields
     */
    private function manifestWith(array $fields): void
    {
        $writer = new DataPackageWriter();
        $writer->write($this->directory, 'acme/data', $this->system());

        $manifest = json_decode((string) file_get_contents($this->directory.'/rpg-data.json'), true, 8, \JSON_THROW_ON_ERROR);
        \assert(\is_array($manifest));
        file_put_contents($this->directory.'/rpg-data.json', json_encode([...$manifest, ...$fields], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $files = [];

        $names = scandir($directory);

        foreach (false === $names ? [] : $names as $name) {
            if ('.' === $name || '..' === $name) {
                continue;
            }

            $path = $directory.'/'.$name;
            $files[] = $path;

            if (is_dir($path) && !is_link($path)) {
                array_push($files, ...$this->files($path));
            }
        }

        return $files;
    }
}
