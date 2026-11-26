<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\ChecklistItem;
use Tilttek\SessionPrep\Clue;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\NonPlayerCharacter;
use Tilttek\SessionPrep\Note;
use Tilttek\SessionPrep\Place;
use Tilttek\SessionPrep\Scene;
use Tilttek\SessionPrep\SceneStatus;
use Tilttek\SessionPrep\Secret;

#[CoversClass(Scene::class)]
#[CoversClass(Secret::class)]
#[CoversClass(Clue::class)]
#[CoversClass(NonPlayerCharacter::class)]
#[CoversClass(Place::class)]
#[CoversClass(Note::class)]
#[CoversClass(ChecklistItem::class)]
final class ValuesTest extends TestCase
{
    public function testASceneHoldsItsTextsReferencesAndStatus(): void
    {
        $scene = new Scene('docks', ' The docks at night ', "Fog.\r\nA bell.", ['mara', 'tobin'], ['pier'], SceneStatus::Played);

        self::assertSame('docks', $scene->key);
        self::assertSame('The docks at night', $scene->title);
        self::assertSame("Fog.\nA bell.", $scene->summary);
        self::assertSame(['mara', 'tobin'], $scene->characters);
        self::assertSame(['pier'], $scene->places);
        self::assertSame(SceneStatus::Played, $scene->status);
        self::assertSame(SceneStatus::Planned, new Scene('s', 'S')->status);
    }

    public function testASceneChangesItsStatusAndDropsAReference(): void
    {
        $scene = new Scene('docks', 'Docks', 'Fog', ['mara', 'tobin', 'ilse'], ['pier', 'tower']);

        $skipped = $scene->withStatus(SceneStatus::Skipped);
        self::assertSame([SceneStatus::Skipped, 'Docks', 'Fog', ['mara', 'tobin', 'ilse'], ['pier', 'tower']], [$skipped->status, $skipped->title, $skipped->summary, $skipped->characters, $skipped->places]);

        $without = $skipped->withoutCharacter('tobin');
        self::assertSame([['mara', 'ilse'], ['pier', 'tower'], SceneStatus::Skipped, 'Docks', 'Fog'], [$without->characters, $without->places, $without->status, $without->title, $without->summary]);

        $elsewhere = $skipped->withoutPlace('pier');
        self::assertSame([['mara', 'tobin', 'ilse'], ['tower'], SceneStatus::Skipped, 'Docks', 'Fog'], [$elsewhere->characters, $elsewhere->places, $elsewhere->status, $elsewhere->title, $elsewhere->summary]);
    }

    public function testASceneIsBounded(): void
    {
        new Scene('s', str_repeat('t', Scene::MAX_TITLE), str_repeat('s', Scene::MAX_SUMMARY), $this->keys(Scene::MAX_CHARACTERS), $this->keys(Scene::MAX_PLACES));

        foreach ([
            static fn (): Scene => new Scene('9', 'Title'),
            static fn (): Scene => new Scene('s', ''),
            static fn (): Scene => new Scene('s', str_repeat('t', Scene::MAX_TITLE + 1)),
            static fn (): Scene => new Scene('s', 'Title', str_repeat('s', Scene::MAX_SUMMARY + 1)),
            fn (): Scene => new Scene('s', 'Title', characters: $this->keys(Scene::MAX_CHARACTERS + 1)),
            fn (): Scene => new Scene('s', 'Title', places: $this->keys(Scene::MAX_PLACES + 1)),
            static fn (): Scene => new Scene('s', 'Title', characters: ['a', 'a']),
        ] as $index => $make) {
            $this->assertRefused($make, 'scene '.$index);
        }
    }

    public function testASecretHoldsItsClues(): void
    {
        $letter = new Clue('letter', ' A letter in the desk ');
        $secret = new Secret('heir', "The mayor is the heir.\nNobody knows.", [$letter, new Clue('ring', 'A signet ring', true)], true);

        self::assertSame('heir', $secret->key);
        self::assertSame("The mayor is the heir.\nNobody knows.", $secret->text);
        self::assertTrue($secret->revealed);
        self::assertFalse(new Secret('s', 'S')->revealed);
        self::assertSame('A letter in the desk', $secret->clue('letter')?->text);
        self::assertFalse($letter->found);
        self::assertTrue($secret->clue('ring')?->found);
        self::assertNull($secret->clue('none'));
    }

