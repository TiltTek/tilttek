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
use Tilttek\SessionPrep\Part;
use Tilttek\SessionPrep\Place;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\PreparationStatus;
use Tilttek\SessionPrep\Scene;
use Tilttek\SessionPrep\SceneStatus;
use Tilttek\SessionPrep\Secret;

#[CoversClass(Preparation::class)]
final class PreparationTest extends TestCase
{
    public function testAPreparationHoldsItsParts(): void
    {
        $preparation = $this->full();

        self::assertSame(['prep-1', 'campaign-7', 'member-1', 'The drowned bell', 'session-3', PreparationStatus::Ready, 4], [
            $preparation->id, $preparation->campaign, $preparation->gameMaster, $preparation->title, $preparation->session, $preparation->status, $preparation->version,
        ]);
        self::assertSame(['docks', 'tower'], $this->keys($preparation->scenes));
        self::assertSame(['heir', 'cult'], $this->keys($preparation->secrets));
        self::assertSame(['mara', 'tobin'], $this->keys($preparation->characters));
        self::assertSame(['pier', 'belfry'], $this->keys($preparation->places));
        self::assertSame(['rules'], $this->keys($preparation->notes));
        self::assertSame(['opening', 'maps', 'music'], $this->keys($preparation->checklist));

        self::assertSame('Docks', $preparation->scene('docks')?->title);
        self::assertSame('The cult', $preparation->secret('cult')?->text);
        self::assertSame('Tobin', $preparation->character('tobin')?->name);
        self::assertSame('Belfry', $preparation->place('belfry')?->name);
        self::assertSame('p. 195', $preparation->note('rules')?->text);
        self::assertSame('Maps', $preparation->item('maps')?->label);
        self::assertNull($preparation->scene('none'));
        self::assertNull($preparation->secret('none'));
        self::assertNull($preparation->character('none'));
        self::assertNull($preparation->place('none'));
        self::assertNull($preparation->note('none'));
        self::assertNull($preparation->item('none'));
    }

    public function testANewPreparationIsAnEmptyDraft(): void
    {
        $preparation = new Preparation('prep-1', 'campaign-7', 'member-1', ' First ');

        self::assertSame(['First', null, PreparationStatus::Draft, 0, [], [], [], [], [], []], [
            $preparation->title, $preparation->session, $preparation->status, $preparation->version, $preparation->scenes,
            $preparation->secrets, $preparation->characters, $preparation->places, $preparation->notes, $preparation->checklist,
        ]);
    }

    public function testItListsTheSecretsLeftAndTheRequiredItemsMissing(): void
    {
        $preparation = $this->full();

        self::assertSame(['cult'], $this->keys($preparation->unrevealedSecrets()));
        self::assertSame([0], array_keys($preparation->unrevealedSecrets()));
        self::assertSame(['opening'], $preparation->missingItems());
        self::assertSame([], $preparation->withItemChecked('opening', true)->missingItems());
        self::assertSame(['music', 'opening'], $preparation->withItem(new ChecklistItem('music', 'Music', true))->moveItem('opening')->missingItems());
    }

    public function testEachWitherKeepsEverythingElse(): void
    {
        $preparation = $this->full();

        $this->assertSameBut($preparation, $preparation->withTitle('Another'), 'title', 'Another');
        $this->assertSameBut($preparation, $preparation->withGameMaster('member-9'), 'gameMaster', 'member-9');
        $this->assertSameBut($preparation, $preparation->withSession(null), 'session', null);
        $this->assertSameBut($preparation, $preparation->withSession('session-4'), 'session', 'session-4');
        $this->assertSameBut($preparation, $preparation->withStatus(PreparationStatus::Closed), 'status', PreparationStatus::Closed);
        $this->assertSameBut($preparation, $preparation->withVersion(9), 'version', 9);
    }

