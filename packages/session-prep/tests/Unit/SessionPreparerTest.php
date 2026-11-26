<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\ChecklistItem;
use Tilttek\SessionPrep\Clue;
use Tilttek\SessionPrep\Event\ClueMarked;
use Tilttek\SessionPrep\Event\ItemMarked;
use Tilttek\SessionPrep\Event\PreparationCarriedOver;
use Tilttek\SessionPrep\Event\PreparationChanged;
use Tilttek\SessionPrep\Event\PreparationClosed;
use Tilttek\SessionPrep\Event\PreparationCreated;
use Tilttek\SessionPrep\Event\PreparationDeleted;
use Tilttek\SessionPrep\Event\PreparationReady;
use Tilttek\SessionPrep\Event\PreparationReopened;
use Tilttek\SessionPrep\Event\SceneMarked;
use Tilttek\SessionPrep\Event\SecretMarked;
use Tilttek\SessionPrep\Event\SessionLinked;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Exception\PreparationConflict;
use Tilttek\SessionPrep\Exception\PreparationNotFound;
use Tilttek\SessionPrep\Exception\PreparationRefused;
use Tilttek\SessionPrep\NonPlayerCharacter;
use Tilttek\SessionPrep\Part;
use Tilttek\SessionPrep\Place;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\PreparationQuery;
use Tilttek\SessionPrep\PreparationStatus;
use Tilttek\SessionPrep\Scene;
use Tilttek\SessionPrep\SceneStatus;
use Tilttek\SessionPrep\Secret;
use Tilttek\SessionPrep\SessionPreparer;
use Tilttek\SessionPrep\Store\InMemoryPreparationStore;
use Tilttek\SessionPrep\Tests\Unit\Fixture\Observed;

#[CoversClass(SessionPreparer::class)]
#[CoversClass(PreparationNotFound::class)]
#[CoversClass(PreparationRefused::class)]
#[CoversClass(PreparationCreated::class)]
#[CoversClass(PreparationChanged::class)]
#[CoversClass(SessionLinked::class)]
#[CoversClass(SceneMarked::class)]
#[CoversClass(SecretMarked::class)]
#[CoversClass(ClueMarked::class)]
#[CoversClass(ItemMarked::class)]
#[CoversClass(PreparationReady::class)]
#[CoversClass(PreparationReopened::class)]
#[CoversClass(PreparationClosed::class)]
#[CoversClass(PreparationCarriedOver::class)]
#[CoversClass(PreparationDeleted::class)]
final class SessionPreparerTest extends TestCase
{
    private const string GM = 'member-1';

    private InMemoryPreparationStore $store;

    private Observed $observed;

    private SessionPreparer $preparer;

    protected function setUp(): void
    {
        $this->store = new InMemoryPreparationStore();
        $this->observed = new Observed();
        $this->preparer = new SessionPreparer($this->store, $this->observed);
    }

    public function testAPreparationIsCreatedWithEveryPartAtItsStart(): void
    {
        $kept = $this->preparer->create($this->draft()->withSceneStatus('docks', SceneStatus::Played)->withSecretRevealed('heir', true)->withClueFound('heir', 'letter', true)->withItemChecked('opening', true));

        self::assertSame(1, $kept->version);
        self::assertSame($kept, $this->store->preparation('prep-1'));
        self::assertSame($kept, $this->preparer->preparation('prep-1'));
        self::assertSame(SceneStatus::Planned, $kept->scene('docks')?->status);
        self::assertFalse($kept->secret('heir')?->revealed);
        self::assertFalse($kept->secret('heir')->clue('letter')?->found);
        self::assertFalse($kept->item('opening')?->checked);
        self::assertSame('session-3', $kept->session);
        self::assertEquals([new PreparationCreated('prep-1')], $this->observed->take());
    }

    public function testANewPreparationIsADraftAtVersion0(): void
    {
        foreach ([$this->draft()->withVersion(1), $this->draft()->withStatus(PreparationStatus::Ready)] as $preparation) {
            try {
                $this->preparer->create($preparation);
                self::fail('Not refused.');
            } catch (PreparationRefused $exception) {
                self::assertSame('A new preparation is a draft at version 0.', $exception->getMessage());
            }
        }

        self::assertNull($this->store->preparation('prep-1'));
        self::assertSame([], $this->observed->take());
    }

