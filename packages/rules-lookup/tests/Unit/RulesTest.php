<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Exception\InvalidQuery;
use Tilttek\RulesLookup\Exception\InvalidRule;
use Tilttek\RulesLookup\Folding;
use Tilttek\RulesLookup\Message\ReindexRules;
use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleQuery;

#[CoversClass(Folding::class)]
#[CoversClass(RuleId::class)]
#[CoversClass(Rule::class)]
#[CoversClass(RuleQuery::class)]
#[CoversClass(ReindexRules::class)]
#[CoversClass(InvalidRule::class)]
#[CoversClass(InvalidQuery::class)]
final class RulesTest extends TestCase
{
    public function testFoldingIgnoresCaseAndAccents(): void
    {
        self::assertSame('epee a deux mains, ca œuvre', Folding::fold('Épée À DEUX MAINS, Ça Œuvre'));
        self::assertSame('ﬁ', 'ﬁ');
        self::assertSame('fi', Folding::fold('ﬁ'));
        self::assertSame(['epee', 'a', 'deux', 'mains', '1d20', 'ελληνικα'], Folding::words('Épée — à deux  mains; à 1d20, Ελληνικά!'));
        self::assertSame([], Folding::words(' — , '));
        self::assertSame(['un', 'deux'], Folding::words('un deux UN Deux'));
    }

    public function testFoldingDecomposesRemovesNonspacingMarksLowercasesAndRecomposes(): void
    {
        // Precomposed and decomposed accents fold alike, by NFKD and the removal of \p{Mn}.
        self::assertSame('epee', Folding::fold("E\u{0301}PE\u{0301}E"));
        self::assertSame('epee', Folding::fold('ÉPÉE'));
        self::assertSame('aveugle', Folding::fold('AvEuGlÉ'));
        self::assertSame('noel', Folding::fold('NOËL'));
        // Compatibility forms: a ligature, a superscript, a full-width letter.
        self::assertSame('fi2a', Folding::fold('ﬁ²Ａ'));
        // Uppercase letters without a decomposition are lowercased beyond ASCII.
        self::assertSame('œuvre ελληνικα', Folding::fold('ŒUVRE ΕΛΛΗΝΙΚΆ'));
        // Hangul is decomposed into its jamo, which are no marks, then recomposed.
        self::assertSame('한국어', Folding::fold('한국어'));
        // A spacing mark is kept, only nonspacing ones go.
        self::assertSame("\u{0915}\u{093E}", Folding::fold("\u{0915}\u{093E}"));
        self::assertSame('plain ascii, 1d20', Folding::fold('Plain ASCII, 1d20'));
        self::assertSame('', Folding::fold("\xC3("));
        self::assertSame('', Folding::fold("\xC3\xA9\xFF"));
    }

    public function testKeysAndLocalesAreChecked(): void
    {
        self::assertTrue(Folding::isKey('condition.blinded'));
        self::assertTrue(Folding::isKey(str_repeat('a', 96)));
        self::assertFalse(Folding::isKey(str_repeat('a', 97)));
        self::assertFalse(Folding::isKey('condition..blinded'));
        self::assertFalse(Folding::isKey('Condition'));
        self::assertFalse(Folding::isKey("a\n"));
        self::assertFalse(Folding::isKey('.a'));
        self::assertSame('fr_CA', Folding::locale('fr-ca'));
        self::assertSame('es_419', Folding::locale('es-419'));
        self::assertSame('fr', Folding::locale('FR'));
        self::assertNull(Folding::locale('french'));
        self::assertNull(Folding::locale("fr\n"));
        self::assertNull(Folding::locale('fr_CAN'));
        self::assertNull(Folding::locale('fr_CA_x'));
        self::assertNull(Folding::locale('fr-ca-x'));
    }

    public function testARuleIdIsWrittenAndReadBack(): void
    {
        $id = new RuleId('srd-5.2', 'condition.blinded', '5.2.1');

        self::assertSame('srd-5.2@5.2.1:condition.blinded', $id->toString());
        self::assertTrue(RuleId::fromString('srd-5.2@5.2.1:condition.blinded')->equals($id));
        self::assertSame('lanterns:range.near', RuleId::fromString('lanterns:range.near')->toString());
        self::assertNull(RuleId::fromString('lanterns:range.near')->version);
        self::assertSame('srd-5.2@5.2.1:skill.stealth', $id->sibling('skill.stealth')->toString());
        self::assertFalse($id->equals(new RuleId('srd-5.2', 'condition.blinded')));
        self::assertTrue(RuleId::isVersion(str_repeat('1', 32)));
        self::assertFalse(RuleId::isVersion(str_repeat('1', 33)));
        self::assertTrue(RuleId::isVersion('2.0.0-rc+1_a'));
        self::assertFalse(RuleId::isVersion('.1'));
        self::assertFalse(RuleId::isVersion("1\n"));
    }

    #[DataProvider('invalidIds')]
    public function testAnInvalidRuleIdIsRefused(string $id, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        RuleId::fromString($id);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'no key' => ['srd-5.2', '"srd-5.2" is not a rule identifier such as "srd-5.2@5.2.1:condition.blinded".'];
        yield 'two versions' => ['a@1@2:b', 'is not a rule identifier'];
        yield 'two keys' => ['a:b:c', 'is not a rule identifier'];
        yield 'an empty version' => ['a@:b', 'is not a rule identifier'];
        yield 'a trailing line break' => ["a:b\n", 'must each be 1 to 96'];
        yield 'a bad key' => ['a:B', 'The system "a" and the key "B" of a rule must each be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.'];
        yield 'a bad system' => ['A:b', 'The system "A" and the key "b"'];
        yield 'a bad version' => ['a@-1:b', 'The version "-1" of a rule must be 1 to 32 letters, digits, ".", "_", "+" or "-".'];
    }