    public function testScenesAreAddedReplacedRemovedAndMoved(): void
    {
        $preparation = $this->full()->withScene(new Scene('market', 'Market', characters: ['mara']));

        self::assertSame(['docks', 'tower', 'market'], $this->keys($preparation->scenes));
        self::assertSame('Docks again', $preparation->withScene(new Scene('docks', 'Docks again'))->scenes[0]->title);
        self::assertSame(['docks', 'market'], $this->keys($preparation->withoutScene('tower')->scenes));
        self::assertSame(['market', 'docks', 'tower'], $this->keys($preparation->moveScene('market', 'docks')->scenes));
        self::assertSame(['tower', 'market', 'docks'], $this->keys($preparation->moveScene('docks')->scenes));
        self::assertSame(SceneStatus::Skipped, $preparation->withSceneStatus('tower', SceneStatus::Skipped)->scene('tower')?->status);
        self::assertSame(['docks', 'tower', 'market'], $this->keys($preparation->withSceneStatus('tower', SceneStatus::Skipped)->scenes));
        self::assertSame($preparation->secrets, $preparation->withoutScene('tower')->secrets);
    }

    public function testSecretsAreAddedRemovedRevealedAndTheirCluesFound(): void
    {
        $preparation = $this->full();

        self::assertSame(['heir', 'cult', 'debt'], $this->keys($preparation->withSecret(new Secret('debt', 'The debt'))->secrets));
        self::assertSame(['cult'], $this->keys($preparation->withoutSecret('heir')->secrets));
        self::assertTrue($preparation->withSecretRevealed('cult', true)->secret('cult')?->revealed);
        self::assertFalse($preparation->withSecretRevealed('heir', false)->secret('heir')?->revealed);
        self::assertSame(['heir', 'cult'], $this->keys($preparation->withSecretRevealed('heir', false)->secrets));
        self::assertTrue($preparation->withClueFound('cult', 'robe', true)->secret('cult')?->clue('robe')?->found);
        self::assertSame(['heir', 'cult'], $this->keys($preparation->withClueFound('cult', 'robe', true)->secrets));
    }

    public function testRemovingACharacterOrAPlaceDropsItFromTheScenes(): void
    {
        $preparation = $this->full();

        $withoutMara = $preparation->withoutCharacter('mara');
        self::assertSame(['tobin'], $this->keys($withoutMara->characters));
        self::assertSame(['tobin'], $withoutMara->scene('docks')?->characters);
        self::assertSame(['pier'], $withoutMara->scene('docks')->places);

        $withoutPier = $preparation->withoutPlace('pier');
        self::assertSame(['belfry'], $this->keys($withoutPier->places));
        self::assertSame([], $withoutPier->scene('docks')?->places);
        self::assertSame(['mara', 'tobin'], $withoutPier->scene('docks')->characters);

        self::assertSame(['mara', 'tobin', 'ilse'], $this->keys($preparation->withCharacter(new NonPlayerCharacter('ilse', 'Ilse'))->characters));
        self::assertSame(['pier', 'belfry', 'gate'], $this->keys($preparation->withPlace(new Place('gate', 'Gate'))->places));
    }

    public function testNotesAndItemsAreAddedRemovedAndMoved(): void
    {
        $preparation = $this->full();

        self::assertSame(['rules', 'idea'], $this->keys($preparation->withNote(new Note('idea', 'An idea'))->notes));
        self::assertSame([], $preparation->withoutNote('rules')->notes);
        self::assertSame(['opening', 'maps', 'music', 'snacks'], $this->keys($preparation->withItem(new ChecklistItem('snacks', 'Snacks'))->checklist));
        self::assertSame(['opening', 'music'], $this->keys($preparation->withoutItem('maps')->checklist));
        self::assertSame(['music', 'opening', 'maps'], $this->keys($preparation->moveItem('music', 'opening')->checklist));
        self::assertSame(['maps', 'music', 'opening'], $this->keys($preparation->moveItem('opening')->checklist));
        self::assertTrue($preparation->withItemChecked('opening', true)->item('opening')?->checked);
        self::assertSame(['opening', 'maps', 'music'], $this->keys($preparation->withItemChecked('opening', true)->checklist));
    }

