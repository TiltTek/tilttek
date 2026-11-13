<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Constraint\Selector;
use Tilttek\WargameArmy\Constraint\UnitCount;
use Tilttek\WargameArmy\Contract\Constraint;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Exception\UnknownCatalogue;
use Tilttek\WargameArmy\Option;
use Tilttek\WargameArmy\OptionGroup;
use Tilttek\WargameArmy\Rule;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Lists;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Skirmish;
use Tilttek\WargameArmy\UnitEntry;
use Tilttek\WargameArmy\UnitSelection;
use Tilttek\WargameArmy\Validation;
use Tilttek\WargameArmy\Violation;

#[CoversClass(ArmyValidator::class)]
#[CoversClass(Validation::class)]
#[CoversClass(Violation::class)]
#[CoversClass(Rule::class)]
#[CoversClass(UnknownCatalogue::class)]
final class ArmyValidatorTest extends TestCase
{
    private ArmyValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ArmyValidator(new InMemoryCatalogueProvider(Skirmish::catalogue()));
    }

    public function testAListWithinTheRulesIsValid(): void
    {
        $list = Lists::valid();
        $validation = $this->validator->validate($list);

        self::assertSame([], $validation->messages());
        self::assertTrue($validation->isValid());
        self::assertSame($list, $validation->list);
        self::assertSame('ember-vale', $validation->catalogue->code);
        self::assertSame(427, $validation->costing->total);
    }

    public function testEveryBrokenRuleIsReported(): void
    {
        $validation = $this->validator->validate(Lists::broken());

        self::assertFalse($validation->isValid());
        self::assertSame([
            'The list was built with version 2025.9 of the catalogue "ember-vale"; the catalogue is at version 2026.1.',
            'The army has 3 detachments; the catalogue "ember-vale" takes at most 2.',
            'The army has 2 detachments "Vanguard"; the catalogue allows at most 1.',
            'Detachment 1 "Vanguard", unit 1 "Captain": 2 models; the unit takes 1.',
            'Detachment 1 "Vanguard", unit 1 "Captain": the option "Banner" is taken 3 times; at most 1 time.',
            'Detachment 1 "Vanguard", unit 1 "Captain": the unit has no option "axe".',
            'Detachment 1 "Vanguard", unit 1 "Captain": 0 selections in "Weapon"; the group takes at least 1.',
            'Detachment 1 "Vanguard", unit 1 "Captain": 4 selections in "Gear"; the group takes at most 2.',
            'Detachment 1 "Vanguard", unit 4 "dragon": the catalogue has no unit "dragon".',
            'Detachment 1 "Vanguard": 0 units of the category "troops"; the slot takes at least 1.',
            'Detachment 1 "Vanguard": 2 units of the category "monster"; the slot takes at most 1.',
            'Detachment 2 "Second", unit 1 "Scouts": 11 models; the unit takes 5 to 10.',
            'Detachment 2 "Second", unit 1 "Scouts": the option "Long rifle" is taken 2 times; at most 1 time.',
            'Detachment 2 "Second", unit 1 "Scouts": 2 selections in "Kit"; the group takes at most 1.',
            'Detachment 2 "Second": 0 units of the category "hero"; the slot takes at least 1.',
            'Detachment 3 "swamp": the catalogue has no detachment type "swamp".',
            'Detachment 3 "swamp", unit 1 "Guards": 4 models; the unit takes 5.',
            'The list costs 579 points; its limit is 300.',
            'At most 1 "Ogre" in the army; the army has 2.',
            'Units of the category "hero" cost at most 25 % of the points limit, 75 points; the list spends 105.',
        ], $validation->messages());
        self::assertSame([
            Rule::CatalogueVersion, Rule::DetachmentCount, Rule::DetachmentLimit, Rule::Models, Rule::OptionLimit,
            Rule::UnknownOption, Rule::OptionGroup, Rule::OptionGroup, Rule::UnknownUnit, Rule::Slot, Rule::Slot,
            Rule::Models, Rule::OptionLimit, Rule::OptionGroup, Rule::Slot, Rule::UnknownDetachment, Rule::Models,
            Rule::PointsLimit, Rule::Constraint, Rule::Constraint,
        ], array_map(static fn (Violation $violation): Rule => $violation->rule, $validation->violations));
    }

    public function testTheViolationsCarryTheirValues(): void
    {
        $violations = $this->validator->validate(Lists::broken())->violations;

        self::assertSame(['version' => '2025.9'], $violations[0]->values);
        self::assertSame(['count' => 3, 'max' => 2], $violations[1]->values);
        self::assertSame(['type' => 'vanguard', 'count' => 2, 'max' => 1], $violations[2]->values);
        self::assertSame(['detachment' => 1, 'unit' => 1, 'count' => 2, 'min' => 1, 'max' => 1], $violations[3]->values);
        self::assertSame(['detachment' => 1, 'unit' => 1, 'option' => 'banner', 'count' => 3, 'max' => 1], $violations[4]->values);
        self::assertSame(['detachment' => 1, 'unit' => 1, 'option' => 'axe'], $violations[5]->values);
        self::assertSame(['detachment' => 1, 'unit' => 1, 'group' => 'weapon', 'count' => 0, 'min' => 1], $violations[6]->values);
        self::assertSame(['detachment' => 1, 'unit' => 1, 'group' => 'gear', 'count' => 4, 'max' => 2], $violations[7]->values);
        self::assertSame(['detachment' => 1, 'unit' => 4], $violations[8]->values);
        self::assertSame(['detachment' => 1, 'category' => 'troops', 'count' => 0, 'min' => 1], $violations[9]->values);
        self::assertSame(['detachment' => 1, 'category' => 'monster', 'count' => 2, 'max' => 1], $violations[10]->values);
        self::assertSame(['detachment' => 3, 'type' => 'swamp'], $violations[15]->values);
        self::assertSame(['rule' => 'points-limit', 'message' => 'The list costs 579 points; its limit is 300.', 'values' => ['points' => 579, 'limit' => 300]], $violations[17]->toArray());
        self::assertCount(3, $this->validator->validate(Lists::broken())->ofRule(Rule::Slot));
        self::assertSame([], $this->validator->validate(Lists::broken())->ofRule(Rule::NotAllowed));
    }

    public function testTooFewDetachmentsAndAUnitAlienToItsDetachment(): void
    {
        $list = Lists::valid();
        $none = $list->withoutDetachment(1)->withoutDetachment(0);

        self::assertSame(['The army has 0 detachments; the catalogue "ember-vale" takes at least 1.', 'At least 1 units of the category "leader" in the army; the army has 0.'], $this->validator->validate($none)->messages());
        self::assertSame(['count' => 0, 'min' => 1], $this->validator->validate($none)->violations[0]->values);

        $raid = $list->withDetachmentAt(1, new Detachment('raid', 'Raiders', [new UnitSelection('ogre')]));
        $validation = $this->validator->validate($raid);

        self::assertSame(['Detachment 2 "Raiders", unit 1 "Ogre": the detachment type "Raid" takes no unit of its categories.', 'At most 1 "Ogre" in the army; the army has 2.'], $validation->messages());
        self::assertSame(['detachment' => 2, 'unit' => 1], $validation->violations[0]->values);
    }

    public function testAnUnknownUnitDoesNotStopTheUnitsAfterIt(): void
    {
        $list = new ArmyList('l', 'L', 'ember-vale', '2026.1', 500, [new Detachment('vanguard', null, [new UnitSelection('dragon'), new UnitSelection('guards', 4)])]);
        $validation = $this->validator->validate($list);

        self::assertCount(1, $validation->ofRule(Rule::UnknownUnit));
        self::assertCount(1, $validation->ofRule(Rule::Models));
    }

    public function testAnUnknownDetachmentTypeDoesNotStopTheUnitsOfTheDetachment(): void
    {
        $list = new ArmyList('l', 'L', 'ember-vale', '2026.1', 500, [new Detachment('swamp', null, [new UnitSelection('guards', 4), new UnitSelection('guards', 4)])]);
        $validation = $this->validator->validate($list);

        self::assertCount(1, $validation->ofRule(Rule::UnknownDetachment));
        self::assertCount(2, $validation->ofRule(Rule::Models));
    }

    public function testTheViolationsOfARuleAreAList(): void
    {
        $validation = $this->validator->validate(Lists::broken());

        foreach ([Rule::Constraint, Rule::UnknownUnit, Rule::Models] as $rule) {
            $violations = $validation->ofRule($rule);

            self::assertNotSame([], $violations);
            self::assertSame(range(0, \count($violations) - 1), array_keys($violations));
        }
    }

    public function testAnOptionOfDigitsIsLookedUpAsACode(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['t'])], [new DetachmentType('d', 'D', [new Slot('t')])]);
        $list = new ArmyList('l', 'L', 'c', '1', 5, [new Detachment('d', null, [new UnitSelection('u', 1, ['7' => 1])])]);

        self::assertSame(['Detachment 1 "D", unit 1 "U": the unit has no option "7".'], new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate($list)->messages());
    }

    public function testASlotWithoutLimitTakesAnyNumber(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['troops'], 1)], [new DetachmentType('d', 'D', [new Slot('troops')])]);
        $list = new ArmyList('l', 'L', 'c', '1', 1_000, [new Detachment('d', null, array_fill(0, 200, new UnitSelection('u')))]);

        self::assertTrue(new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate($list)->isValid());
        self::assertSame(['The list costs 200 points; its limit is 199.'], new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate($list->withPointsLimit(199))->messages());
        self::assertTrue(new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate($list->withPointsLimit(200))->isValid());
    }

    public function testTheBoundsOfModelsOptionsAndGroupsAreKept(): void
    {
        $unit = new UnitEntry('u', 'U', ['troops'], 10, 2, 4, 1, [new OptionGroup('g', 'G', [new Option('a', 'A', max: 2), new Option('b', 'B', max: 2)], 1, 3)]);
        $catalogue = new Catalogue('c', 'C', '1', [$unit], [new DetachmentType('d', 'D', [new Slot('troops', 1, 1)])]);
        $validator = new ArmyValidator(new InMemoryCatalogueProvider($catalogue));
        $list = static fn (UnitSelection $selection): ArmyList => new ArmyList('l', 'L', 'c', '1', 1_000, [new Detachment('d', null, [$selection])]);

        self::assertTrue($validator->validate($list(new UnitSelection('u', 2, ['a' => 1])))->isValid());
        self::assertTrue($validator->validate($list(new UnitSelection('u', 4, ['a' => 2, 'b' => 1])))->isValid());
        self::assertSame(['Detachment 1 "D", unit 1 "U": 1 model; the unit takes 2 to 4.'], $validator->validate($list(new UnitSelection('u', 1, ['a' => 1])))->messages());
        self::assertSame(['Detachment 1 "D", unit 1 "U": 5 models; the unit takes 2 to 4.'], $validator->validate($list(new UnitSelection('u', 5, ['a' => 1])))->messages());
        self::assertSame(['Detachment 1 "D", unit 1 "U": 4 selections in "G"; the group takes at most 3.'], $validator->validate($list(new UnitSelection('u', 2, ['a' => 2, 'b' => 2])))->messages());
        self::assertSame(['Detachment 1 "D", unit 1 "U": 0 selections in "G"; the group takes at least 1.'], $validator->validate($list(new UnitSelection('u', 2)))->messages());
        self::assertSame(['Detachment 1 "D", unit 1 "U": the option "A" is taken 3 times; at most 2 times.'], $validator->validate($list(new UnitSelection('u', 2, ['a' => 3])))->messages());
        $strict = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['troops'], optionGroups: [new OptionGroup('g', 'G', [new Option('a', 'A', max: 2)], 2, 2)])], [new DetachmentType('d', 'D', [new Slot('troops')])]);
        $strictValidator = new ArmyValidator(new InMemoryCatalogueProvider($strict));

        self::assertSame(['Detachment 1 "D", unit 1 "U": 1 selection in "G"; the group takes at least 2.'], $strictValidator->validate($list(new UnitSelection('u', 1, ['a' => 1])))->messages());
    }

    public function testASlotCountsAtItsBounds(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['troops'])], [new DetachmentType('d', 'D', [new Slot('troops', 2, 3)])]);
        $validator = new ArmyValidator(new InMemoryCatalogueProvider($catalogue));
        $list = static fn (int $units): ArmyList => new ArmyList('l', 'L', 'c', '1', 1_000, [new Detachment('d', null, array_fill(0, $units, new UnitSelection('u')))]);

        self::assertSame(['Detachment 1 "D": 1 unit of the category "troops"; the slot takes at least 2.'], $validator->validate($list(1))->messages());
        self::assertTrue($validator->validate($list(2))->isValid());
        self::assertTrue($validator->validate($list(3))->isValid());
        self::assertSame(['Detachment 1 "D": 4 units of the category "troops"; the slot takes at most 3.'], $validator->validate($list(4))->messages());
    }

    public function testADetachmentTypeTakenAtItsLimitIsAllowed(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['troops'])], [new DetachmentType('d', 'D', [new Slot('troops')], 2)], 1, 3);
        $validator = new ArmyValidator(new InMemoryCatalogueProvider($catalogue));
        $list = new ArmyList('l', 'L', 'c', '1', 1_000, [new Detachment('d'), new Detachment('d')]);

        self::assertTrue($validator->validate($list)->isValid());
        self::assertSame(['The army has 3 detachments "D"; the catalogue allows at most 2.'], $validator->validate($list->withDetachment(new Detachment('d')))->messages());
        self::assertSame(['The army has 4 detachments; the catalogue "c" takes at most 3.', 'The army has 4 detachments "D"; the catalogue allows at most 2.'], $validator->validate($list->withDetachment(new Detachment('d'))->withDetachment(new Detachment('d')))->messages());
    }

    public function testCodesOfDigitsAreCountedAsCodes(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('7', 'Seven', ['0'])], [new DetachmentType('0', 'Zero', [new Slot('0', 0, 1)], 1)], 1, 2, [new UnitCount(Selector::category('0'), max: 1)]);
        $list = new ArmyList('l', 'L', 'c', '1', 1_000, [new Detachment('0', null, [new UnitSelection('7')]), new Detachment('0', null, [new UnitSelection('7')])]);

        self::assertSame(['The army has 2 detachments "Zero"; the catalogue allows at most 1.', 'At most 1 units of the category "0" in the army; the army has 2.'], new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate($list)->messages());
    }

    public function testAListOfAnotherCatalogueIsReported(): void
    {
        $list = new ArmyList('l', 'L', 'other', '2026.1', 500, Lists::valid()->detachments);
        $validation = $this->validator->validateAgainst($list, Skirmish::catalogue());

        self::assertSame(['The list is built with the catalogue "other"; it is checked against "ember-vale".'], $validation->messages());
        self::assertSame(['catalogue' => 'other'], $validation->violations[0]->values);
    }

    public function testAnUnknownCatalogueIsAnError(): void
    {
        $this->expectException(UnknownCatalogue::class);
        $this->expectExceptionMessageIsOrContains('The catalogue "other" is not provided.');

        $this->validator->validate(new ArmyList('l', 'L', 'other', '1', 500));
    }

    public function testTheApplicationsConstraintsAreCheckedAfterTheCatalogues(): void
    {
        $constraint = new class implements Constraint {
            public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array
            {
                return $costing->total > 400 ? [new Violation(Rule::Constraint, 'Club nights are played at 400 points or less.', ['points' => $costing->total])] : [];
            }
        };

        $validator = new ArmyValidator(new InMemoryCatalogueProvider(Skirmish::catalogue()), $constraint, $constraint);

        self::assertSame(['Club nights are played at 400 points or less.', 'Club nights are played at 400 points or less.'], $validator->validate(Lists::valid())->messages());
        self::assertSame(['Detachment 1 "Wardens": 2 units of the category "monster"; the slot takes at most 1.', 'At most 1 "Ogre" in the army; the army has 2.', 'Club nights are played at 400 points or less.', 'Club nights are played at 400 points or less.'], $validator->validate(Lists::valid()->withDetachmentAt(1, new Detachment('raid', null, [new UnitSelection('guards', 5)]))->withDetachmentAt(0, Lists::valid()->detachments[0]->withUnitAt(2, new UnitSelection('ogre'))))->messages());
        self::assertSame([], new ArmyValidator(new InMemoryCatalogueProvider(Skirmish::catalogue()), $constraint)->validate(Lists::valid()->withoutDetachment(1))->messages());
    }
}
