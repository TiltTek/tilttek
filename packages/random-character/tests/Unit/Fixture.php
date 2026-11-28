<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit;

use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\Reader;
use Tilttek\RandomCharacter\Contract\CharacterObserver;
use Tilttek\RandomCharacter\Event\CharacterRolled;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Translations;

/**
 * The system, the generators and the observer the tests share.
 */
final class Fixture implements CharacterObserver
{
    /**
     * @var list<CharacterRolled>
     */
    public array $events = [];

    public function notify(CharacterRolled $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The system `test`: `str` from 3 to 18, `dex` from 3 to 18, `luck` without bounds.
     */
    public static function systems(): Systems
    {
        $source = new Provenance('Test', Licence::Owned);
        $attribute = static fn (string $key, ?int $minimum, ?int $maximum): Attribute => new Attribute($key, new Translations(['en' => ucfirst($key)]), $source, minimum: $minimum, maximum: $maximum);

        return new Systems(new GameSystem('test', new Translations(['en' => 'Test']), $source, attributes: [
            $attribute('str', 3, 18),
            $attribute('dex', 3, 18),
            $attribute('luck', null, null),
        ]));
    }

    public static function generator(Limits $limits = new Limits()): Generator
    {
        return new Generator(new Reader()->catalog(['test' => [
            'lists' => [
                'kin' => ['entries' => ['elf'], 'source' => 'Kin book', 'licence' => 'owned'],
                'kin.mixed' => ['elf', 'dwarf', 'gnome'],
                'name.elf' => ['entries' => ['Aerin', 'Lithil', 'Sael'], 'source' => 'Kin book', 'licence' => 'owned'],
                'name.dwarf' => ['Borin', 'Dagna'],
                'name.gnome' => ['Pip', 'Fizz'],
                'trait' => ['entries' => ['brave', 'curious', 'gentle', 'wry'], 'source' => 'Trait book', 'licence' => 'owned'],
                'single' => ['only'],
                'name.3' => ['Three'],
            ],
            'generators' => [
                'kin' => ['templates' => "{{ pick('kin') }}"],
                'kin.mixed' => ['templates' => "{{ pick('kin.mixed') }}"],
                'kin.object' => ['fields' => ['key' => 'elf', 'label' => 'Elf']],
                'name.elf' => ['templates' => "{{ pick('name.elf') }}"],
                'name.dwarf' => ['templates' => "{{ pick('name.dwarf') }}"],
                'name.gnome' => ['templates' => "{{ pick('name.gnome') }}"],
                'name.3' => ['templates' => "{{ pick('name.3') }}"],
                'trait' => ['templates' => "{{ pick('trait') }}"],
                'single' => ['templates' => "{{ pick('single') }}"],
            ],
        ]]), $limits);
    }
}
