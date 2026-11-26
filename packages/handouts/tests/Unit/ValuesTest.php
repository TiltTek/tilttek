<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Handouts\Attachment;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Disclosure;
use Tilttek\Handouts\Exception\HandoutException;
use Tilttek\Handouts\Exception\HandoutRefused;
use Tilttek\Handouts\Exception\InvalidHandout;
use Tilttek\Handouts\Exception\UnknownHandout;
use Tilttek\Handouts\Handout;
use Tilttek\Handouts\Limits;
use Tilttek\Handouts\MediaTypes;
use Tilttek\Handouts\Refusal;
use Tilttek\Handouts\Sighting;
use Tilttek\Handouts\StagedReveal;
use Tilttek\Handouts\Text;
use Tilttek\Handouts\Trigger;

#[CoversClass(Handout::class)]
#[CoversClass(Attachment::class)]
#[CoversClass(MediaTypes::class)]
#[CoversClass(Audience::class)]
#[CoversClass(Trigger::class)]
#[CoversClass(Disclosure::class)]
#[CoversClass(StagedReveal::class)]
#[CoversClass(Sighting::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidHandout::class)]
#[CoversClass(HandoutRefused::class)]
#[CoversClass(UnknownHandout::class)]
final class ValuesTest extends TestCase
{
    private ResourceReference $handout;