    public function testASecretIsRevealedAndItsCluesFound(): void
    {
        $secret = new Secret('heir', 'The heir', [new Clue('letter', 'A letter'), new Clue('ring', 'A ring')]);

        $revealed = $secret->withRevealed(true);
        self::assertTrue($revealed->revealed);
        self::assertSame($secret->clues, $revealed->clues);
        self::assertSame('The heir', $revealed->text);

        $found = $revealed->withClueFound('ring', true);
        self::assertSame([false, true], [$found->clues[0]->found, $found->clues[1]->found]);
        self::assertSame(['letter', 'ring'], [$found->clues[0]->key, $found->clues[1]->key]);
        self::assertTrue($found->revealed);
        self::assertSame('A ring', $found->clues[1]->text);
    }

    public function testFindingAMissingClueIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('No clue has the key map.'));

        new Secret('heir', 'The heir')->withClueFound('map', true);
    }

    public function testASecretTakesTheFactsOfAnother(): void
    {
        $secret = new Secret('heir', 'The heir, written anew', [new Clue('letter', 'A letter', true), new Clue('ring', 'A ring', true), new Clue('map', 'A map')], true);
        $before = new Secret('heir', 'The heir', [new Clue('letter', 'A letter', true), new Clue('ring', 'A ring')]);

        $after = $secret->withFactsOf($before);
        self::assertFalse($after->revealed);
        self::assertSame([true, false, false], array_map(static fn (Clue $clue): bool => $clue->found, $after->clues));
        self::assertSame('The heir, written anew', $after->text);

        $fresh = $secret->withFactsOf(null);
        self::assertFalse($fresh->revealed);
        self::assertSame([false, false, false], array_map(static fn (Clue $clue): bool => $clue->found, $fresh->clues));

        self::assertTrue(new Secret('heir', 'The heir')->withFactsOf($before->withRevealed(true))->revealed);
    }

    public function testASecretIsBounded(): void
    {
        $clues = array_map(static fn (string $key): Clue => new Clue($key, 'Clue'), $this->keys(Secret::MAX_CLUES));
        new Secret('s', str_repeat('t', Secret::MAX_TEXT), $clues);
        new Clue('c', str_repeat('c', Clue::MAX_TEXT));

        foreach ([
            static fn (): Secret => new Secret('9', 'Text'),
            static fn (): Secret => new Secret('s', ' '),
            static fn (): Secret => new Secret('s', str_repeat('t', Secret::MAX_TEXT + 1)),
            static fn (): Secret => new Secret('s', 'Text', [...$clues, new Clue('extra', 'Clue')]),
            static fn (): Secret => new Secret('s', 'Text', [new Clue('c', 'One'), new Clue('c', 'Two')]),
            static fn (): Clue => new Clue('9', 'Clue'),
            static fn (): Clue => new Clue('c', ''),
            static fn (): Clue => new Clue('c', "Two\nlines"),
            static fn (): Clue => new Clue('c', str_repeat('c', Clue::MAX_TEXT + 1)),
        ] as $index => $make) {
            $this->assertRefused($make, 'secret '.$index);
        }
    }

    public function testACharacterAndAPlaceHoldTheirTextsAndReference(): void
    {
        $mara = new NonPlayerCharacter('mara', ' Mara ', ' Wants the ledger ', "Scar.\r\nLimps.", 'wiki:mara');
        self::assertSame(['mara', 'Mara', 'Wants the ledger', "Scar.\nLimps.", 'wiki:mara'], [$mara->key, $mara->name, $mara->role, $mara->notes, $mara->reference]);
        $plain = new NonPlayerCharacter('tobin', 'Tobin');
        self::assertSame(['', '', null], [$plain->role, $plain->notes, $plain->reference]);

        $pier = new Place('pier', ' Pier ', "Wet.\nCold.", 'map:pier');
        self::assertSame(['pier', 'Pier', "Wet.\nCold.", 'map:pier'], [$pier->key, $pier->name, $pier->description, $pier->reference]);
        $plainPlace = new Place('tower', 'Tower');
        self::assertSame(['', null], [$plainPlace->description, $plainPlace->reference]);
    }

    public function testACharacterAndAPlaceAreBounded(): void
    {
        new NonPlayerCharacter('n', str_repeat('n', NonPlayerCharacter::MAX_NAME), str_repeat('r', NonPlayerCharacter::MAX_ROLE), str_repeat('o', NonPlayerCharacter::MAX_NOTES));
        new Place('p', str_repeat('n', Place::MAX_NAME), str_repeat('d', Place::MAX_DESCRIPTION));

        foreach ([
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('9', 'Name'),
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('n', ''),
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('n', str_repeat('n', NonPlayerCharacter::MAX_NAME + 1)),
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('n', 'Name', str_repeat('r', NonPlayerCharacter::MAX_ROLE + 1)),
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('n', 'Name', "Two\nlines"),
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('n', 'Name', notes: str_repeat('o', NonPlayerCharacter::MAX_NOTES + 1)),
            static fn (): NonPlayerCharacter => new NonPlayerCharacter('n', 'Name', reference: 'gm@example.org'),
            static fn (): Place => new Place('9', 'Name'),
            static fn (): Place => new Place('p', ''),
            static fn (): Place => new Place('p', str_repeat('n', Place::MAX_NAME + 1)),
            static fn (): Place => new Place('p', 'Name', str_repeat('d', Place::MAX_DESCRIPTION + 1)),
            static fn (): Place => new Place('p', 'Name', reference: ''),
        ] as $index => $make) {
            $this->assertRefused($make, 'character or place '.$index);
        }
    }

    public function testANoteAndAnItemHoldTheirTexts(): void
    {
        $note = new Note('rules', " Grapple rules\r\np. 195 ");
        self::assertSame(['rules', "Grapple rules\np. 195"], [$note->key, $note->text]);
        new Note('n', str_repeat('n', Note::MAX_TEXT));

        $item = new ChecklistItem('maps', ' Print the maps ', true, true);
        self::assertSame(['maps', 'Print the maps', true, true], [$item->key, $item->label, $item->required, $item->checked]);
        $plain = new ChecklistItem('music', 'Music');
        self::assertSame([false, false], [$plain->required, $plain->checked]);
        new ChecklistItem('i', str_repeat('l', ChecklistItem::MAX_LABEL));

        $unchecked = $item->withChecked(false);
        self::assertSame(['maps', 'Print the maps', true, false], [$unchecked->key, $unchecked->label, $unchecked->required, $unchecked->checked]);
        self::assertTrue($plain->withChecked(true)->checked);
    }

    public function testANoteAndAnItemAreBounded(): void
    {
        foreach ([
            static fn (): Note => new Note('9', 'Text'),
            static fn (): Note => new Note('n', "\n"),
            static fn (): Note => new Note('n', str_repeat('n', Note::MAX_TEXT + 1)),
            static fn (): ChecklistItem => new ChecklistItem('9', 'Label'),
            static fn (): ChecklistItem => new ChecklistItem('i', ''),
            static fn (): ChecklistItem => new ChecklistItem('i', str_repeat('l', ChecklistItem::MAX_LABEL + 1)),
        ] as $index => $make) {
            $this->assertRefused($make, 'note or item '.$index);
        }
    }

    /**
     * @return list<string>
     */
    private function keys(int $count): array
    {
        return array_map(static fn (int $index): string => 'k'.$index, range(1, $count));
    }

    /**
     * @param callable(): object $make
     */
    private function assertRefused(callable $make, string $case): void
    {
        try {
            $make();
            self::fail('Not refused: '.$case);
        } catch (InvalidPreparation $invalidPreparation) {
            self::assertNotSame('', $invalidPreparation->getMessage());
        }
    }
}
