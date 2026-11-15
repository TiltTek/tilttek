<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\TokenRefused;
use Tilttek\LuckTokens\GrantPolicy;
use Tilttek\LuckTokens\HolderScope;
use Tilttek\LuckTokens\Refresh;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokens\Tests\Unit\Fixture\Table;
use Tilttek\LuckTokens\TokenKind;

#[CoversClass(TableRules::class)]
#[CoversClass(HolderScope::class)]
#[CoversClass(Refresh::class)]
#[CoversClass(GrantPolicy::class)]
final class TableRulesTest extends TestCase
{
    public function testTheRulesAreReadAndWrittenBack(): void
    {
        $rules = TableRules::fromJson(Table::ref('table:1'), Table::RULES);

        self::assertSame(['inspiration', 'bennies', 'fate'], array_map(static fn (TokenKind $kind): string => $kind->key, $rules->kinds()));
        self::assertSame(['key' => 'bennies', 'name' => 'Bennies', 'cap' => 5, 'scope' => 'player', 'refresh' => 'each-session', 'refresh_to' => 3, 'carry_over' => 0, 'grants' => 'anyone', 'transferable' => true], $rules->kind('bennies')->toArray());
        self::assertSame(['key' => 'inspiration', 'name' => 'Inspiration', 'cap' => 1, 'scope' => 'character', 'refresh' => 'never', 'refresh_to' => 0, 'carry_over' => null, 'grants' => 'game-masters', 'transferable' => true], $rules->kind('inspiration')->toArray());
        self::assertTrue($rules->isGameMaster(Table::gm()));
        self::assertFalse($rules->isGameMaster(Table::ref('member:ana')));
        self::assertSame(['member:gm'], $rules->toArray()['game_masters']);
        self::assertEquals($rules, TableRules::fromArray(Table::ref('table:1'), $rules->toArray()));
        self::assertEquals($rules, TableRules::fromJson(Table::ref('table:1'), json_encode($rules->toArray(), \JSON_THROW_ON_ERROR)));

        $empty = TableRules::fromArray(Table::ref('table:2'), []);
        self::assertSame(['kinds' => [], 'game_masters' => []], $empty->toArray());
        self::assertFalse($empty->isGameMaster(Table::gm()));
    }

    public function testAKindNotHandedOutIsRefused(): void
    {
        $this->expectException(TokenRefused::class);
        $this->expectExceptionMessageIsOrContains('The table hands out no token "luck".');

        TableRules::fromJson(Table::ref('table:1'), Table::RULES)->kind('luck');
    }