    public function testARuleKeepsItsTextsAndReferences(): void
    {
        $source = new Provenance('Lanterns', Licence::Owned);
        $rule = new Rule(
            new RuleId('lanterns', 'condition.blinded', '1.0'),
            Translations::of(['en' => 'Blinded', 'fr' => 'Aveuglé']),
            $source,
            Translations::of(['en' => 'Cannot see.']),
            'condition',
            ['condition.prone', 'condition.prone', 'skill.notice'],
        );

        self::assertSame(['lanterns@1.0:condition.prone', 'lanterns@1.0:skill.notice'], array_map(static fn (RuleId $id): string => $id->toString(), $rule->references));
        self::assertSame('Aveuglé', $rule->title('fr_CA'));
        self::assertSame('Blinded', $rule->title());
        self::assertSame('Cannot see.', $rule->text('fr'));
        self::assertSame('', new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source)->text());
        self::assertNull(new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source)->category);
        self::assertCount(64, new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source, references: array_map(static fn (int $index): string => 'r'.$index, range(1, 64)))->references);
    }

    #[DataProvider('invalidRules')]
    public function testAnInvalidRuleIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make(new Provenance('Lanterns', Licence::Owned));
    }

    /**
     * @return iterable<string, array{callable(Provenance): Rule, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'no title' => [static fn (Provenance $source): Rule => new Rule(new RuleId('a', 'b'), new Translations(), $source), 'The rule "a:b" has no title.'];
        yield 'a bad category' => [static fn (Provenance $source): Rule => new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source, category: 'Combat'), 'The category "Combat" of the rule "a:b" is not a key.'];
        yield 'itself' => [static fn (Provenance $source): Rule => new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source, references: ['b']), 'The rule "a:b" refers to itself.'];
        yield 'a bad reference' => [static fn (Provenance $source): Rule => new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source, references: ['C']), 'the key "C"'];
        yield 'too many references' => [static fn (Provenance $source): Rule => new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), $source, references: array_map(static fn (int $index): string => 'r'.$index, range(1, 65))), 'The rule "a:b" refers to more than 64 rules.'];
    }

    public function testAQueryFoldsItsWords(): void
    {
        $query = new RuleQuery('  Épée, ÉPÉE à deux-mains ', 'fr-ca', 'srd-5.2', '5.2.1', 'weapon', 100, 10_000);

        self::assertSame(['epee', 'a', 'deux', 'mains'], $query->words);
        self::assertSame('epee a deux mains', $query->phrase());
        self::assertSame('fr_CA', $query->locale);
        self::assertSame(['srd-5.2', '5.2.1', 'weapon', 100, 10_000], [$query->system, $query->version, $query->category, $query->limit, $query->offset]);
        self::assertSame([], new RuleQuery('', category: 'condition')->words);
        self::assertSame(1, new RuleQuery('a', limit: 1)->limit);
        self::assertSame(['en', 20, 0], [new RuleQuery('a')->locale, new RuleQuery('a')->limit, new RuleQuery('a')->offset]);
        self::assertCount(16, new RuleQuery(implode(' ', range(1, 16)))->words);
        self::assertSame(200, mb_strlen(new RuleQuery(str_repeat('é', 200))->text));
    }

    /**
     * @param array{text: string, locale?: string, system?: string, category?: string, limit?: int, offset?: int} $arguments
     */
    #[DataProvider('invalidQueries')]
    public function testAnInvalidQueryIsRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidQuery::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RuleQuery(...$arguments);
    }

    /**
     * @return iterable<string, array{array{text: string, locale?: string, system?: string, category?: string, limit?: int, offset?: int}, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'too long' => [['text' => str_repeat('é', 201)], 'The text searched for must be UTF-8 and at most 200 characters long.'];
        yield 'not UTF-8' => [['text' => "\xff"], 'The text searched for must be UTF-8'];
        yield 'too many words' => [['text' => implode(' ', range(1, 17))], 'A search holds at most 16 words.'];
        yield 'nothing' => [['text' => ' , '], 'A search needs words or a category.'];
        yield 'a bad locale' => [['text' => 'a', 'locale' => 'french'], '"french" is not a locale such as "en", "fr_CA" or "es-419".'];
        yield 'a bad system' => [['text' => 'a', 'system' => 'SRD'], 'The system "SRD" is not a key.'];
        yield 'a bad category' => [['text' => 'a', 'category' => 'A B'], 'The category "A B" is not a key.'];
        yield 'no result' => [['text' => 'a', 'limit' => 0], 'A page holds 1 to 100 results and starts at 0 to 10000.'];
        yield 'too many results' => [['text' => 'a', 'limit' => 101], 'A page holds 1 to 100 results'];
        yield 'a negative page' => [['text' => 'a', 'offset' => -1], 'A page holds 1 to 100 results'];
        yield 'a page too far' => [['text' => 'a', 'offset' => 10_001], 'A page holds 1 to 100 results'];
    }

    public function testAReindexMessageCarriesIdentifiersOnly(): void
    {
        $message = new ReindexRules('srd-5.2', '5.2.1');

        self::assertSame(['system' => 'srd-5.2', 'version' => '5.2.1'], get_object_vars($message));
        self::assertNull(new ReindexRules('lanterns')->version);

        foreach ([['SRD', null], ['srd', '-1']] as [$system, $version]) {
            try {
                new ReindexRules($system, $version);
                self::fail('The message is made.');
            } catch (InvalidRule $invalidRule) {
                self::assertSame(\sprintf('"%s" is not the key of a system, or "%s" not a version.', $system, $version ?? ''), $invalidRule->getMessage());
            }
        }
    }
}
