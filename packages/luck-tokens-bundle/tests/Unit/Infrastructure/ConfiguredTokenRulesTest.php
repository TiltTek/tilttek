<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Infrastructure\Rules\ConfiguredTokenRules;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Table;

#[CoversClass(ConfiguredTokenRules::class)]
final class ConfiguredTokenRulesTest extends TestCase
{
    public function testTheRulesOfEachTableAreReadFromTheConfiguration(): void
    {
        $rules = new ConfiguredTokenRules([
            'table:1' => ['game_masters' => ['member:gm'], 'kinds' => [['key' => 'inspiration', 'name' => 'Inspiration', 'cap' => 1, 'transferable' => true]]],
            'table:with:colons' => ['kinds' => []],
        ]);

        self::assertEquals(TableRules::fromArray(Table::ref('table:1'), ['game_masters' => ['member:gm'], 'kinds' => [['key' => 'inspiration', 'name' => 'Inspiration', 'cap' => 1, 'transferable' => true]]]), $rules->rulesFor(Table::ref('table:1')));
        self::assertEquals(TableRules::fromArray(Table::ref('table:with:colons'), []), $rules->rulesFor(Table::ref('table:with:colons')));
        self::assertNull($rules->rulesFor(Table::ref('table:2')));
        self::assertNull(new ConfiguredTokenRules([])->rulesFor(Table::ref('table:1')));
    }

    public function testAReferenceThatIsNoneIsRefused(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('"twelve" is not a resource reference of the form "kind:identifier".');

        new ConfiguredTokenRules(['twelve' => []]);
    }

    public function testRulesTheLibraryRefusesAreRefused(): void
    {
        $this->expectException(InvalidTokenRule::class);

        new ConfiguredTokenRules(['table:1' => ['kinds' => [['key' => 'luck', 'name' => 'Luck', 'cap' => 0]]]]);
    }
}
