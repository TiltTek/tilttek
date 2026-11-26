<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use Tilttek\Conditions\Exception\ConditionNotFound;
use Tilttek\Conditions\Exception\InvalidCondition;

/**
 * The conditions of a game system, declared by the site: built once from its system data, and read by the service.
 *
 * A condition brings only conditions of the catalogue, and only ones without a value; no condition brings itself back
 * through others.
 */
final readonly class ConditionCatalogue
{
    /** The most conditions of a catalogue. */
    public const int MAX_DEFINITIONS = 1_000;

    /** @var array<string, ConditionDefinition> by identifier */
    private array $definitions;

    /**
     * @param list<ConditionDefinition> $definitions
     *
     * @throws InvalidCondition when there are too many, an identifier comes twice, or a condition brings one that is
     *                          missing, has a value, or brings it back
     */
    public function __construct(array $definitions)
    {
        if (\count($definitions) > self::MAX_DEFINITIONS) {
            throw InvalidCondition::because(\sprintf('A catalogue has %d conditions at most.', self::MAX_DEFINITIONS));
        }

        $byId = [];
        foreach ($definitions as $definition) {
            if (isset($byId[$definition->id])) {
                throw InvalidCondition::because(\sprintf('The condition %s comes twice in the catalogue.', $definition->id));
            }

            $byId[$definition->id] = $definition;
        }

        foreach ($byId as $definition) {
            foreach ($definition->implies as $implied) {
                $target = $byId[$implied] ?? throw InvalidCondition::because(\sprintf('The condition %s brings %s, which the catalogue lacks.', $definition->id, $implied));
                if ($target->hasValue()) {
                    throw InvalidCondition::because(\sprintf('The condition %s brings %s, which has a value.', $definition->id, $implied));
                }
            }
        }

        $this->definitions = $byId;
        foreach ($byId as $id => $definition) {
            if (\in_array($id, $this->implied($definition->implies), true)) {
                throw InvalidCondition::because(\sprintf('The condition %s brings itself back through others.', $id));
            }
        }
    }

    public function find(string $id): ?ConditionDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    /**
     * @throws ConditionNotFound when the catalogue has no condition of the identifier
     */
    public function get(string $id): ConditionDefinition
    {
        return $this->definitions[$id] ?? throw ConditionNotFound::definition($id);
    }

    /**
     * @return list<string> the identifiers, in the order declared
     */
    public function ids(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Every condition the given ones bring, through others too, each once, in the order met; the given ones are not in
     * it unless another brings them.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function implied(array $ids): array
    {
        $found = [];
        $queue = [];
        foreach ($ids as $id) {
            foreach ($this->definitions[$id]->implies ?? [] as $implied) {
                $queue[] = $implied;
            }
        }

        while ([] !== $queue) {
            $id = array_shift($queue);
            if (isset($found[$id])) {
                continue;
            }

            $found[$id] = $id;
            foreach ($this->definitions[$id]->implies ?? [] as $implied) {
                $queue[] = $implied;
            }
        }

        return array_values($found);
    }
}
