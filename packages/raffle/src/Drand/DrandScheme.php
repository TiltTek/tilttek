<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Drand;

use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * The signature schemes of drand: on which group of BLS12-381 the signatures and the public key lie, and whether a
 * round signs the signature of the round before.
 */
enum DrandScheme: string
{
    /** Signatures on G2, the round chained to the one before: drand's first network, 30 seconds a round. */
    case PedersenBlsChained = 'pedersen-bls-chained';

    /** Signatures on G2, each round signed alone. */
    case PedersenBlsUnchained = 'pedersen-bls-unchained';

    /** Signatures on G1, each round signed alone, hashed to the curve before RFC 9380 was final. */
    case BlsUnchainedOnG1 = 'bls-unchained-on-g1';

    /** Signatures on G1, each round signed alone, hashed to the curve as RFC 9380 says: quicknet, 3 seconds a round. */
    case BlsUnchainedG1Rfc9380 = 'bls-unchained-g1-rfc9380';

    /**
     * The length of a signature, in hexadecimal digits: 96 on G1, 192 on G2.
     */
    public function signatureLength(): int
    {
        return $this->signsOnG1() ? 96 : 192;
    }

    /**
     * The length of the public key, in hexadecimal digits: on the other group.
     */
    public function publicKeyLength(): int
    {
        return $this->signsOnG1() ? 192 : 96;
    }

    public function isChained(): bool
    {
        return self::PedersenBlsChained === $this;
    }

    /**
     * The 32 bytes a round signs: the SHA-256 of the round as 8 big-endian bytes, preceded, for a chained scheme, by
     * the bytes of the signature of the round before.
     *
     * @throws InvalidRaffle when the scheme is chained and the previous signature is not hexadecimal of its length
     */
    public function message(int $round, ?string $previousSignature): string
    {
        if (!$this->isChained()) {
            return hash('sha256', pack('J', $round), true);
        }

        if (null === $previousSignature || \strlen($previousSignature) !== $this->signatureLength() || 1 !== preg_match('/^[0-9a-f]+$/D', $previousSignature)) {
            throw InvalidRaffle::because(\sprintf('A round of the scheme %s signs the previous signature, %d hexadecimal digits.', $this->value, $this->signatureLength()));
        }

        return hash('sha256', sodium_hex2bin($previousSignature).pack('J', $round), true);
    }

    private function signsOnG1(): bool
    {
        return self::BlsUnchainedOnG1 === $this || self::BlsUnchainedG1Rfc9380 === $this;
    }
}