    public function testATakenIdentifierIsRefused(): void
    {
        $this->preparer->create($this->draft());

        $this->expectExceptionObject(PreparationRefused::because('A preparation has the identifier prep-1.'));

        $this->preparer->create($this->draft());
    }

    public function testAMissingPreparationIsNotFound(): void
    {
        $this->expectExceptionObject(PreparationNotFound::withId('prep-9'));

        $this->preparer->preparation('prep-9');
    }

    public function testAnIdentifierIsCheckedBeforeItIsRead(): void
    {
        $this->expectException(InvalidPreparation::class);

        $this->preparer->preparation('');
    }

    public function testTheGameMasterChangesThePartsAndKeepsTheFacts(): void
    {
        $this->create();
        $this->preparer->markSecret('prep-1', 'heir', true, self::GM);
        $this->preparer->markItem('prep-1', 'opening', true, self::GM);
        $this->preparer->markItem('prep-1', 'secrets', true, self::GM);

        $kept = $this->preparer->ready('prep-1', self::GM);
        $this->observed->take();

        $changed = $this->preparer->change(
            $kept->withTitle('Renamed')
                ->withSession('session-9')
                ->withStatus(PreparationStatus::Draft)
                ->withSecretRevealed('heir', false)
                ->withSecret(new Secret('debt', 'The debt', revealed: true))
                ->withGameMaster('member-2'),
            self::GM,
        );

        self::assertSame(['Renamed', 'session-3', PreparationStatus::Ready, 'member-2', 6], [$changed->title, $changed->session, $changed->status, $changed->gameMaster, $changed->version]);
        self::assertTrue($changed->secret('heir')?->revealed);
        self::assertTrue($changed->item('opening')?->checked);
        self::assertFalse($changed->secret('debt')?->revealed);
        self::assertSame($changed, $this->store->preparation('prep-1'));
        self::assertEquals([new PreparationChanged('prep-1')], $this->observed->take());
    }

    public function testAChangeStaysInItsCampaign(): void
    {
        $this->create();

        $this->expectExceptionObject(PreparationRefused::because('A preparation stays in its campaign.'));

        $this->preparer->change(new Preparation('prep-1', 'campaign-8', self::GM, 'Title', version: 1), self::GM);
    }

    public function testAChangeOnAStaleVersionIsAConflict(): void
    {
        $kept = $this->create();
        $this->preparer->change($kept->withTitle('One'), self::GM);

        $this->expectException(PreparationConflict::class);

        $this->preparer->change($kept->withTitle('Two'), self::GM);
    }

    public function testOnlyTheGameMasterChangesAPreparation(): void
    {
        $kept = $this->create();
        $this->observed->take();

        foreach ([
            fn (): mixed => $this->preparer->change($kept->withTitle('Mine'), 'member-2'),
            fn (): mixed => $this->preparer->link('prep-1', 'session-4', 'member-2'),
            fn (): mixed => $this->preparer->markScene('prep-1', 'docks', SceneStatus::Played, 'member-2'),
            fn (): mixed => $this->preparer->markSecret('prep-1', 'heir', true, 'member-2'),
            fn (): mixed => $this->preparer->markClue('prep-1', 'heir', 'letter', true, 'member-2'),
            fn (): mixed => $this->preparer->markItem('prep-1', 'opening', true, 'member-2'),
            fn (): mixed => $this->preparer->ready('prep-1', 'member-2'),
            fn (): mixed => $this->preparer->reopen('prep-1', 'member-2'),
            fn (): mixed => $this->preparer->close('prep-1', 'member-2'),
            fn (): mixed => $this->preparer->carryOver('prep-1', 'prep-1', 'member-2'),
            function (): void {
                $this->preparer->delete('prep-1', 'member-2');
            },
        ] as $index => $call) {
            try {
                $call();
                self::fail('Not refused: '.$index);
            } catch (PreparationRefused $exception) {
                self::assertSame('Only the game master of a preparation changes it.', $exception->getMessage(), (string) $index);
            }
        }

        self::assertSame($kept, $this->store->preparation('prep-1'));
        self::assertSame([], $this->observed->take());
    }

