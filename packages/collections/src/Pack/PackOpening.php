<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Exception\InvalidCollection;

/**
 * A pack opened by a member: the key it was opened under, the pack, the seed of the draw in hexadecimal and its
 * commitment — the SHA-256 of the seed, shown before the opening —, the client seed the member gave after seeing the
 * commitment, the items drawn in order, the copies given and when. Anyone holding it can replay the draw and check it.
 */
final readonly class PackOpening
{
    /**
     * @param list<string> $items
     * @param list<string> $copies
     */
    public function __construct(
        public string $key,
        public ResourceReference $member,
        public string $pack,
        public string $seed,
        public string $commitment,
        public string $clientSeed,
        public array $items,
        public array $copies,
        public DateTimeImmutable $openedAt,
    ) {
        if (1 !== preg_match('/^(?:[0-9a-f]{2}){16,64}$/D', $seed)) {
            throw InvalidCollection::because('The seed of an opening is 16 to 64 bytes in lower-case hexadecimal.');
        }

        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $commitment)) {
            throw InvalidCollection::because('The commitment of an opening is a SHA-256 in lower-case hexadecimal.');
        }

        Packs::checkClientSeed($clientSeed);
    }

    /**
     * @return array{key: string, member: string, pack: string, seed: string, commitment: string, client_seed: string, items: list<string>, copies: list<string>, opened_at: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'member' => $this->member->toString(),
            'pack' => $this->pack,
            'seed' => $this->seed,
            'commitment' => $this->commitment,
            'client_seed' => $this->clientSeed,
            'items' => $this->items,
            'copies' => $this->copies,
            'opened_at' => $this->openedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