    public function testAPartMissingIsRefused(): void
    {
        $preparation = $this->full();

        foreach ([
            static fn (): Preparation => $preparation->withoutScene('none'),
            static fn (): Preparation => $preparation->moveScene('none'),
            static fn (): Preparation => $preparation->withSceneStatus('none', SceneStatus::Played),
            static fn (): Preparation => $preparation->withoutSecret('none'),
            static fn (): Preparation => $preparation->withSecretRevealed('none', true),
            static fn (): Preparation => $preparation->withClueFound('none', 'robe', true),
            static fn (): Preparation => $preparation->withClueFound('cult', 'none', true),
            static fn (): Preparation => $preparation->withoutCharacter('none'),
            static fn (): Preparation => $preparation->withoutPlace('none'),
            static fn (): Preparation => $preparation->withoutNote('none'),
            static fn (): Preparation => $preparation->withoutItem('none'),
            static fn (): Preparation => $preparation->moveItem('none'),
            static fn (): Preparation => $preparation->withItemChecked('none', true),
        ] as $index => $change) {
            try {
                $change();
                self::fail('Not refused: '.$index);
            } catch (InvalidPreparation $exception) {
                self::assertStringContainsString('has the key none', $exception->getMessage());
            }
        }
    }

    public function testASceneFeaturesOnlyTheCharactersAndPlacesHeld(): void
    {
        try {
            $this->full()->withScene(new Scene('market', 'Market', characters: ['ilse']));
            self::fail('A missing character is not refused.');
        } catch (InvalidPreparation $invalidPreparation) {
            self::assertSame('The scene market features the character ilse, which the preparation does not hold.', $invalidPreparation->getMessage());
        }

        $this->expectExceptionObject(InvalidPreparation::because('The scene market happens in the place gate, which the preparation does not hold.'));

        $this->full()->withScene(new Scene('market', 'Market', places: ['pier', 'gate']));
    }

    public function testItTakesTheFactsOfAnother(): void
    {
        $before = $this->full()->withSceneStatus('docks', SceneStatus::Played)->withItemChecked('maps', false)->withItemChecked('opening', true);
        $changed = $this->full()
            ->withSceneStatus('tower', SceneStatus::Skipped)
            ->withScene(new Scene('market', 'Market', status: SceneStatus::Played))
            ->withItem(new ChecklistItem('snacks', 'Snacks', checked: true))
            ->withSecret(new Secret('debt', 'The debt', revealed: true))
            ->withSecretRevealed('cult', true);

        $after = $changed->withFactsOf($before);
        self::assertSame([SceneStatus::Played, SceneStatus::Planned, SceneStatus::Planned], array_map(static fn (Scene $scene): SceneStatus => $scene->status, $after->scenes));
        self::assertSame([true, false, false], array_map(static fn (Secret $secret): bool => $secret->revealed, $after->secrets));
        self::assertSame([true, false, false, false], array_map(static fn (ChecklistItem $item): bool => $item->checked, $after->checklist));
        self::assertSame(['docks', 'tower', 'market'], $this->keys($after->scenes));
        self::assertSame($changed->characters, $after->characters);

        $fresh = $this->full()->withFactsOf(null);
        self::assertSame([SceneStatus::Planned, SceneStatus::Planned], array_map(static fn (Scene $scene): SceneStatus => $scene->status, $fresh->scenes));
        self::assertSame([false, false], array_map(static fn (Secret $secret): bool => $secret->revealed, $fresh->secrets));
        self::assertSame([false, false, false], array_map(static fn (ChecklistItem $item): bool => $item->checked, $fresh->checklist));
        self::assertSame(['session-3', PreparationStatus::Ready, 4, 'The drowned bell'], [$fresh->session, $fresh->status, $fresh->version, $fresh->title]);
    }