    public function testAClosedPreparationChangesOnlyOnceReopened(): void
    {
        $kept = $this->create();
        $closed = $this->preparer->close('prep-1', self::GM);
        $this->preparer->create(new Preparation('prep-2', 'campaign-7', self::GM, 'Next'));

        foreach ([
            fn (): mixed => $this->preparer->change($closed->withTitle('Late'), self::GM),
            fn (): mixed => $this->preparer->link('prep-1', 'session-4', self::GM),
            fn (): mixed => $this->preparer->markScene('prep-1', 'docks', SceneStatus::Played, self::GM),
            fn (): mixed => $this->preparer->markSecret('prep-1', 'heir', true, self::GM),
            fn (): mixed => $this->preparer->markClue('prep-1', 'heir', 'letter', true, self::GM),
            fn (): mixed => $this->preparer->markItem('prep-1', 'opening', true, self::GM),
            fn (): mixed => $this->preparer->ready('prep-1', self::GM),
            fn (): mixed => $this->preparer->carryOver('prep-2', 'prep-1', self::GM),
        ] as $index => $call) {
            try {
                $call();
                self::fail('Not refused: '.$index);
            } catch (PreparationRefused $exception) {
                self::assertSame('A closed preparation is reopened before it changes.', $exception->getMessage(), (string) $index);
            }
        }

        self::assertSame($kept->version + 1, $this->preparer->preparation('prep-1')->version);
    }

    public function testAPreparationIsLinkedToASessionOrToNone(): void
    {
        $this->create();
        $this->observed->take();

        $linked = $this->preparer->link('prep-1', 'session-4', self::GM);
        self::assertSame(['session-4', 2], [$linked->session, $linked->version]);
        self::assertSame($linked, $this->preparer->link('prep-1', 'session-4', self::GM));
        $unlinked = $this->preparer->link('prep-1', null, self::GM);
        self::assertNull($unlinked->session);
        self::assertSame($unlinked, $this->store->preparation('prep-1'));
        self::assertEquals([new SessionLinked('prep-1', 'session-4'), new SessionLinked('prep-1', null)], $this->observed->take());
    }

    public function testTheFactsOfTheTableAreMarkedEachToldOnce(): void
    {
        $this->create();
        $this->observed->take();

        $played = $this->preparer->markScene('prep-1', 'docks', SceneStatus::Played, self::GM);
        self::assertSame(SceneStatus::Played, $played->scene('docks')?->status);
        self::assertSame($played, $this->preparer->markScene('prep-1', 'docks', SceneStatus::Played, self::GM));

        $revealed = $this->preparer->markSecret('prep-1', 'heir', true, self::GM);
        self::assertTrue($revealed->secret('heir')?->revealed);
        self::assertSame($revealed, $this->preparer->markSecret('prep-1', 'heir', true, self::GM));

        $found = $this->preparer->markClue('prep-1', 'heir', 'letter', true, self::GM);
        self::assertTrue($found->secret('heir')?->clue('letter')?->found);
        self::assertSame($found, $this->preparer->markClue('prep-1', 'heir', 'letter', true, self::GM));

        $checked = $this->preparer->markItem('prep-1', 'opening', true, self::GM);
        self::assertTrue($checked->item('opening')?->checked);
        self::assertSame($checked, $this->preparer->markItem('prep-1', 'opening', true, self::GM));

        $this->preparer->markScene('prep-1', 'docks', SceneStatus::Planned, self::GM);
        $this->preparer->markSecret('prep-1', 'heir', false, self::GM);
        $this->preparer->markClue('prep-1', 'heir', 'letter', false, self::GM);

        $last = $this->preparer->markItem('prep-1', 'opening', false, self::GM);

        self::assertSame(9, $last->version);
        self::assertSame($last, $this->store->preparation('prep-1'));
        self::assertEquals([
            new SceneMarked('prep-1', 'docks', SceneStatus::Played),
            new SecretMarked('prep-1', 'heir', true),
            new ClueMarked('prep-1', 'heir', 'letter', true),
            new ItemMarked('prep-1', 'opening', true),
            new SceneMarked('prep-1', 'docks', SceneStatus::Planned),
            new SecretMarked('prep-1', 'heir', false),
            new ClueMarked('prep-1', 'heir', 'letter', false),
            new ItemMarked('prep-1', 'opening', false),
        ], $this->observed->take());
    }

