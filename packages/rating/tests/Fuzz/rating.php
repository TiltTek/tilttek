<?php

declare(strict_types=1);

/*
 * The fuzz target of the computation of a rating, run by `phil-dev fuzz`: any text, read as a result in JSON, is refused
 * with `InvalidRating` or rated by every system from ratings taken from the same text, and each new rating is whole
 * numbers that the system reads back the same.
 */

use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Elo\KFactor;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\OpenSkill\OpenSkillModel;
use Tilttek\Rating\OpenSkill\OpenSkillSystem;
use Tilttek\Rating\OpenSkill\SkillRating;

require getenv('PHIL_DEV_AUTOLOAD');

$systems = [new EloSystem(KFactor::chess(), floor: 100), new OpenSkillSystem(), new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller)];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($systems): void {
    $data = json_decode($input, true, 8);

    if (!is_array($data)) {
        return;
    }

    try {
        $result = GameResult::fromArray($data);
    } catch (InvalidRating) {
        // The refusal the reader promises for a text that is not a result.
        return;
    }

    if ($result->toArray() !== GameResult::fromArray($result->toArray())->toArray()) {
        throw new LogicException('A result read back is not the same.');
    }

    foreach ($systems as $system) {
        $current = [];

        foreach ($result->members() as $seat => $member) {
            $seed = crc32($input.$seat);
            $rating = $system instanceof EloSystem
                ? new EloRating($seed % 400_000)
                : new SkillRating($seed % 100_000_000 - 50_000_000, 1 + $seed % 20_000_000);
            $current[$member] = new MemberRating($result->game, $member, $system->id(), $rating, $system->score($rating), $seed % 60);
        }

        foreach ($system->rate($result, $current) as $rating) {
            $values = $rating->toArray();

            if ($system->restore($values)->toArray() !== $values) {
                throw new LogicException('A rating computed does not read back the same.');
            }

            $system->score($rating);
        }
    }
});
$config->setMaxLen(4096);
