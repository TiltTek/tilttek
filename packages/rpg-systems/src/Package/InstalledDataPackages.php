<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Package;

use Composer\InstalledVersions;
use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Exception\UnknownRecord;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Systems;

/**
 * The data packages Composer installed — the packages of the type `tilttek-rpg-data` — read with the strict reader:
 * requiring one is all an application does to know its system. Each package's manifest must carry the Composer name
 * it is installed under. Nothing is kept between two calls: the packages are found and read again each time.
 */
final readonly class InstalledDataPackages
{
    /** The Composer type of a data package. */
    public const string TYPE = 'tilttek-rpg-data';

    /**
     * @param ?array<string, ?string> $installed the data packages and their directories, by Composer name; those
     *                                           Composer installed when null
     */
    public function __construct(
        private DataPackageReader $reader = new DataPackageReader(),
        private ?array $installed = null,
    ) {
    }

    /**
     * The Composer names of the installed data packages, in alphabetical order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->installed());
        sort($names);

        return $names;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->installed());
    }

    /**
     * The system of one installed data package, by its Composer name.
     *
     * @throws UnknownRecord   when no data package of that name is installed
     * @throws InvalidDocument when the package cannot be read, or its manifest names another package
     */
    public function load(string $name): GameSystem
    {
        $installed = $this->installed();

        if (!\array_key_exists($name, $installed)) {
            throw UnknownRecord::of('data package', $name);
        }

        $directory = $installed[$name] ?? throw InvalidDocument::at($name, 'the data package is installed without a directory.');
        $manifest = $this->reader->manifest($directory);

        if ($manifest->name !== $name) {
            throw InvalidDocument::at('name', \sprintf('the package installed as "%s" declares the name "%s".', $name, $manifest->name));
        }

        return $this->reader->load($directory);
    }

    /**
     * The systems of every installed data package.
     *
     * @throws InvalidDocument when a package cannot be read, or its manifest names another package
     * @throws InvalidRecord   when two packages hold a system of the same key
     */
    public function systems(): Systems
    {
        return new Systems(...array_map($this->load(...), $this->names()));
    }

    /**
     * @return array<string, ?string>
     */
    private function installed(): array
    {
        if (null !== $this->installed) {
            return $this->installed;
        }

        $installed = [];

        foreach (InstalledVersions::getInstalledPackagesByType(self::TYPE) as $name) {
            $installed[$name] = InstalledVersions::getInstallPath($name);
        }

        return $installed;
    }
}
