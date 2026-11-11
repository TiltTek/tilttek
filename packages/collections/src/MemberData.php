<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Phil\Resource\ResourceReference;
use Tilttek\Collections\Dice\ChoiceStore;
use Tilttek\Collections\Dice\DieChoice;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\Collections\Pack\PackOpening;
use Tilttek\Collections\Store\CompletionStore;
use Tilttek\Collections\Store\CopyStore;

/**
 * What the collections keep of a member, for an access or an erasure request: their copies, the sets they completed,
 * the packs they opened and the dice they chose. Erasing a member removes all of it; the copies they traded away
 * belong to other members and stay theirs, without a trace of where they came from.
 */
final readonly class MemberData
{
    public function __construct(
        private CopyStore $copies,
        private CompletionStore $completions,
        private OpeningStore $openings,
        private ChoiceStore $choices,
    ) {
    }

    /**
     * @return array{copies: list<array{id: string, item: string, owner: string, source: string, key: string, acquired_at: string, tradable: bool, moved: bool}>, completions: list<array{set: string, member: string, completed_at: string}>, openings: list<array{key: string, member: string, pack: string, seed: string, commitment: string, client_seed: string, items: list<string>, copies: list<string>, opened_at: string}>, choices: list<array{member: string, sides: int, copy: string}>}
     */
    public function export(ResourceReference $member): array
    {
        return [
            'copies' => array_map(static fn (Copy $copy): array => $copy->toArray(), $this->copies->ofMember($member)),
            'completions' => array_map(static fn (SetCompletion $completion): array => $completion->toArray(), $this->completions->ofMember($member)),
            'openings' => array_map(static fn (PackOpening $opening): array => $opening->toArray(), $this->openings->ofMember($member)),
            'choices' => array_map(static fn (DieChoice $choice): array => $choice->toArray(), $this->choices->ofMember($member)),
        ];
    }

    public function erase(ResourceReference $member): Erasure
    {
        return new Erasure(
            $this->copies->eraseOwner($member),
            $this->completions->eraseMember($member),
            $this->openings->eraseMember($member),
            $this->choices->eraseMember($member),
        );
    }
}
