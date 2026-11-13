<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\TableSettings;

#[CoversClass(TableSettings::class)]
final class TableSettingsTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $settings = TableSettings::defaults();

        self::assertSame(SafetyTool::cases(), $settings->tools);
        self::assertFalse($settings->namedSignals);
        self::assertSame(Audience::Table, $settings->signalsSeenBy);
        self::assertSame([], $settings->customThemes);
        self::assertSame(Templates::CAMPAIGN, $settings->sessionZero->key);

        foreach (SafetyTool::cases() as $tool) {
            self::assertTrue($settings->uses($tool));
        }
    }

    public function testTheToolsKeepTheirOrderAndAreGivenOnce(): void
    {
        $settings = new TableSettings([SafetyTool::OpenDoor, SafetyTool::XCard, SafetyTool::XCard]);

        self::assertSame([SafetyTool::XCard, SafetyTool::OpenDoor], $settings->tools);
        self::assertTrue($settings->uses(SafetyTool::XCard));
        self::assertFalse($settings->uses(SafetyTool::CheckIn));
        self::assertSame([], new TableSettings([])->tools);
    }

    public function testWhoSeesEachSignal(): void
    {
        $settings = TableSettings::defaults();

        self::assertSame(Audience::GameMaster, $settings->audienceOf(SignalKind::OpenDoor));
        self::assertSame(Audience::GameMaster, $settings->audienceOf(SignalKind::AllGood));
        self::assertSame(Audience::GameMaster, $settings->audienceOf(SignalKind::Unsure));
        self::assertSame(Audience::GameMaster, $settings->audienceOf(SignalKind::NotOkay));
        self::assertSame(Audience::Table, $settings->audienceOf(SignalKind::CheckIn));
        self::assertSame(Audience::Table, $settings->audienceOf(SignalKind::XCard));
        self::assertSame(Audience::Table, $settings->audienceOf(SignalKind::Rewind));

        $discreet = $settings->withSignalsSeenBy(Audience::GameMaster);

        self::assertSame(Audience::GameMaster, $discreet->audienceOf(SignalKind::XCard));
        self::assertSame(Audience::GameMaster, $discreet->audienceOf(SignalKind::Pause));
        self::assertSame(Audience::Table, $discreet->audienceOf(SignalKind::CheckIn));
    }

    public function testSignalsAreAnonymousUnlessTheTableChoseOtherwise(): void
    {
        $anonymous = TableSettings::defaults();
        $named = $anonymous->withNamedSignals(true);

        foreach (SignalKind::cases() as $kind) {
            self::assertFalse($anonymous->names($kind));
        }

        self::assertTrue($named->names(SignalKind::XCard));
        self::assertTrue($named->names(SignalKind::OpenDoor));
        self::assertTrue($named->names(SignalKind::FastForward));
        self::assertFalse($named->names(SignalKind::CheckIn));
        self::assertFalse($named->names(SignalKind::AllGood));
        self::assertFalse($named->names(SignalKind::NotOkay));
    }

    public function testEachWitherChangesOneSetting(): void
    {
        $template = Templates::get(Templates::ONLINE);
        $settings = TableSettings::defaults()
            ->withTools([SafetyTool::XCard])
            ->withNamedSignals(true)
            ->withSignalsSeenBy(Audience::GameMaster)
            ->withCustomThemes(['clowns' => 'Clowns'])
            ->withSessionZero($template);

        self::assertSame([SafetyTool::XCard], $settings->tools);
        self::assertTrue($settings->namedSignals);
        self::assertSame(Audience::GameMaster, $settings->signalsSeenBy);
        self::assertSame(['clowns' => 'Clowns'], $settings->customThemes);
        self::assertSame($template, $settings->sessionZero);
        self::assertEquals($settings, $settings->withTools([SafetyTool::XCard])->withNamedSignals(true)->withSignalsSeenBy(Audience::GameMaster)->withCustomThemes(['clowns' => 'Clowns'])->withSessionZero($template));
    }

    public function testTheAddedThemesAreBounded(): void
    {
        $themes = [];

        for ($i = 0; $i < 100; ++$i) {
            $themes['t'.$i] = 'T';
        }

        self::assertCount(100, new TableSettings(customThemes: $themes)->customThemes);

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A table adds at most 100 themes.');

        new TableSettings(customThemes: [...$themes, 'more' => 'More']);
    }

    public function testTheyAreStoredAsAnArray(): void
    {
        $settings = new TableSettings([SafetyTool::XCard, SafetyTool::OpenDoor], true, Audience::GameMaster, ['clowns' => 'Clowns'], Templates::get(Templates::ONE_SHOT));
        $stored = $settings->toArray();

        self::assertSame(['x-card', 'open-door'], $stored['tools']);
        self::assertTrue($stored['named_signals']);
        self::assertSame('game-master', $stored['signals_seen_by']);
        self::assertSame(['clowns' => 'Clowns'], $stored['custom_themes']);
        self::assertSame('one-shot', $stored['session_zero']['key']);
        self::assertEquals($settings, TableSettings::fromArray($stored));
        $decoded = json_decode(json_encode($stored, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertEquals($settings, TableSettings::fromArray($decoded));
    }

    public function testMissingStoredValuesTakeTheirDefaults(): void
    {
        $settings = TableSettings::fromArray([]);

        self::assertSame([], $settings->tools);
        self::assertFalse($settings->namedSignals);
        self::assertSame(Audience::Table, $settings->signalsSeenBy);
        self::assertSame([], $settings->customThemes);
        self::assertSame(Templates::CAMPAIGN, $settings->sessionZero->key);

        $odd = TableSettings::fromArray(['tools' => 'x-card', 'named_signals' => 'yes', 'signals_seen_by' => 'everyone', 'custom_themes' => 'none', 'session_zero' => 'campaign']);

        self::assertSame([], $odd->tools);
        self::assertFalse($odd->namedSignals);
        self::assertSame(Audience::Table, $odd->signalsSeenBy);
        self::assertSame(Audience::Table, TableSettings::fromArray(['signals_seen_by' => 3])->signalsSeenBy);
    }

    public function testAStoredToolMustBeKnown(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A stored tool is unknown.');

        TableSettings::fromArray(['tools' => ['x-card', 'safe-word']]);
    }

    public function testAStoredToolMustBeAText(): void
    {
        $this->expectException(InvalidAnswer::class);

        TableSettings::fromArray(['tools' => [1]]);
    }

    public function testAStoredThemeMustHaveALabel(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A stored theme has no label.');

        TableSettings::fromArray(['custom_themes' => ['clowns' => null]]);
    }
}
