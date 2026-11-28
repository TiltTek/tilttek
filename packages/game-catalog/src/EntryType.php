<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * What an entry is as a product. The first six are the game types of the OpenTabletop data model; `Scenario` and
 * `Supplement` are the library's own, for the adventures and the sourcebooks of role-playing games.
 */
enum EntryType: string
{
    /** Played on its own: a board game, or the core rules of a role-playing game. */
    case BaseGame = 'base_game';
    /** Needs a base game. */
    case Expansion = 'expansion';
    /** Played on its own, and part of a family of games. */
    case StandaloneExpansion = 'standalone_expansion';
    /** A small addition, often a single component. */
    case Promo = 'promo';
    /** Not played: sleeves, inserts, a screen. */
    case Accessory = 'accessory';
    /** Made by players, not by the publisher. */
    case FanExpansion = 'fan_expansion';
    /** An adventure, a module or a campaign, with its levels. */
    case Scenario = 'scenario';
    /** A sourcebook: rules, setting or options added to a base game. */
    case Supplement = 'supplement';
}
