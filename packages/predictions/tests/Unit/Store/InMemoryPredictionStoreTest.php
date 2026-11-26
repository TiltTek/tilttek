<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Predictions\Pick;
use Tilttek\Predictions\Prediction;
use Tilttek\Predictions\PredictionId;
use Tilttek\Predictions\Question;
use Tilttek\Predictions\QuestionKey;
use Tilttek\Predictions\QuestionStatus;
use Tilttek\Predictions\Store\InMemoryPredictionStore;

#[CoversClass(InMemoryPredictionStore::class)]
final class InMemoryPredictionStoreTest extends TestCase
{
    public function testAQuestionIsKeptOnce(): void
    {
        $store = new InMemoryPredictionStore();
        $question = $this->question('q');

        self::assertSame($question, $store->addQuestion($question));
        self::assertSame($question, $store->addQuestion($this->question('q', 99)));
        self::assertSame($question, $store->question(QuestionKey::fromString('q')));
        self::assertNull($store->question(QuestionKey::fromString('r')));
    }

    public function testClosingOnce(): void
    {
        $store = new InMemoryPredictionStore();
        $store->addQuestion($this->question('q'));
        $store->addQuestion($this->question('r'));

        $at = new DateTimeImmutable('2026-11-25 00:00:00 UTC');

        $resolved = $store->close(QuestionKey::fromString('q'), QuestionStatus::Resolved, 'a', $at);
        self::assertSame([QuestionStatus::Resolved, 'a'], [$resolved?->status, $resolved?->outcome]);
        self::assertSame($resolved, $store->close(QuestionKey::fromString('q'), QuestionStatus::Cancelled, null, $at));
        self::assertSame($resolved, $store->question(QuestionKey::fromString('q')));

        self::assertSame(QuestionStatus::Cancelled, $store->close(QuestionKey::fromString('r'), QuestionStatus::Cancelled, null, $at)?->status);
        self::assertNull($store->close(QuestionKey::fromString('s'), QuestionStatus::Cancelled, null, $at));
    }

    public function testSavingOnlyWhileTheQuestionTakesPredictions(): void
    {
        $store = new InMemoryPredictionStore();
        $store->addQuestion($this->question('q'));

        $first = $this->prediction('1', 'q', 1, 'a');

        self::assertNull($store->save($this->prediction('2', 'unknown', 1, 'a'), new DateTimeImmutable('2026-11-20 00:00:00 UTC')));
        self::assertNull($store->save($first, new DateTimeImmutable('2026-12-01 00:00:00 UTC')));
        self::assertSame($first, $store->save($first, new DateTimeImmutable('2026-11-30 23:59:59 UTC')));

        $changed = $store->save($this->prediction('3', 'q', 1, 'b'), new DateTimeImmutable('2026-11-30 23:59:59 UTC'));
        self::assertTrue($changed?->id->equals($first->id));
        self::assertSame('b', $changed->pick->choice);
        self::assertSame($changed, $store->prediction(QuestionKey::fromString('q'), ResourceReference::of('member', '1')));
        self::assertNull($store->prediction(QuestionKey::fromString('q'), ResourceReference::of('member', '2')));

        $store->close(QuestionKey::fromString('q'), QuestionStatus::Cancelled, null, new DateTimeImmutable('2026-11-21 00:00:00 UTC'));
        self::assertNull($store->save($this->prediction('4', 'q', 2, 'a'), new DateTimeImmutable('2026-11-22 00:00:00 UTC')));
    }

    public function testReadingByPages(): void
    {
        $store = new InMemoryPredictionStore();
        $store->addQuestion($this->question('q'));
        $store->addQuestion($this->question('r'));

        $at = new DateTimeImmutable('2026-11-20 00:00:00 UTC');

        foreach ([['3', 'q', 1], ['1', 'q', 2], ['2', 'r', 1], ['4', 'q', 3]] as [$id, $question, $member]) {
            $store->save($this->prediction($id, $question, $member, 'a'), $at);
        }

        $ids = static fn (array $predictions): array => array_map(static fn (Prediction $prediction): string => $prediction->id->value[0], $predictions);
        self::assertSame(['1', '3', '4'], $ids($store->predictions(QuestionKey::fromString('q'), null, 10)));
        self::assertSame(['1', '3'], $ids($store->predictions(QuestionKey::fromString('q'), null, 2)));
        self::assertSame(['4'], $ids($store->predictions(QuestionKey::fromString('q'), PredictionId::fromString(str_repeat('3', 32)), 2)));
        self::assertSame(['3', '2'], $ids($store->ofMember(ResourceReference::of('member', '1'), 0, 10)));
        self::assertSame(['2'], $ids($store->ofMember(ResourceReference::of('member', '1'), 1, 1)));
    }

    public function testErasingAMember(): void
    {
        $store = new InMemoryPredictionStore();
        $store->addQuestion($this->question('q'));
        $store->addQuestion($this->question('r'));

        $at = new DateTimeImmutable('2026-11-20 00:00:00 UTC');
        $store->save($this->prediction('1', 'q', 1, 'a'), $at);
        $store->save($this->prediction('2', 'r', 1, 'a'), $at);
        $store->save($this->prediction('3', 'q', 2, 'a'), $at);

        self::assertSame(2, $store->eraseMember(ResourceReference::of('member', '1')));
        self::assertSame(0, $store->eraseMember(ResourceReference::of('member', '1')));
        self::assertSame([], $store->ofMember(ResourceReference::of('member', '1'), 0, 10));
        self::assertCount(1, $store->predictions(QuestionKey::fromString('q'), null, 10));
    }

    private function question(string $key, int $points = 10): Question
    {
        return new Question(QuestionKey::fromString($key), ['a', 'b'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), $points);
    }

    public function testTwoQuestionsAndMembersNeverShareAnIndex(): void
    {
        $store = new InMemoryPredictionStore();
        $closes = new DateTimeImmutable('2026-12-01 00:00:00 UTC');
        $at = new DateTimeImmutable('2026-11-20 12:00:00 UTC');
        $store->addQuestion(new Question(QuestionKey::fromString('a'), ['x', 'y'], $closes, 1));
        $store->addQuestion(new Question(QuestionKey::fromString('ab'), ['x', 'y'], $closes, 1));

        $pick = Pick::choice('x');
        $one = new Prediction(PredictionId::fromString(str_repeat('a', 32)), QuestionKey::fromString('a'), ResourceReference::of('bc', '1'), $pick, $at);
        $other = new Prediction(PredictionId::fromString(str_repeat('b', 32)), QuestionKey::fromString('ab'), ResourceReference::of('c', '1'), $pick, $at);

        $store->save($one, $at);
        $store->save($other, $at);

        self::assertSame($one, $store->prediction(QuestionKey::fromString('a'), ResourceReference::of('bc', '1')));
        self::assertSame($other, $store->prediction(QuestionKey::fromString('ab'), ResourceReference::of('c', '1')));
    }

    private function prediction(string $id, string $question, int $member, string $choice): Prediction
    {
        return new Prediction(PredictionId::fromString(str_repeat($id, 32)), QuestionKey::fromString($question), ResourceReference::of('member', (string) $member), Pick::choice($choice), new DateTimeImmutable('2026-11-20 00:00:00 UTC'));
    }
}