    public function testMarkingAMissingPartIsRefusedEvenWhenTheValueIsTheDefault(): void
    {
        $this->create();

        foreach ([
            fn (): mixed => $this->preparer->markScene('prep-1', 'none', SceneStatus::Planned, self::GM),
            fn (): mixed => $this->preparer->markSecret('prep-1', 'none', false, self::GM),
            fn (): mixed => $this->preparer->markClue('prep-1', 'heir', 'none', false, self::GM),
            fn (): mixed => $this->preparer->markItem('prep-1', 'none', false, self::GM),
        ] as $index => $call) {
            try {
                $call();
                self::fail('Not refused: '.$index);
            } catch (InvalidPreparation $exception) {
                self::assertStringContainsString('has the key none', $exception->getMessage());
            }
        }
    }

    public function testADraftIsReadyOnceItsRequiredItemsAreChecked(): void
    {
        $this->create();
        $this->observed->take();

        try {
            $this->preparer->ready('prep-1', self::GM);
            self::fail('A missing item is not refused.');
        } catch (PreparationRefused $preparationRefused) {
            self::assertSame('The preparation is ready once its required items are checked: opening, secrets.', $preparationRefused->getMessage());
        }

        $this->preparer->markItem('prep-1', 'opening', true, self::GM);
        $this->preparer->markItem('prep-1', 'secrets', true, self::GM);

        $this->observed->take();

        $ready = $this->preparer->ready('prep-1', self::GM);
        self::assertSame([PreparationStatus::Ready, 4], [$ready->status, $ready->version]);
        self::assertSame($ready, $this->preparer->ready('prep-1', self::GM));
        self::assertSame($ready, $this->store->preparation('prep-1'));
        self::assertEquals([new PreparationReady('prep-1')], $this->observed->take());
    }

    public function testAPreparationIsClosedAndReopened(): void
    {
        $this->create();
        $this->observed->take();

        $closed = $this->preparer->close('prep-1', self::GM);
        self::assertSame([PreparationStatus::Closed, 2], [$closed->status, $closed->version]);
        self::assertSame($closed, $this->preparer->close('prep-1', self::GM));

        $reopened = $this->preparer->reopen('prep-1', self::GM);
        self::assertSame([PreparationStatus::Draft, 3], [$reopened->status, $reopened->version]);
        self::assertSame($reopened, $this->preparer->reopen('prep-1', self::GM));
        self::assertSame($reopened, $this->store->preparation('prep-1'));
        self::assertEquals([new PreparationClosed('prep-1'), new PreparationReopened('prep-1')], $this->observed->take());

        $this->preparer->markItem('prep-1', 'opening', true, self::GM);
        $this->preparer->markItem('prep-1', 'secrets', true, self::GM);
        $this->preparer->ready('prep-1', self::GM);
        self::assertSame(PreparationStatus::Draft, $this->preparer->reopen('prep-1', self::GM)->status);
    }

    public function testWhatWasLeftOverIsCarriedIntoTheNextPreparation(): void
    {
        $this->create();
        $this->preparer->markSecret('prep-1', 'heir', true, self::GM);
        $this->preparer->markClue('prep-1', 'cult', 'robe', true, self::GM);
        $this->preparer->markScene('prep-1', 'docks', SceneStatus::Played, self::GM);
        $this->preparer->close('prep-1', self::GM);
        $this->preparer->create(new Preparation(
            'prep-2',
            'campaign-7',
            self::GM,
            'Next',
            scenes: [new Scene('market', 'Market', characters: ['ilse'])],
            secrets: [new Secret('debt', 'The debt')],
            characters: [new NonPlayerCharacter('ilse', 'Ilse'), new NonPlayerCharacter('tobin', 'Tobin, as now')],
        ));
        $this->observed->take();

        $next = $this->preparer->carryOver('prep-1', 'prep-2', self::GM);

        self::assertSame(['debt', 'cult', 'ghost'], $this->keys($next->secrets));
        self::assertTrue($next->secret('cult')?->clue('robe')?->found);
        self::assertSame(['market', 'tower', 'crypt'], $this->keys($next->scenes));
        self::assertSame(['ilse', 'tobin', 'mara'], $this->keys($next->characters));
        self::assertSame('Tobin, as now', $next->character('tobin')?->name);
        self::assertSame(['belfry'], $this->keys($next->places));
        self::assertSame([PreparationStatus::Draft, 2], [$next->status, $next->version]);
        self::assertSame($next, $this->store->preparation('prep-2'));
        self::assertEquals([new PreparationCarriedOver('prep-2', 'prep-1')], $this->observed->take());

        self::assertSame($next, $this->preparer->carryOver('prep-1', 'prep-2', self::GM));
        self::assertSame([], $this->observed->take());
    }

