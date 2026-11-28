<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * A field of an entry's details: what a correction changes, and what the history names.
 */
enum Field: string
{
    case Name = 'name';
    case Type = 'type';
    case Kind = 'kind';
    case System = 'system';
    case Publishers = 'publishers';
    case Designers = 'designers';
    case Year = 'year';
    case Players = 'players';
    case Playtime = 'playtime';
    case MinimumAge = 'minimum_age';
    case Languages = 'languages';
    case Levels = 'levels';
    case LanguageDependence = 'language_dependence';
    case Description = 'description';
    case Cover = 'cover';
}