    public function testTheLimitsAreTaken(): void
    {
        $kinds = array_map(static fn (int $index): array => ['key' => 'k'.$index, 'name' => 'K', 'cap' => 1], range(1, 16));
        $masters = array_map(static fn (int $index): string => 'member:'.$index, range(1, 16));

        $rules = TableRules::fromArray(Table::ref('table:1'), ['kinds' => $kinds, 'game_masters' => $masters]);

        self::assertCount(16, $rules->kinds());
        self::assertTrue($rules->isGameMaster(Table::ref('member:16')));

        $padded = str_pad('{}', TableRules::MAX_JSON_BYTES, ' ');
        self::assertSame([], TableRules::fromJson(Table::ref('table:1'), $padded)->kinds());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        $kind = '{"key": "luck", "name": "Luck", "cap": 3';
        $sixteen = implode(', ', array_map(static fn (int $index): string => '{"key": "k'.$index.'", "name": "K", "cap": 1}', range(1, 17)));
        $masters = implode(', ', array_map(static fn (int $index): string => '"member:'.$index.'"', range(1, 17)));

        yield 'not JSON' => ['{', 'The rules are not JSON of 6 levels at most: Syntax error'];
        yield 'too deep' => ['{"kinds": [{"key": [[[[1]]]]}]}', 'The rules are not JSON of 6 levels at most: Maximum stack depth exceeded'];
        yield 'a text' => ['"rules"', 'The rules of a table are an object of fields.'];
        yield 'an unknown field' => ['{"tokens": []}', 'The field "tokens" of the rules is not one of kinds, game_masters.'];
        yield 'kinds not a list' => ['{"kinds": {"luck": {}}}', 'The kinds of tokens are a list.'];
        yield 'kinds a text' => ['{"kinds": "luck"}', 'The kinds of tokens are a list.'];
        yield 'masters not a list' => ['{"game_masters": {"a": "member:1"}}', 'The game masters are a list of references.'];
        yield 'masters a text' => ['{"game_masters": "member:1"}', 'The game masters are a list of references.'];
        yield 'a master not a text' => ['{"game_masters": [1]}', 'A game master is a reference written "kind:identifier".'];
        yield 'a master not a reference' => ['{"game_masters": ["gm"]}', '"gm" is not a resource reference of the form "kind:identifier".'];
        yield 'too many kinds' => ['{"kinds": ['.$sixteen.']}', 'A table hands out 16 kinds of tokens at most.'];
        yield 'too many masters' => ['{"game_masters": ['.$masters.']}', 'A table has 16 game masters at most.'];
        yield 'two kinds of a key' => ['{"kinds": ['.$kind.'}, '.$kind.'}]}', 'Two kinds of tokens have the key "luck".'];
        yield 'a kind not an object' => ['{"kinds": ["luck"]}', 'A kind of token is an object of fields.'];
        yield 'a kind of unknown field' => ['{"kinds": [{"key": "luck", "colour": "red"}]}', 'The field "colour" of a kind of token is not one of key, name, cap, scope, refresh, refresh_to, carry_over, grants, transferable.'];
        yield 'no key' => ['{"kinds": [{"name": "Luck"}]}', 'The field "key" of a kind of token is a text.'];
        yield 'no name' => ['{"kinds": [{"key": "luck", "cap": 3}]}', 'The field "name" of the token "luck" is a text.'];
        yield 'no cap' => ['{"kinds": [{"key": "luck", "name": "Luck"}]}', 'The field "cap" of the token "luck" is an integer.'];
        yield 'a cap as text' => ['{"kinds": [{"key": "luck", "name": "Luck", "cap": "3"}]}', 'The field "cap" of the token "luck" is an integer.'];
        yield 'a refresh amount as text' => ['{"kinds": ['.$kind.', "refresh_to": "1"}]}', 'The field "refresh_to" of the token "luck" is an integer.'];
        yield 'a carry-over as text' => ['{"kinds": ['.$kind.', "carry_over": "all"}]}', 'The field "carry_over" of the token "luck" is an integer, or null.'];
        yield 'transferable as text' => ['{"kinds": ['.$kind.', "transferable": "yes"}]}', 'The field "transferable" of the token "luck" is true or false.'];
        yield 'a scope not known' => ['{"kinds": ['.$kind.', "scope": "party"}]}', 'The field "scope" of the token "luck" is one of character, player.'];
        yield 'a scope not a text' => ['{"kinds": ['.$kind.', "scope": 1}]}', 'The field "scope" of the token "luck" is one of character, player.'];
        yield 'a refresh not known' => ['{"kinds": ['.$kind.', "refresh": "daily"}]}', 'The field "refresh" of the token "luck" is one of never, each-session.'];
        yield 'a grant policy not known' => ['{"kinds": ['.$kind.', "grants": "players"}]}', 'The field "grants" of the token "luck" is one of game-masters, anyone.'];
        yield 'a kind refused' => ['{"kinds": ['.$kind.', "carry_over": 4}]}', 'A holder carries 0 to 3 "luck" to the next session, or all of them; not 4.'];
        yield 'a long key quoted short' => ['{"kinds": [{"key": "'.str_repeat('k', 40).'"}]}', 'The field "name" of the token "'.str_repeat('k', 32).'" is a text.'];
    }

    #[DataProvider('refusedJson')]
    public function testWhatAreNotRulesIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        TableRules::fromJson(Table::ref('table:1'), $json);
    }

    public function testTheTextIsBounded(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A text of rules holds 65536 bytes at most.');

        TableRules::fromJson(Table::ref('table:1'), str_pad('{}', TableRules::MAX_JSON_BYTES + 1, ' '));
    }

    public function testTheConstructorChecksItsLists(): void
    {
        $kinds = array_map(static fn (int $index): TokenKind => new TokenKind('k'.$index, 'K', 1), range(1, 17));

        $this->expectExceptionMessageIsOrContains('A table hands out 16 kinds of tokens at most.');
        new TableRules(Table::ref('table:1'), $kinds);
    }

    public function testTheConstructorChecksItsGameMasters(): void
    {
        $masters = array_map(static fn (int $index): ResourceReference => Table::ref('member:'.$index), range(1, 17));

        $this->expectExceptionMessageIsOrContains('A table has 16 game masters at most.');
        new TableRules(Table::ref('table:1'), [], $masters);
    }
}
