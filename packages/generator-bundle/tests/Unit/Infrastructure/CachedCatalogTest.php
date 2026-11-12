<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\ResourceInterface;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\DiceRoller;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Limits;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\FileChanges;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory;
use Tilttek\GeneratorBundle\Tests\Fixture\Files;

#[CoversClass(CachedCatalog::class)]
#[CoversClass(FileChanges::class)]
#[CoversClass(GeneratorFactory::class)]
final class CachedCatalogTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = Files::directory();
    }

    #[Override]
    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    public function testTheCatalogIsReadOnceAndKeptInTheCache(): void
    {
        $catalog = new CachedCatalog([$this->directory.'/generators'], $this->directory.'/cache/catalog.php', false);

        self::assertSame(['name.syllable'], array_keys($catalog->catalog()->lists()));
        self::assertFileExists($this->directory.'/cache/catalog.php');

        file_put_contents($this->directory.'/generators/more.yaml', "lists:\n  more: [x]\n");
        self::assertSame(['name.syllable'], array_keys($catalog->catalog()->lists()));
        self::assertSame(['more', 'name.syllable'], array_keys($catalog->fresh()->lists()));
    }

    public function testInDebugTheCacheFollowsTheFiles(): void
    {
        $catalog = new CachedCatalog([$this->directory.'/generators'], $this->directory.'/cache/catalog.php', true);
        self::assertSame(['name.syllable'], array_keys($catalog->catalog()->lists()));

        file_put_contents($this->directory.'/generators/more.yml', "lists:\n  more: [x]\n");
        touch($this->directory.'/generators/more.yml', time() + 10);
        touch($this->directory.'/generators', time() + 10);
        self::assertSame(['more', 'name.syllable'], array_keys($catalog->catalog()->lists()));

        $file = new CachedCatalog([$this->directory.'/generators/names.yaml'], $this->directory.'/cache/file.php', true);
        self::assertSame(['name.syllable'], array_keys($file->catalog()->lists()));
        file_put_contents($this->directory.'/generators/names.yaml', "lists:\n  other: [x]\n");
        touch($this->directory.'/generators/names.yaml', time() + 20);
        self::assertSame(['other'], array_keys($file->catalog()->lists()));
    }

    public function testInDebugAFileChangedInTheSecondOfTheCacheIsSeen(): void
    {
        touch($this->directory.'/generators/names.yaml', time() - 10);
        touch($this->directory.'/generators', time() - 10);
        $catalog = new CachedCatalog([$this->directory.'/generators'], $this->directory.'/cache/catalog.php', true);

        self::assertSame(['name.syllable'], array_keys($catalog->catalog()->lists()));
        $written = (int) filemtime($this->directory.'/cache/catalog.php');
        self::assertSame(['name.syllable'], array_keys($catalog->catalog()->lists()));

        // The cache keeps the second it was written in: a checker that remembers its answer by second would miss this.
        file_put_contents($this->directory.'/generators/more.yaml', "lists:\n  more: [x]\n");
        touch($this->directory.'/cache/catalog.php', $written);

        self::assertSame(['more', 'name.syllable'], array_keys($catalog->catalog()->lists()));
    }

    public function testTheFilesOfTheCatalogAreAskedEachTime(): void
    {
        $changes = new FileChanges();
        $file = new FileResource(__FILE__);
        $other = new class implements ResourceInterface {
            #[Override]
            public function __toString(): string
            {
                return 'other';
            }
        };

        self::assertTrue($changes->supports($file));
        self::assertFalse($changes->supports($other));
        self::assertFalse($changes->isFresh($other, time() + 10));
        self::assertTrue($changes->isFresh($file, time() + 10));
        self::assertFalse($changes->isFresh($file, (int) filemtime(__FILE__) - 1));
        self::assertTrue($changes->isFresh($file, time() + 10));
    }

    public function testACacheThatIsNotACatalogGivesAnEmptyOne(): void
    {
        mkdir($this->directory.'/cache');
        file_put_contents($this->directory.'/cache/catalog.php', "<?php return 'nothing';\n");

        self::assertSame([], new CachedCatalog([$this->directory.'/generators'], $this->directory.'/cache/catalog.php', false)->catalog()->lists());
    }

    public function testTheFirstDirectoryReceivesNewGenerators(): void
    {
        self::assertSame($this->directory.'/generators', new CachedCatalog([$this->directory.'/generators/names.yaml', $this->directory.'/generators'], 'x', false)->directory());
        self::assertNull(new CachedCatalog([$this->directory.'/generators/names.yaml'], 'x', false)->directory());
    }

    public function testTheFactoryBuildsTheGeneratorOfTheCatalog(): void
    {
        $text = bin2hex(random_bytes(8));
        file_put_contents($this->directory.'/generators/once.yaml', "generator: once\ntemplates: '".$text."'\n");
        $catalog = new CachedCatalog([$this->directory.'/generators'], $this->directory.'/cache/catalog.php', false);
        $factory = new GeneratorFactory($catalog, new Limits(), 'fr', new Grammars(), null, $this->directory.'/twig');

        self::assertSame($catalog, $factory->catalog());
        self::assertSame(['name.short', 'inn', 'broken', 'once'], array_keys($factory->generator()->catalog()->generators()));
        self::assertSame($text, $factory->generator()->draw('once')->first());
        self::assertDirectoryExists($this->directory.'/twig');
        self::assertIsString(new GeneratorFactory($catalog, new Limits(), 'en', new Grammars(), new DiceRoller())->build($catalog->fresh())->draw('name.short')->first());
    }

    public function testTheFactoryRollsTheDiceItIsGiven(): void
    {
        file_put_contents($this->directory.'/generators/coin.yaml', "generator: coin\ntemplates: \"{{ roll('2d{coin}') }}\"\n");
        $catalog = new CachedCatalog([$this->directory.'/generators'], $this->directory.'/cache/catalog.php', false);
        $dice = new DiceRoller(customDice: new CustomDice(new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 1))));

        self::assertSame('2', new GeneratorFactory($catalog, new Limits(), 'en', new Grammars(), $dice)->generator()->draw('coin')->first());
    }
}