    public function testAPreparationIsBounded(): void
    {
        $many = static fn (int $count, callable $make): array => array_map($make, array_map(static fn (int $index): string => 'k'.$index, range(1, $count)));
        $scene = static fn (string $key): Scene => new Scene($key, 'Scene');
        $secret = static fn (string $key): Secret => new Secret($key, 'Secret');
        $character = static fn (string $key): NonPlayerCharacter => new NonPlayerCharacter($key, 'Name');
        $place = static fn (string $key): Place => new Place($key, 'Name');
        $note = static fn (string $key): Note => new Note($key, 'Note');
        $item = static fn (string $key): ChecklistItem => new ChecklistItem($key, 'Item');

        $largest = new Preparation(
            'p',
            'c',
            'm',
            str_repeat('t', Preparation::MAX_TITLE),
            null,
            $many(Preparation::MAX_SCENES, $scene),
            $many(Preparation::MAX_SECRETS, $secret),
            $many(Preparation::MAX_CHARACTERS, $character),
            $many(Preparation::MAX_PLACES, $place),
            $many(Preparation::MAX_NOTES, $note),
            $many(Preparation::MAX_CHECKLIST, $item),
        );
        self::assertCount(Preparation::MAX_CHARACTERS, $largest->characters);

        foreach ([
            static fn (): Preparation => new Preparation('9', 'c', 'm', 'Title'),
            static fn (): Preparation => new Preparation('p', '9', 'm', 'Title'),
            static fn (): Preparation => new Preparation('p', 'c', '9', 'Title'),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', '9'),
            static fn (): Preparation => new Preparation('p', 'c', 'm', ''),
            static fn (): Preparation => new Preparation('p', 'c', 'm', str_repeat('t', Preparation::MAX_TITLE + 1)),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', version: -1),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', scenes: $many(Preparation::MAX_SCENES + 1, $scene)),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', secrets: $many(Preparation::MAX_SECRETS + 1, $secret)),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', characters: $many(Preparation::MAX_CHARACTERS + 1, $character)),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', places: $many(Preparation::MAX_PLACES + 1, $place)),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', notes: $many(Preparation::MAX_NOTES + 1, $note)),
            static fn (): Preparation => new Preparation('p', 'c', 'm', 'Title', checklist: $many(Preparation::MAX_CHECKLIST + 1, $item)),
        ] as $index => $make) {
            try {
                $make();
                self::fail('Not refused: '.$index);
            } catch (InvalidPreparation $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        self::assertSame(0, new Preparation('p', 'c', 'm', 'Title', version: 0)->version);
    }

    public function testANegativeVersionIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The version of a preparation is 0 or more.'));

        new Preparation('p', 'c', 'm', 'Title', version: -1);
    }

    private function full(): Preparation
    {
        return new Preparation(
            'prep-1',
            'campaign-7',
            'member-1',
            'The drowned bell',
            'session-3',
            [new Scene('docks', 'Docks', characters: ['mara', 'tobin'], places: ['pier']), new Scene('tower', 'Tower', places: ['belfry'])],
            [new Secret('heir', 'The heir', revealed: true), new Secret('cult', 'The cult', [new Clue('robe', 'A robe')])],
            [new NonPlayerCharacter('mara', 'Mara'), new NonPlayerCharacter('tobin', 'Tobin')],
            [new Place('pier', 'Pier'), new Place('belfry', 'Belfry')],
            [new Note('rules', 'p. 195')],
            [new ChecklistItem('opening', 'Opening', true), new ChecklistItem('maps', 'Maps', true, true), new ChecklistItem('music', 'Music')],
            PreparationStatus::Ready,
            4,
        );
    }

    private function assertSameBut(Preparation $before, Preparation $after, string $property, mixed $value): void
    {
        foreach (['id', 'campaign', 'gameMaster', 'title', 'session', 'scenes', 'secrets', 'characters', 'places', 'notes', 'checklist', 'status', 'version'] as $name) {
            self::assertSame($name === $property ? $value : $before->{$name}, $after->{$name}, $name);
        }
    }

    /**
     * @param list<Part> $parts
     *
     * @return list<string>
     */
    private function keys(array $parts): array
    {
        return array_map(static fn (Part $part): string => $part->key, $parts);
    }
}
