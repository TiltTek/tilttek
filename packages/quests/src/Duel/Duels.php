<?php

declare(strict_types=1);

namespace Tilttek\Quests\Duel;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Exception\QuestRefused;
use Tilttek\Quests\Exception\UnknownDuel;
use Tilttek\Quests\Exception\UnknownQuest;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestKind;
use Tilttek\Quests\QuestObserver;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Window;

/**
 * The challenges members send each other: a member challenges another to a duel quest, who accepts, declines or lets
 * the offer run out; the challenger may withdraw it until it is answered. Once accepted, the duel lasts the quest's
 * duel length — or until the quest closes —, and the first side to accomplish the quest wins it, which the projection
 * decides. A duel whose time runs out with no winner expires, when `expire()` sweeps the duels.
 *
 * ```php
 * $duels->challenge('duel:812', 'ten-games', $member, $friend);
 * $duels->accept('duel:812', $friend);
 * ```
 */
final readonly class Duels
{
    public const string DEFAULT_OFFER_LIFETIME = 'P7D';

    private DateInterval $offerLifetime;

    /**
     * @param string $offerLifetime how long an offer waits for its answer, as an ISO 8601 duration
     */
    public function __construct(
        private Evaluator $evaluator,
        private ClockInterface $clock,
        private ?QuestObserver $observer = null,
        string $offerLifetime = self::DEFAULT_OFFER_LIFETIME,
    ) {
        try {
            $this->offerLifetime = new DateInterval($offerLifetime);
        } catch (Exception) {
            throw InvalidQuest::because(\sprintf('"%s" is not an ISO 8601 duration like "P7D".', $offerLifetime));
        }

        $now = new DateTimeImmutable();

        if ($now->add($this->offerLifetime) <= $now) {
            throw InvalidQuest::because(\sprintf('An offer waits for some time; "%s" is no time.', $offerLifetime));
        }
    }

    /**
     * Challenges a member to a duel quest. The same key given again for the same duel returns it as it is.
     *
     * @throws UnknownQuest
     * @throws QuestRefused when the quest is not a duel or is closed, or the key names another duel
     */
    public function challenge(string $key, string $quest, ResourceReference $challenger, ResourceReference $opponent): Duel
    {
        $duel = new Duel($key, $quest, $challenger, $opponent, $this->clock->now());
        $existing = $this->evaluator->duels->find($key);

        if ($existing instanceof Duel) {
            if ($existing->quest !== $quest || !$existing->challenger->equals($challenger) || !$existing->opponent->equals($opponent)) {
                throw QuestRefused::because(\sprintf('The key "%s" names another duel.', $key));
            }

            return $existing;
        }

        $this->open($this->duelQuest($quest));
        $this->evaluator->duels->save($duel);

        return $duel;
    }

    /**
     * Accepts the duel, by the member challenged: from now on, each side's activity counts. Accepting it again returns
     * it as it is.
     *
     * @throws UnknownDuel
     * @throws QuestRefused when the member is not the one challenged, the duel no longer waits for an answer — an offer
     *                      run out expires then —, or its quest is closed
     */
    public function accept(string $key, ResourceReference $by): Duel
    {
        $duel = $this->get($key);
        $this->checkSide($duel, $duel->opponent, $by);

        if (DuelStatus::Accepted === $duel->status) {
            return $duel;
        }

        $this->checkOffered($duel);
        $now = $this->clock->now();

        if ($duel->offeredAt->add($this->offerLifetime) <= $now) {
            $this->end($duel->with(DuelStatus::Expired, $now));

            throw QuestRefused::because(\sprintf('The duel "%s" was not answered in time.', $key));
        }

        $quest = $this->duelQuest($duel->quest);
        $closesAt = $this->open($quest)->closesAt;
        $length = new Window(null, $quest->duelLength instanceof DateInterval ? $now->add($quest->duelLength) : null);
        $accepted = $duel->accepted($now, $length->intersect(new Window(null, $closesAt))->until);
        $this->evaluator->duels->save($accepted);

        return $accepted;
    }

    /**
     * Declines the duel, by the member challenged.
     *
     * @throws UnknownDuel
     * @throws QuestRefused when the member is not the one challenged, or the duel no longer waits for an answer
     */
    public function decline(string $key, ResourceReference $by): Duel
    {
        $duel = $this->get($key);
        $this->checkSide($duel, $duel->opponent, $by);

        return $this->answer($duel, DuelStatus::Declined);
    }

    /**
     * Withdraws the duel, by the challenger, until it is answered.
     *
     * @throws UnknownDuel
     * @throws QuestRefused when the member is not the challenger, or the duel no longer waits for an answer
     */
    public function withdraw(string $key, ResourceReference $by): Duel
    {
        $duel = $this->get($key);
        $this->checkSide($duel, $duel->challenger, $by);

        return $this->answer($duel, DuelStatus::Withdrawn);
    }

    /**
     * Expires the offers left unanswered past their lifetime and the accepted duels whose time is up with no winner,
     * and tells the observer of each.
     *
     * @return list<Duel> the duels expired
     */
    public function expire(): array
    {
        $now = $this->clock->now();
        $expired = [];

        foreach ($this->evaluator->duels->due($now->sub($this->offerLifetime), $now) as $duel) {
            $expired[] = $this->end($duel->with(DuelStatus::Expired, $now));
        }

        return $expired;
    }

    /**
     * @throws UnknownDuel
     */
    public function get(string $key): Duel
    {
        return $this->evaluator->duels->find($key) ?? throw UnknownDuel::keyed($key);
    }

    /**
     * The duels a member challenged or was challenged to, by moment of the offer.
     *
     * @return list<Duel>
     */
    public function of(ResourceReference $member): array
    {
        return $this->evaluator->duels->ofMember($member);
    }

    /**
     * The duel and how far each side is with its quest.
     *
     * @throws UnknownDuel
     * @throws UnknownQuest
     */
    public function standing(string $key): DuelStanding
    {
        $duel = $this->get($key);
        $quest = $this->evaluator->catalog->get($duel->quest);

        return new DuelStanding(
            $duel,
            $this->evaluator->duelProgress($quest, $duel, $duel->challenger),
            $this->evaluator->duelProgress($quest, $duel, $duel->opponent),
        );
    }

    private function duelQuest(string $code): Quest
    {
        $quest = $this->evaluator->catalog->get($code);

        if (QuestKind::Duel !== $quest->kind) {
            throw QuestRefused::because(\sprintf('"%s" is not a duel.', $code));
        }

        return $quest;
    }

    private function open(Quest $quest): Occurrence
    {
        return $quest->schedule->occurrenceAt($this->clock->now()) ?? throw QuestRefused::because(\sprintf('"%s" is closed.', $quest->code));
    }

    private function checkSide(Duel $duel, ResourceReference $side, ResourceReference $by): void
    {
        if (!$side->equals($by)) {
            throw QuestRefused::because(\sprintf('Only %s answers the duel "%s" so.', $side->toString(), $duel->key));
        }
    }

    private function checkOffered(Duel $duel): void
    {
        if (DuelStatus::Offered !== $duel->status) {
            throw QuestRefused::because(\sprintf('The duel "%s" is %s; it no longer waits for an answer.', $duel->key, $duel->status->value));
        }
    }

    private function answer(Duel $duel, DuelStatus $status): Duel
    {
        if ($status === $duel->status) {
            return $duel;
        }

        $this->checkOffered($duel);
        $answered = $duel->with($status, $this->clock->now());
        $this->evaluator->duels->save($answered);

        return $answered;
    }

    private function end(Duel $expired): Duel
    {
        $this->evaluator->duels->save($expired);
        $this->observer?->duelExpired($expired);

        return $expired;
    }
}
