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
 * A store whose questions are deleted between the moment they are read and the moment they are closed.
 */
final readonly class VanishingQuestionStore implements PredictionStore
{
    public function __construct(
        private PredictionStore $store,
    ) {
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
        return null;
    }

    #[Override]
    public function save(Prediction $prediction, DateTimeImmutable $at): ?Prediction
    {
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
        return $this->store->predictions($question, $after, $limit);
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
}