    public function testAPreparationIsCarriedIntoAnotherOfItsCampaign(): void
    {
        $this->create();
        $this->preparer->create(new Preparation('prep-2', 'campaign-8', self::GM, 'Elsewhere'));

        foreach ([['prep-1', 'prep-1'], ['prep-1', 'prep-2']] as [$from, $into]) {
            try {
                $this->preparer->carryOver($from, $into, self::GM);
                self::fail('Not refused: '.$into);
            } catch (PreparationRefused $exception) {
                self::assertSame('A preparation is carried into another one of its campaign.', $exception->getMessage());
            }
        }
    }

    public function testCarryingIntoAPreparationOfAnotherGameMasterIsRefused(): void
    {
        $this->create();
        $this->preparer->create(new Preparation('prep-2', 'campaign-7', 'member-2', 'Theirs'));

        $this->expectExceptionObject(PreparationRefused::because('Only the game master of a preparation changes it.'));

        $this->preparer->carryOver('prep-1', 'prep-2', self::GM);
    }

    public function testAPreparationIsDeletedInAnyStatus(): void
    {
        $this->create();
        $this->preparer->close('prep-1', self::GM);
        $this->observed->take();

        $this->preparer->delete('prep-1', self::GM);

        self::assertNull($this->store->preparation('prep-1'));
        self::assertEquals([new PreparationDeleted('prep-1')], $this->observed->take());
    }

    public function testThePreparationsAreReadByQueryWithinTheBounds(): void
    {
        $this->create();
        $this->preparer->create(new Preparation('prep-2', 'campaign-8', self::GM, 'Other'));

        $ids = static fn (iterable $preparations): array => array_map(static fn (Preparation $preparation): string => $preparation->id, [...$preparations]);

        self::assertSame(['prep-1', 'prep-2'], $ids($this->preparer->preparations(new PreparationQuery(gameMaster: self::GM))));
        self::assertSame(['prep-2'], $ids($this->preparer->preparations(new PreparationQuery('campaign-8'), 1)));
        self::assertSame(['prep-1'], $ids($this->preparer->preparations(new PreparationQuery(), 1)));
        self::assertCount(2, [...$this->preparer->preparations(new PreparationQuery(), SessionPreparer::MAX_READ)]);

        foreach ([0, SessionPreparer::MAX_READ + 1] as $limit) {
            try {
                $this->preparer->preparations(new PreparationQuery(), $limit);
                self::fail('Not refused: '.$limit);
            } catch (InvalidPreparation $exception) {
                self::assertSame('A read of preparations is of 1 to 1000.', $exception->getMessage());
            }
        }
    }

    public function testItWorksWithoutAnObserver(): void
    {
        $preparer = new SessionPreparer($this->store);

        $preparer->create($this->draft());
        $preparer->markSecret('prep-1', 'heir', true, self::GM);

        self::assertTrue($preparer->preparation('prep-1')->secret('heir')?->revealed);
    }

    private function create(): Preparation
    {
        return $this->preparer->create($this->draft());
    }

    private function draft(): Preparation
    {
        return new Preparation(
            'prep-1',
            'campaign-7',
            self::GM,
            'The drowned bell',
            'session-3',
            [
                new Scene('docks', 'Docks', characters: ['mara'], places: ['pier']),
                new Scene('tower', 'Tower', characters: ['tobin', 'mara'], places: ['belfry']),
                new Scene('crypt', 'Crypt'),
            ],
            [
                new Secret('heir', 'The heir', [new Clue('letter', 'A letter')]),
                new Secret('cult', 'The cult', [new Clue('robe', 'A robe'), new Clue('chant', 'A chant')]),
                new Secret('ghost', 'The ghost'),
            ],
            [new NonPlayerCharacter('mara', 'Mara'), new NonPlayerCharacter('tobin', 'Tobin'), new NonPlayerCharacter('wren', 'Wren')],
            [new Place('pier', 'Pier'), new Place('belfry', 'Belfry')],
            checklist: [new ChecklistItem('opening', 'Opening', true), new ChecklistItem('secrets', 'Secrets', true), new ChecklistItem('music', 'Music')],
        );
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
