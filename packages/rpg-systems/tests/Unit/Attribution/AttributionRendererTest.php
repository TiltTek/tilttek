<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Attribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Localisation;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

#[CoversClass(AttributionRenderer::class)]
final class AttributionRendererTest extends TestCase
{
    private Provenance $srd;

    private Provenance $orc;

    private Provenance $public;

    private Provenance $own;

    protected function setUp(): void
    {
        $this->srd = new Provenance('SRD 5.2', Licence::CcBy40, 'This work includes material from the SRD 5.2 <by a publisher> & others.', url: 'https://example.org/srd');
        $this->orc = new Provenance('Open rules', Licence::Orc, 'Open rules, used under the ORC License.');
        $this->public = new Provenance('Public tables', Licence::Cc0, url: 'https://example.org/public');
        $this->own = new Provenance('Our notes', Licence::Owned);
    }

    public function testOnlyTheSourcesThatAskForAttributionAreShownEachOnce(): void
    {
        $renderer = new AttributionRenderer();
        $system = $this->system();

        self::assertSame([$this->srd, $this->orc], $renderer->sources($system));
        self::assertSame([$this->own, $this->srd, $this->public, $this->orc], $renderer->sources($system, all: true));
        self::assertSame([$this->orc], $renderer->sources([$this->orc, $this->own, $this->orc]));
        self::assertSame([], $renderer->sources([$this->own, $this->public]));
        self::assertSame(
            "This work includes material from the SRD 5.2 <by a publisher> & others. (Creative Commons Attribution 4.0 International: https://creativecommons.org/licenses/by/4.0/)\n"
            .'Open rules, used under the ORC License. (ORC License: https://paizo.com/orclicense)',
            $renderer->text($system),
        );
        self::assertSame(
            "Our notes (All rights reserved)\n"
            .'Public tables <https://example.org/public> (Creative Commons Zero 1.0 Universal: https://creativecommons.org/publicdomain/zero/1.0/)',
            $renderer->text([$this->own, $this->public], all: true),
        );
        self::assertSame('', $renderer->text([$this->own]));
    }

    public function testTheHtmlListEscapesEveryTextAndLinksTheLicence(): void
    {
        $renderer = new AttributionRenderer();

        self::assertSame(
            "<ul class=\"attribution\">\n"
            .'<li>This work includes material from the SRD 5.2 &lt;by a publisher&gt; &amp; others. (<a href="https://creativecommons.org/licenses/by/4.0/" rel="license">Creative Commons Attribution 4.0 International</a>)</li>'."\n"
            .'<li>Open rules, used under the ORC License. (<a href="https://paizo.com/orclicense" rel="license">ORC License</a>)</li>'
            ."\n</ul>",
            $renderer->html($this->system()),
        );
        self::assertSame(
            "<ul class=\"attribution\">\n"
            .'<li><a href="https://example.org/public">Public tables</a> (<a href="https://creativecommons.org/publicdomain/zero/1.0/" rel="license">Creative Commons Zero 1.0 Universal</a>)</li>'."\n"
            .'<li>Our notes &quot;quoted&quot; (All rights reserved)</li>'
            ."\n</ul>",
            $renderer->html([$this->public, new Provenance('Our notes "quoted"', Licence::Owned)], all: true),
        );
        self::assertSame('', $renderer->html([$this->own]));
    }

    public function testTheTranslationsShownAreThoseOfTheLocale(): void
    {
        $translator = new Provenance('Traduction', Licence::CcBy40, 'Traduction française, CC BY 4.0.');
        $system = $this->system()->localised(new Localisation('fr', $translator, 'Système'));
        $renderer = new AttributionRenderer();

        self::assertSame([$this->srd, $this->orc, $translator], $renderer->sources($system));
        self::assertSame([$this->srd, $this->orc, $translator], $renderer->sources($system, 'fr_CA'));
        self::assertSame([$this->srd, $this->orc], $renderer->sources($system, 'en'));
        self::assertStringNotContainsString('Traduction', $renderer->text($system, 'de'));
        self::assertStringContainsString('Traduction', $renderer->html($system, 'fr'));
    }

    private function system(): GameSystem
    {
        $names = Translations::of(['en' => 'X']);

        return new GameSystem('x', $names, $this->own, terms: [
            new Term('a', $names, $this->srd),
            new Term('b', $names, $this->public),
            new Term('c', $names, $this->srd),
            new Term('d', $names, $this->orc),
        ]);
    }
}
