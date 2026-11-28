<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Package;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Exception\UnknownRecord;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Package\DataPackageReader;
use Tilttek\RpgSystems\Package\DataPackageWriter;
use Tilttek\RpgSystems\Package\InstalledDataPackages;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

#[CoversClass(InstalledDataPackages::class)]
final class InstalledDataPackagesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/rpg-installed-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*/*');
        $packages = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        foreach (false === $packages ? [] : $packages as $package) {
            rmdir($package);
        }

        rmdir($this->directory);
    }

    public function testTheInstalledPackagesAreListedByNameAndLoadedIntoSystems(): void
    {
        $zeta = $this->package('acme/rpg-data-zeta', 'zeta');
        $alpha = $this->package('acme/rpg-data-alpha', 'alpha');
        $packages = new InstalledDataPackages(installed: ['acme/rpg-data-zeta' => $zeta, 'acme/rpg-data-alpha' => $alpha]);

        self::assertSame(['acme/rpg-data-alpha', 'acme/rpg-data-zeta'], $packages->names());
        self::assertTrue($packages->has('acme/rpg-data-zeta'));
        self::assertFalse($packages->has('acme/rpg-data-beta'));
        self::assertEquals($this->system('zeta'), $packages->load('acme/rpg-data-zeta'));
        self::assertEquals($this->system('alpha'), $packages->load('acme/rpg-data-alpha'));

        $systems = $packages->systems();

        self::assertSame(['alpha', 'zeta'], array_map(static fn (GameSystem $system): string => $system->key, $systems->all()));
        self::assertEquals($this->system('zeta'), $systems->get('zeta'));
    }

    public function testNoPackageGivesNoSystem(): void
    {
        $packages = new InstalledDataPackages(installed: []);

        self::assertSame([], $packages->names());
        self::assertSame([], $packages->systems()->all());
    }

    public function testAPackageNotInstalledIsUnknown(): void
    {
        $this->expectException(UnknownRecord::class);
        $this->expectExceptionMessageIsOrContains('No data package has the key "acme/rpg-data-beta".');

        new InstalledDataPackages(installed: ['acme/rpg-data-alpha' => $this->package('acme/rpg-data-alpha', 'alpha')])->load('acme/rpg-data-beta');
    }

    public function testAPackageInstalledWithoutDirectoryIsRefused(): void
    {
        $packages = new InstalledDataPackages(installed: ['acme/rpg-data-meta' => null]);

        self::assertTrue($packages->has('acme/rpg-data-meta'));
        self::assertSame(['acme/rpg-data-meta'], $packages->names());

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('acme/rpg-data-meta: the data package is installed without a directory.');

        $packages->load('acme/rpg-data-meta');
    }

    public function testAManifestThatNamesAnotherPackageIsRefused(): void
    {
        $packages = new InstalledDataPackages(installed: ['acme/rpg-data-other' => $this->package('acme/rpg-data-alpha', 'alpha')]);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('name: the package installed as "acme/rpg-data-other" declares the name "acme/rpg-data-alpha".');

        $packages->systems();
    }

    public function testAChangedPackageIsRefusedByTheReader(): void
    {
        $alpha = $this->package('acme/rpg-data-alpha', 'alpha');
        file_put_contents($alpha.'/system.json', ' ', \FILE_APPEND);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('system.json: the document does not match the digest of the manifest.');

        new InstalledDataPackages(installed: ['acme/rpg-data-alpha' => $alpha])->load('acme/rpg-data-alpha');
    }

    public function testTheReaderGivenIsTheOneThatReads(): void
    {
        $alpha = $this->package('acme/rpg-data-alpha', 'alpha');

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('rpg-data.json: the manifest is longer than 10 bytes.');

        new InstalledDataPackages(new DataPackageReader(maxManifestBytes: 10), ['acme/rpg-data-alpha' => $alpha])->load('acme/rpg-data-alpha');
    }

    public function testTwoPackagesOfTheSameSystemAreRefused(): void
    {
        $packages = new InstalledDataPackages(installed: [
            'acme/rpg-data-alpha' => $this->package('acme/rpg-data-alpha', 'alpha'),
            'acme/rpg-data-alpha-copy' => $this->package('acme/rpg-data-alpha-copy', 'alpha'),
        ]);

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('Two systems have the key "alpha".');

        $packages->systems();
    }

    /**
     * Without a list of its own, the packages are those Composer installed with the type `tilttek-rpg-data`. The
     * installed packages are replaced in a process of its own, so that no other test sees them.
     */
    #[RunInSeparateProcess]
    public function testComposerGivesThePackagesOfTheDataType(): void
    {
        $root = InstalledVersions::getRootPackage();
        $version = ['pretty_version' => '1.0.0', 'version' => '1.0.0.0', 'reference' => null, 'aliases' => [], 'dev_requirement' => false];

        InstalledVersions::reload([
            'root' => $root,
            'versions' => [
                'acme/rpg-data-zeta' => [...$version, 'type' => InstalledDataPackages::TYPE, 'install_path' => $this->package('acme/rpg-data-zeta', 'zeta')],
                'acme/rpg-data-alpha' => [...$version, 'type' => InstalledDataPackages::TYPE, 'install_path' => $this->package('acme/rpg-data-alpha', 'alpha')],
                'acme/rpg-tools' => [...$version, 'type' => 'library', 'install_path' => $this->directory],
            ],
        ]);

        $packages = new InstalledDataPackages();

        self::assertSame(['acme/rpg-data-alpha', 'acme/rpg-data-zeta'], $packages->names());
        self::assertFalse($packages->has('acme/rpg-tools'));
        self::assertSame(['alpha', 'zeta'], array_map(static fn (GameSystem $system): string => $system->key, $packages->systems()->all()));
    }

    public function testTheDataTypeIsTheOneOfTheComposerPackages(): void
    {
        self::assertSame('tilttek-rpg-data', InstalledDataPackages::TYPE);
    }

    private function package(string $name, string $key): string
    {
        $directory = $this->directory.'/'.str_replace('/', '--', $name);
        mkdir($directory);
        new DataPackageWriter()->write($directory, $name, $this->system($key));

        return $directory;
    }

    private function system(string $key): GameSystem
    {
        $source = new Provenance('Open '.ucfirst($key), Licence::CcBy40, 'From Open '.ucfirst($key).', under CC BY 4.0.', 'A publisher', 'https://example.org/'.$key);

        return new GameSystem(
            $key,
            Translations::of(['en' => 'Open '.ucfirst($key)]),
            $source,
            '1.0',
            terms: [new Term('range.near', Translations::of(['en' => 'Near']), $source, 'range')],
        );
    }
}
