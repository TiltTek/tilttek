<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A list named by its path, such as `name.fantasy.elf.female.first`, and its entries; or a list that invents new words
 * from its entries by a Markov chain. A list may say where its entries come from.
 */
final readonly class EntryList
{
    /**
     * @var list<Entry>
     */
    public array $entries;

    /**
     * @var array<string, true> the tags at least one entry carries
     */
    private array $tags;

    /**
     * How the list invents words from its entries, or null when a pick takes one of its entries.
     */
    public ?MarkovChain $markov;

    /**
     * Where the entries come from, unless an entry says otherwise.
     */
    public ?Attribution $attribution;

    /**
     * @throws InvalidDefinition when the list has no entry, or the weights of its entries add up to more than an integer holds
     */
    public function __construct(public string $name, Entry ...$entries)
    {
        if ([] === $entries) {
            throw InvalidDefinition::at($name, 'a list has at least one entry');
        }

        $this->entries = array_values($entries);
        $tags = [];
        $total = 0;

        foreach ($this->entries as $entry) {
            $tags += array_fill_keys(array_keys($entry->tags()), true);

            if ($entry->weight > \PHP_INT_MAX - $total) {
                throw InvalidDefinition::at($name, \sprintf('the weights of the entries of a list add up to at most %d', \PHP_INT_MAX));
            }

            $total += $entry->weight;
        }

        $this->tags = $tags;
        $this->markov = null;
        $this->attribution = null;
    }

    /**
     * The same list, whose picks are words invented from its entries by the chain.
     */
    public function invent(MarkovChain $chain): self
    {
        return clone ($this, ['markov' => $chain]);
    }

    /**
     * The same list, whose entries come from where the attribution says, unless an entry says otherwise.
     */
    public function attributed(Attribution $attribution): self
    {
        return clone ($this, ['attribution' => $attribution]);
    }

    /**
     * Whether at least one entry carries this tag.
     */
    public function hasTag(string $tag): bool
    {
        return isset($this->tags[$tag]);
    }
}
