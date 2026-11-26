<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Predictions\Contract\PredictionStore;
use Tilttek\Predictions\Prediction;
use Tilttek\Predictions\PredictionId;
use Tilttek\Predictions\Question;
use Tilttek\Predictions\QuestionKey;
use Tilttek\Predictions\QuestionStatus;

/**
 * A store that runs what another process does once, right after the first read of a question's predictions or right
 * before the first prediction is written or the first question is closed: the step that slips between a read and the write that follows.
 */
final class InterleavingPredictionStore implements PredictionStore
{
    /**
     * @var ?callable(): mixed
     */
    private $meanwhile;

    /**
     * @param 'predictions'|'save'|'close' $at
     * @param callable(): mixed            $meanwhile
     */
    public function __construct(
        private readonly PredictionStore $store,
        private readonly string $at,
        callable $meanwhile,
    ) {
        $this->meanwhile = $meanwhile;
    }

    #[Override]
    public function addQuestion(Question $question): Question
    {
        return $this->store->addQuestion($question);
    }

    #[Override]
    public function question(QuestionKey $key): ?Question
    {
        return $this->store->question($key);
    }

    #[Override]
    public function close(QuestionKey $key, QuestionStatus $status, ?string $outcome, DateTimeImmutable $at): ?Question
    {
        $this->meanwhile('close');

        return $this->store->close($key, $status, $outcome, $at);
    }

    #[Override]
    public function save(Prediction $prediction, DateTimeImmutable $at): ?Prediction
    {
        $this->meanwhile('save');

        return $this->store->save($prediction, $at);
    }

    #[Override]
    public function prediction(QuestionKey $question, ResourceReference $member): ?Prediction
    {
        return $this->store->prediction($question, $member);
    }

    #[Override]
    public function predictions(QuestionKey $question, ?PredictionId $after, int $limit): array
    {
        $predictions = $this->store->predictions($question, $after, $limit);
        $this->meanwhile('predictions');

        return $predictions;
    }

    #[Override]
    public function ofMember(ResourceReference $member, int $offset, int $limit): array
    {
        return $this->store->ofMember($member, $offset, $limit);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->store->eraseMember($member);
    }

    private function meanwhile(string $at): void
    {
        if ($at === $this->at && null !== $this->meanwhile) {
            $meanwhile = $this->meanwhile;
            $this->meanwhile = null;
            $meanwhile();
        }
    }
}
