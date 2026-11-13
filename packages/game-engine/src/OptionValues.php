<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The values chosen for a list of options — the options of a table, the preferences of a seat —, checked by Symfony's
 * OptionsResolver, with the defaults of the options not chosen.
 *
 * @internal
 */
final class OptionValues
{
    private function __construct()
    {
    }

    /**
     * @param list<Option>         $options the options offered
     * @param array<string, mixed> $values  the values chosen
     * @param string               $what    what the values are, for the message of a refusal: `the options of the table`
     *
     * @return array<string, bool|int|string>
     *
     * @throws InvalidGame when a value is given for an option not offered, or a value the option does not allow
     */
    public static function resolve(array $options, array $values, string $what): array
    {
        $resolver = new OptionsResolver();

        foreach ($options as $option) {
            $resolver->setDefault($option->name, $option->default);
            $resolver->setAllowedTypes($option->name, $option->kind->value);
            $resolver->setAllowedValues($option->name, $option->allows(...));
        }

        try {
            $resolved = $resolver->resolve($values);
        } catch (ExceptionInterface $exception) {
            throw InvalidGame::because(\sprintf('%s are refused: %s', ucfirst($what), $exception->getMessage()), $exception);
        }

        $checked = [];

        foreach ($options as $option) {
            $value = $resolved[$option->name];
            // The resolver has checked the type of every value: the default arm only narrows it.
            $checked[$option->name] = match (true) {
                \is_bool($value), \is_int($value), \is_string($value) => $value,
                default => $option->default,
            };
        }

        return $checked;
    }

    /**
     * Values read back as they were resolved — from a cache —, each a boolean, a whole number or a text under a name.
     *
     * @param array<mixed> $values
     *
     * @return array<string, bool|int|string>
     *
     * @throws InvalidGame
     */
    public static function read(array $values, string $what): array
    {
        $checked = [];

        foreach ($values as $name => $value) {
            if (!\is_string($name) || (!\is_bool($value) && !\is_int($value) && !\is_string($value))) {
                throw InvalidGame::because(\sprintf('%s hold a value that is not a boolean, a whole number or a text under a name.', ucfirst($what)));
            }

            $checked[$name] = $value;
        }

        return $checked;
    }
}
