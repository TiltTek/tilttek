<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Store;

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
 * The store in memory, for tests and for a process that keeps nothing.
 */
final class InMemoryPredictionStore implements PredictionStore
{
    /**
     * @var array<string, Question>
     */
    private array $questions = [];

    /**
     * @var array<string, Prediction> by question key and member reference, in the order they were first made
     */
    private array $predictions = [];

    #[Override]
    public function addQuestion(Question $question): Question
    {
        return $this->questions[$question->key->value] ??= $question;
    }

    #[Override]
    public function question(QuestionKey $key): ?Question
    {
        return $this->questions[$key->value] ?? null;
    }

    #[Override]
    public function close(QuestionKey $key, QuestionStatus $status, ?string $outcome, DateTimeImmutable $at): ?Question
    {
        $question = $this->question($key);

        if (!$question instanceof Question || QuestionStatus::Open !== $question->status) {
            return $question;
        }

        return $this->questions[$key->value] = null === $outcome ? $question->cancelled($at) : $question->resolved($outcome, $at);
    }

    #[Override]
    public function save(Prediction $prediction, DateTimeImmutable $at): ?Prediction
    {
        if (true !== $this->question($prediction->question)?->takesPredictionsAt($at)) {
            return null;
        }

        $index = $this->index($prediction->question, $prediction->member);
        $made = $this->predictions[$index] ?? null;

        return $this->predictions[$index] = $made instanceof Prediction ? $made->withPick($prediction->pick, $prediction->madeAt) : $prediction;
    }

    #[Override]
    public function prediction(QuestionKey $question, ResourceReference $member): ?Prediction
    {
        return $this->predictions[$this->index($question, $member)] ?? null;
    }

    #[Override]
    public function predictions(QuestionKey $question, ?PredictionId $after, int $limit): array
    {
        $on = array_filter($this->predictions, static fn (Prediction $prediction): bool => $prediction->question->equals($question) && $prediction->id->value > ($after->value ?? ''));
        usort($on, static fn (Prediction $one, Prediction $other): int => $one->id->value <=> $other->id->value);

        return \array_slice($on, 0, $limit);
    }

    #[Override]
    public function ofMember(ResourceReference $member, int $offset, int $limit): array
    {
        $made = array_filter($this->predictions, static fn (Prediction $prediction): bool => $prediction->member->equals($member));

        return \array_slice(array_values($made), $offset, $limit);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $before = \count($this->predictions);
        $this->predictions = array_filter($this->predictions, static fn (Prediction $prediction): bool => !$prediction->member->equals($member));

        return $before - \count($this->predictions);
    }

    private function index(QuestionKey $question, ResourceReference $member): string
    {
        return $question->value."\0".$member->toString();
    }
}
