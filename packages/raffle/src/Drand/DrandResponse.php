<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Drand;

use JsonException;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * Reads the answers of a drand relay: the information of a chain, `GET /{chain}/info`, and a round,
 * `GET /{chain}/public/{round}`. Anything that does not read, or describes another chain or round, is refused.
 *
 * @internal
 */
final class DrandResponse
{
    /** The longest answer read, in bytes: a round or the information of a chain is a few hundred. */
    public const int MAX_BYTES = 4096;

    private function __construct()
    {
    }

    /**
     * The chain the relay describes, its public key read, when it is the chain expected.
     *
     * @throws BeaconRefused when the answer does not read, or describes another chain
     */
    public static function info(string $json, BeaconChain $expected): BeaconChain
    {
        $data = self::decode($json);
        $scheme = DrandScheme::tryFrom($expected->scheme);
        $key = $data['public_key'] ?? null;
        $genesis = $data['genesis_time'] ?? null;
        $period = $data['period'] ?? null;
        $hash = $data['hash'] ?? null;
        $schemeId = $data['schemeID'] ?? null;

        if (!$scheme instanceof DrandScheme || !\is_string($key) || \strlen($key) !== $scheme->publicKeyLength() || !\is_int($genesis) || !\is_int($period) || !\is_string($hash) || !\is_string($schemeId)) {
            throw BeaconRefused::because('The relay does not describe a drand chain of the scheme expected.');
        }

        try {
            $chain = new BeaconChain($hash, $genesis, $period, $schemeId, $key);
        } catch (InvalidRaffle $invalidRaffle) {
            throw BeaconRefused::because('The relay describes a chain that does not read: '.$invalidRaffle->getMessage());
        }

        if (!$chain->isSame($expected)) {
            throw BeaconRefused::because(\sprintf('The relay describes the chain %s, not the chain %s expected.', $hash, $expected->hash));
        }

        return $chain;
    }

    /**
     * The round, when it is the round asked for and its randomness is the SHA-256 of its signature.
     *
     * @throws BeaconRefused when the answer does not read, or is another round
     */
    public static function beacon(string $json, DrandScheme $scheme, int $round, BeaconVerification $verification): Beacon
    {
        $data = self::decode($json);
        $given = $data['round'] ?? null;
        $randomness = $data['randomness'] ?? null;
        $signature = $data['signature'] ?? null;
        $previous = $scheme->isChained() ? $data['previous_signature'] ?? null : '';

        if ($given !== $round || !\is_string($randomness) || !\is_string($signature) || \strlen($signature) !== $scheme->signatureLength()
            || !\is_string($previous) || ($scheme->isChained() && \strlen($previous) !== $scheme->signatureLength())) {
            throw BeaconRefused::because(\sprintf('The relay does not give round %d with a signature of the scheme %s.', $round, $scheme->value));
        }

        $previous = '' === $previous ? null : $previous;

        try {
            return new Beacon($round, $randomness, $signature, $previous, $verification);
        } catch (InvalidRaffle $invalidRaffle) {
            throw BeaconRefused::because(\sprintf('The relay gives a round %d that does not check: %s', $round, $invalidRaffle->getMessage()));
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(string $json): array
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw BeaconRefused::because(\sprintf('An answer of a relay is at most %d bytes.', self::MAX_BYTES));
        }

        try {
            $data = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw BeaconRefused::because('An answer of a relay is a JSON object.');
        }

        return \is_array($data) ? $data : throw BeaconRefused::because('An answer of a relay is a JSON object.');
    }
}