    private ResourceReference $table;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->handout = ResourceReference::of('handout', '4');
        $this->table = ResourceReference::of('campaign', '3');
        $this->now = new DateTimeImmutable('2026-11-25 10:00:00');
    }

    public function testAHandoutKeepsItsTableTitleTextAndAttachments(): void
    {
        $map = $this->attachment('9');
        $handout = new Handout($this->handout, $this->table, "The miller's letter", "Meet me\n\tat the mill.", [$map], $this->now);

        self::assertSame(['handout:4', 'campaign:3', "The miller's letter", "Meet me\n\tat the mill.", 1, null], [$handout->reference->toString(), $handout->table->toString(), $handout->title, $handout->text, $handout->revision, $handout->editedAt]);
        self::assertSame([$map], $handout->attachments);
        self::assertSame($this->now, $handout->createdAt);
    }

    public function testAnEditRaisesTheRevision(): void
    {
        $handout = new Handout($this->handout, $this->table, 'Letter', 'Text', [], $this->now);
        $later = new DateTimeImmutable('2026-11-25 11:00:00');

        $edited = $handout->edited('Torn letter', null, [$this->attachment('9')], $later);

        self::assertSame(['Torn letter', null, 2, $later, $this->now, 'handout:4', 'campaign:3'], [$edited->title, $edited->text, $edited->revision, $edited->editedAt, $edited->createdAt, $edited->reference->toString(), $edited->table->toString()]);
        self::assertSame(3, $edited->edited('Again', 'Text', [], $later)->revision);
    }

    public function testAHandoutMayHoldOnlyAttachments(): void
    {
        $handout = new Handout($this->handout, $this->table, 'Map', null, [$this->attachment('9'), $this->attachment('10')], $this->now);

        self::assertNull($handout->text);
        self::assertCount(2, $handout->attachments);
    }

    public function testAHandoutHoldsSomething(): void
    {
        $this->expectInvalid('A handout holds a text, an attachment, or both.');

        new Handout($this->handout, $this->table, 'Empty', null, [], $this->now);
    }

    public function testAFileIsShownOnce(): void
    {
        $this->expectInvalid('A handout shows the file "file:9" once.');

        new Handout($this->handout, $this->table, 'Map', null, [$this->attachment('9'), $this->attachment('9')], $this->now);
    }

    public function testAHandoutHoldsAtMostFiftyAttachments(): void
    {
        $attachments = array_map(fn (int $i): Attachment => $this->attachment((string) $i), range(1, 50));
        self::assertCount(50, new Handout($this->handout, $this->table, 'Maps', null, $attachments, $this->now)->attachments);

        $attachments[] = $this->attachment('51');
        $this->expectInvalid('A handout holds at most 50 attachments.');

        new Handout($this->handout, $this->table, 'Maps', null, $attachments, $this->now);
    }

    public function testTheRevisionStartsAtOne(): void
    {
        self::assertSame(7, new Handout($this->handout, $this->table, 'T', 'x', [], $this->now, 7)->revision);

        $this->expectInvalid('The revision of a handout starts at 1; it is 0.');

        new Handout($this->handout, $this->table, 'T', 'x', [], $this->now, 0);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTitles(): iterable
    {
        yield 'empty' => [''];
        yield 'leading space' => [' Letter'];
        yield 'trailing space' => ['Letter '];
        yield 'line break' => ["Let\nter"];
        yield 'too long' => [str_repeat('é', 151)];
    }

    #[DataProvider('invalidTitles')]
    public function testAnInvalidTitleIsRefused(string $title): void
    {
        $this->expectInvalid('The title of a handout is 1 to 150 characters of UTF-8 without control characters nor surrounding spaces.');

        new Handout($this->handout, $this->table, $title, 'Text', [], $this->now);
    }

    public function testTheTitleMayTakeAHundredAndFiftyCharacters(): void
    {
        self::assertSame(str_repeat('é', 150), new Handout($this->handout, $this->table, str_repeat('é', 150), 'x', [], $this->now)->title);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \n\t "];
        yield 'control character' => ["a\u{0007}b"];
        yield 'carriage return' => ["a\r\nb"];
        yield 'too long' => [str_repeat('é', 20_001)];
        yield 'invalid UTF-8' => ["\xC3\x28"];
    }

    #[DataProvider('invalidTexts')]
    public function testAnInvalidTextIsRefused(string $text): void
    {
        $this->expectInvalid('The text of a handout is 1 to 20000 characters of UTF-8, not blank, with line breaks and tabs the only control characters.');

        new Handout($this->handout, $this->table, 'Letter', $text, [], $this->now);
    }

    public function testTheTextMayTakeTwentyThousandCharacters(): void
    {
        self::assertSame(20_000, mb_strlen((string) new Handout($this->handout, $this->table, 'Letter', str_repeat('é', 20_000), [], $this->now)->text));
    }

    public function testAnAttachmentChecksItsMediaType(): void
    {
        $this->expectException(InvalidHandout::class);

        new Attachment(ResourceReference::of('file', '9'), 'Image/PNG', 'A map');
    }

    public function testAnAttachmentKeepsItsFileTypeAndDescription(): void
    {
        $attachment = new Attachment(ResourceReference::of('file', '9'), 'image/webp', 'A hand-drawn map of the mill');

        self::assertSame(['file:9', 'image/webp', 'A hand-drawn map of the mill'], [$attachment->file->toString(), $attachment->mediaType, $attachment->description]);
        self::assertSame(str_repeat('a', 500), new Attachment(ResourceReference::of('file', '9'), 'image/png', str_repeat('a', 500))->description);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDescriptions(): iterable
    {
        yield 'empty' => [''];
        yield 'surrounding space' => [' Map'];
        yield 'too long' => [str_repeat('a', 501)];
    }

    #[DataProvider('invalidDescriptions')]
    public function testAnAttachmentIsDescribed(string $description): void
    {
        $this->expectInvalid('The description of an attachment is 1 to 500 characters of UTF-8 without control characters nor surrounding spaces.');

        new Attachment(ResourceReference::of('file', '9'), 'image/png', $description);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMediaTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'no subtype' => ['image'];
        yield 'empty subtype' => ['image/'];
        yield 'uppercase' => ['Image/PNG'];
        yield 'parameters' => ['text/plain; charset=utf-8'];
        yield 'two slashes' => ['image/png/x'];
        yield 'leading symbol' => ['+image/png'];
        yield 'subtype leading symbol' => ['image/+png'];
        yield 'too long' => ['a/'.str_repeat('b', 254)];
        yield 'line break' => ["image/png\n"];
    }

    #[DataProvider('invalidMediaTypes')]
    public function testAnInvalidMediaTypeIsRefused(string $type): void
    {
        $this->expectInvalid(\sprintf('A media type is "type/subtype" in lowercase, 3 to 255 characters, without parameters; "%s" is not.', $type));

        MediaTypes::check($type);
    }

    public function testMediaTypesAreWrittenTypeSlashSubtype(): void
    {
        self::assertSame('a/b', MediaTypes::check('a/b'));
        self::assertSame('image/svg+xml', MediaTypes::check('image/svg+xml'));
        self::assertSame('application/vnd.ms-excel', MediaTypes::check('application/vnd.ms-excel'));
        self::assertSame('a/'.str_repeat('b', 253), MediaTypes::check('a/'.str_repeat('b', 253)));
    }

    public function testTheDefaultMediaTypesLeaveSvgOut(): void
    {
        $types = new MediaTypes();

        self::assertSame(MediaTypes::DEFAULTS, $types->types());
        self::assertTrue($types->allows('image/png'));
        self::assertTrue($types->allows('application/pdf'));
        self::assertFalse($types->allows('image/svg+xml'));
        self::assertFalse($types->allows('text/html'));
    }

    public function testTheApplicationChoosesItsMediaTypes(): void
    {
        $types = new MediaTypes(['image/svg+xml', 'image/png', 'image/svg+xml']);

        self::assertSame(['image/svg+xml', 'image/png'], $types->types());
        self::assertTrue($types->allows('image/svg+xml'));
        self::assertFalse($types->allows('image/jpeg'));
        self::assertCount(100, new MediaTypes(array_map(static fn (int $i): string => 'image/x'.$i, range(1, 100)))->types());
    }

    public function testNoMediaTypeIsRefused(): void
    {
        $this->expectInvalid('The allowed media types are 1 to 100; 0 are given.');

        new MediaTypes([]);
    }

    public function testTooManyMediaTypesAreRefused(): void
    {
        $this->expectInvalid('The allowed media types are 1 to 100; 101 are given.');

        new MediaTypes(array_map(static fn (int $i): string => 'image/x'.$i, range(1, 101)));
    }

    public function testAnInvalidAllowedMediaTypeIsRefused(): void
    {
        $this->expectException(InvalidHandout::class);

        new MediaTypes(['image/png', 'PNG']);
    }

    public function testEveryoneIsOneKey(): void
    {
        $everyone = Audience::everyone();

        self::assertTrue($everyone->isEveryone());
        self::assertSame([], $everyone->players);
        self::assertSame(['*'], $everyone->keys());
        self::assertSame([null], $everyone->members());
    }

    public function testPlayersAreKeptOnceInTheirOrder(): void
    {
        $ada = ResourceReference::of('player', '12');
        $bo = ResourceReference::of('player', '13');

        $audience = Audience::players($bo, $ada, ResourceReference::of('player', '13'));

        self::assertFalse($audience->isEveryone());
        self::assertSame(['player:13', 'player:12'], $audience->keys());
        self::assertSame([$bo, $ada], $audience->members());
        self::assertSame([$bo, $ada], $audience->players);
    }

    public function testAnAudienceNamesAPlayer(): void
    {
        $this->expectInvalid('An audience names 1 to 500 players; 0 are given.');

        Audience::players();
    }

    public function testAnAudienceNamesAtMostFiveHundredPlayers(): void
    {
        $players = array_map(static fn (int $i): ResourceReference => ResourceReference::of('player', (string) $i), range(1, 500));
        self::assertCount(500, Audience::players(...$players)->players);

        $players[] = ResourceReference::of('player', '501');
        $this->expectInvalid('An audience names 1 to 500 players; 501 are given.');

        Audience::players(...$players);
    }

    public function testATriggerIsAMomentOrACue(): void
    {
        $at = Trigger::at($this->now);
        self::assertSame([$this->now, null], [$at->at, $at->cue]);

        $cue = Trigger::cue('crypt.opened');
        self::assertSame([null, 'crypt.opened'], [$cue->at, $cue->cue]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Crypt'];
        yield 'space' => ['crypt opened'];
        yield 'leading dot' => ['.crypt'];
        yield 'trailing dot' => ['crypt.'];
        yield 'double dot' => ['crypt..opened'];
        yield 'too long' => [str_repeat('a', 97)];
        yield 'trailing line break' => ["crypt\n"];
    }

    #[DataProvider('invalidKeys')]
    public function testAnInvalidCueIsRefused(string $cue): void
    {
        $this->expectInvalid(\sprintf('The key of a cue must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "%s" is not.', $cue));

        Trigger::cue($cue);
    }

    public function testACueMayTakeNinetySixCharacters(): void
    {
        self::assertSame(str_repeat('a', 96), Trigger::cue(str_repeat('a', 96))->cue);
        self::assertSame('a-b_c.1', Trigger::cue('a-b_c.1')->cue);
    }

    public function testDisclosuresAndStagedRevealsAreKeyedByAudience(): void
    {
        $ada = ResourceReference::of('player', '12');
        $gm = ResourceReference::of('player', '1');

        $toAll = new Disclosure($this->handout, $this->table, null, 2, $this->now, $gm);
        $toAda = new Disclosure($this->handout, $this->table, $ada, 1, $this->now);

        self::assertSame(['*', 'player:12', 2, 'player:1', null], [$toAll->key(), $toAda->key(), $toAll->revision, $toAll->by?->toString(), $toAda->by]);

        $staged = new StagedReveal($this->handout, $this->table, $ada, Trigger::cue('crypt'), $this->now, $gm);
        self::assertSame(['player:12', '*', 'crypt'], [$staged->key(), new StagedReveal($this->handout, $this->table, null, Trigger::at($this->now), $this->now)->key(), $staged->trigger->cue]);
    }

    public function testASightingMovesItsLastMoment(): void
    {
        $later = new DateTimeImmutable('2026-11-25 11:00:00');
        $sighting = new Sighting($this->handout, ResourceReference::of('player', '12'), 1, $this->now, $this->now);

        $again = $sighting->seenAgain(3, $later);

        self::assertSame([3, $this->now, $later, 'handout:4', 'player:12'], [$again->revision, $again->firstSeenAt, $again->lastSeenAt, $again->handout->toString(), $again->player->toString()]);
    }

    public function testTheDefaultLimits(): void
    {
        $limits = new Limits();

        self::assertSame([200, 10, 50, 100], [$limits->handoutsPerTable, $limits->attachmentsPerHandout, $limits->audiencePerHandout, $limits->releaseBatch]);

        $widest = new Limits(5000, 50, 500, 1000);
        self::assertSame([5000, 50, 500, 1000], [$widest->handoutsPerTable, $widest->attachmentsPerHandout, $widest->audiencePerHandout, $widest->releaseBatch]);

        $narrowest = new Limits(1, 1, 1, 1);
        self::assertSame([1, 1, 1, 1], [$narrowest->handoutsPerTable, $narrowest->attachmentsPerHandout, $narrowest->audiencePerHandout, $narrowest->releaseBatch]);
    }

    /**
     * @return iterable<string, array{array{int, int, int, int}, string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no handout' => [[0, 10, 50, 100], 'The limit of handouts per table is 1 to 5000; it is 0.'];
        yield 'too many handouts' => [[5001, 10, 50, 100], 'The limit of handouts per table is 1 to 5000; it is 5001.'];
        yield 'no attachment' => [[200, 0, 50, 100], 'The limit of attachments per handout is 1 to 50; it is 0.'];
        yield 'too many attachments' => [[200, 51, 50, 100], 'The limit of attachments per handout is 1 to 50; it is 51.'];
        yield 'no audience' => [[200, 10, 0, 100], 'The limit of audience per handout is 1 to 500; it is 0.'];
        yield 'too large an audience' => [[200, 10, 501, 100], 'The limit of audience per handout is 1 to 500; it is 501.'];
        yield 'no release' => [[200, 10, 50, 0], 'The limit of staged reveals per release is 1 to 1000; it is 0.'];
        yield 'too large a release' => [[200, 10, 50, 1001], 'The limit of staged reveals per release is 1 to 1000; it is 1001.'];
    }

    /**
     * @param array{int, int, int, int} $values
     */
    #[DataProvider('invalidLimits')]
    public function testLimitsAreBounded(array $values, string $message): void
    {
        $this->expectInvalid($message);

        new Limits(...$values);
    }

    public function testEveryRefusalHasItsMessage(): void
    {
        $messages = [];

        foreach (Refusal::cases() as $refusal) {
            $exception = HandoutRefused::because($refusal);
            self::assertSame($refusal, $exception->refusal);
            self::assertInstanceOf(HandoutException::class, $exception);
            $messages[$refusal->value] = $exception->getMessage();
        }

        self::assertSame([
            'already_created' => 'A handout already holds this reference.',
            'too_many_handouts' => 'The table holds as many handouts as allowed.',
            'too_many_attachments' => 'The handout holds more attachments than allowed.',
            'media_type_not_allowed' => 'An attachment has a media type the application does not allow.',
            'audience_too_large' => 'The audience names more players than allowed.',
            'too_many_revealed' => 'The handout is revealed to as many audiences as allowed.',
            'too_many_staged' => 'The handout holds as many staged reveals as allowed.',
            'not_revealed' => 'The handout is not revealed to this player.',
        ], $messages);
    }

    public function testTheOtherExceptionsShareTheInterface(): void
    {
        self::assertInstanceOf(HandoutException::class, InvalidHandout::because('x'));
        self::assertSame('x', InvalidHandout::because('x')->getMessage());
        self::assertInstanceOf(HandoutException::class, UnknownHandout::named('handout:4'));
        self::assertSame('No handout is known as "handout:4".', UnknownHandout::named('handout:4')->getMessage());
    }

    private function attachment(string $id): Attachment
    {
        return new Attachment(ResourceReference::of('file', $id), 'image/png', 'A map');
    }

    private function expectInvalid(string $message): void
    {
        $this->expectException(InvalidHandout::class);
        $this->expectExceptionMessageIsOrContains($message);
    }
}
