<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Celebrations\Exception\InvalidCelebration;

/**
 * Something a member earned, as a listener of the application hands it in: the member, the source that gave it — the
 * key of a package or a feature, `badges` —, its kind — `badge`, `level`, `quest` —, the identifier of what was earned —
 * `badge.first-roll` —, the occurrence that makes it unique for that source — `badge.first-roll#1` —, the moment it
 * was earned, the parameters its message needs — a level reached, a count — and, maybe, the moment after which it is
 * no longer worth showing. The reward itself never expires: only its celebration does.
 */
final readonly class Achievement
{
    public const int MAX_PARAMETERS = 10;

    public const int MAX_PARAMETER_LENGTH = 100;

    /** @var array<string, int|string> */
    public array $parameters;

    /**
     * @param array<string, int|string> $parameters
     *
     * @throws InvalidCelebration
     */
    public function __construct(
        public ResourceReference $member,
        public string $source,
        public string $kind,
        public string $subject,
        public string $occurrence,
        public DateTimeImmutable $earnedAt,
        array $parameters = [],
        public ?DateTimeImmutable $showUntil = null,
    ) {
        Text::key($source, 'a source');
        Text::key($kind, 'a kind');
        Text::identifier($subject, 'subject');
        Text::identifier($occurrence, 'occurrence');

        if (\count($parameters) > self::MAX_PARAMETERS) {
            throw InvalidCelebration::because(\sprintf('An achievement carries at most %d parameters; this one has %d.', self::MAX_PARAMETERS, \count($parameters)));
        }

        foreach ($parameters as $name => $value) {
            Text::key($name, 'a parameter');

            if (\is_string($value)) {
                Text::value($value, \sprintf('The parameter "%s"', $name), self::MAX_PARAMETER_LENGTH);
            }
        }

        ksort($parameters, \SORT_STRING);
        $this->parameters = $parameters;

        if ($showUntil instanceof DateTimeImmutable && !($earnedAt < $showUntil)) {
            throw InvalidCelebration::because('The moment an achievement stops being shown comes after the moment it was earned.');
        }
    }
}
