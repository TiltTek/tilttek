<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Predictions\Prediction;
use Tilttek\Predictions\PredictionId;
use Tilttek\Predictions\Question;
use Tilttek\Predictions\QuestionKey;
use Tilttek\Predictions\QuestionStatus;

/**
 * Where the questions and the predictions are kept. A store backed by a database holds a unique constraint on the key
 * of a question and on the question and the member of a prediction, and writes a prediction only while its question
 * takes predictions, checked and written in one step that a resolution cannot slip between.
 */
interface PredictionStore
{
    /**
     * Keeps the question, unless one is kept under its key, which is returned.
     */
    public function addQuestion(Question $question): Question;

    public function question(QuestionKey $key): ?Question;

    /**
     * Closes the open question as resolved with the outcome, or as cancelled; a question no longer open is returned as
     * it stands, null when none is kept under the key.
     */
    public function close(QuestionKey $key, QuestionStatus $status, ?string $outcome, DateTimeImmutable $at): ?Question;

    /**
     * Keeps the prediction, in place of the member's prediction on the question if they made one, whose identifier
     * stays; null, keeping nothing, when the question does not take predictions at the moment.
     */
    public function save(Prediction $prediction, DateTimeImmutable $at): ?Prediction;

    public function prediction(QuestionKey $question, ResourceReference $member): ?Prediction;

    /**
     * The predictions on the question in the order of their identifiers, after the identifier given: a read by pages
     * that a prediction erased meanwhile does not shift.
     *
     * @return list<Prediction>
     */
    public function predictions(QuestionKey $question, ?PredictionId $after, int $limit): array;

    /**
     * The member's predictions, in the order they were first made, from an offset.
     *
     * @return list<Prediction>
     */
    public function ofMember(ResourceReference $member, int $offset, int $limit): array;

    /**
     * Deletes the member's predictions; returns how many.
     */
    public function eraseMember(ResourceReference $member): int;
}
