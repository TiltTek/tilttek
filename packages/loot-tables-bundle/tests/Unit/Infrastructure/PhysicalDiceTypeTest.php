<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\Reading\CatalogReader;
use Tilttek\LootTablesBundle\Infrastructure\Form\PhysicalDiceType;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(PhysicalDiceType::class)]
final class PhysicalDiceTypeTest extends TestCase
{
    public function testOneFieldPerUnitWithTheTablesOfTheBox(): void
    {
        $form = $this->form(Shop::catalog()->box('starter'), 2);

        self::assertSame(['unit_1', 'unit_2'], array_keys($form->all()));
        $options = $form->get('unit_2')->getConfig()->getOptions();
        self::assertSame('physical_dice.unit', $options['label']);
        self::assertSame(['%unit%' => 2], $options['label_translation_parameters']);
        self::assertSame('physical_dice.help', $options['help']);
        self::assertSame(['%tables%' => 'Rings, Hats', '%dice%' => 2], $options['help_translation_parameters']);
        self::assertSame('tilttek_loot_tables', $options['translation_domain']);
        self::assertTrue($options['trim']);
        self::assertTrue($options['required']);
        self::assertSame(['minlength' => 2, 'maxlength' => 2, 'size' => 2, 'pattern' => '[123456]{2}', 'inputmode' => 'numeric', 'autocomplete' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false'], $options['attr']);
        self::assertSame('tilttek_loot_tables', $form->getConfig()->getOption('translation_domain'));

        $view = $form->createView();
        self::assertSame('Starter box', $view->vars['loot_box']);
        self::assertSame(['Rings', 'Hats'], $view->vars['loot_tables']);
    }

    public function testADieOfMoreThanNineSidesIsTypedInLetters(): void
    {
        $options = $this->form(Shop::catalog()->box('giant'), 1)->get('unit_1')->getConfig()->getOptions();

        self::assertSame(['minlength' => 1, 'maxlength' => 1, 'size' => 2, 'pattern' => '[123456789ABCDEFGHIJKabcdefghijk]{1}', 'inputmode' => 'text', 'autocomplete' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false'], $options['attr']);
    }

    public function testADieOfNineSidesIsStillTypedInDigits(): void
    {
        $nine = ['name' => 'D9', 'entries' => array_map(static fn (int $face): array => ['key' => 'f'.$face, 'name' => 'Face '.$face], range(1, 9))];
        $box = new CatalogReader()->read(['tables' => ['d9' => $nine], 'boxes' => ['nine' => ['name' => 'Nine box', 'tables' => ['d9']]]])->box('nine');

        $attr = $this->form($box, 1)->get('unit_1')->getConfig()->getOption('attr');

        self::assertIsArray($attr);
        self::assertSame('numeric', $attr['inputmode']);
        self::assertSame('[123456789]{1}', $attr['pattern']);
    }

    public function testTheCodesAreTrimmedWhateverTheFieldHolds(): void
    {
        $form = $this->form(Shop::catalog()->box('starter'), 3);
        $form->get('unit_1')->setData('  36 ');
        $form->get('unit_2')->setData(null);
        $form->get('unit_3')->setData("\t11\n");

        self::assertSame(['36', '', '11'], PhysicalDiceType::codes($form));
    }

    public function testTheBoxMustBeABox(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(PhysicalDiceType::class, null, ['box' => 'starter', 'units' => 1]);
    }

    public function testTheUnitsMustBeAnInteger(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(PhysicalDiceType::class, null, ['box' => Shop::catalog()->box('starter'), 'units' => '3']);
    }

    public function testTheCodesOfASubmittedFormInTheOrderOfTheUnits(): void
    {
        $form = $this->form(Shop::catalog()->box('starter'), 3);
        $form->submit(['unit_1' => ' 36 ', 'unit_2' => '11', 'unit_3' => '25']);

        self::assertTrue($form->isValid());
        self::assertSame(['36', '11', '25'], PhysicalDiceType::codes($form));
    }

    public function testAFieldLeftOutCountsAsAnEmptyCode(): void
    {
        $form = $this->form(Shop::catalog()->box('starter'), 2);
        $form->submit(['unit_1' => '36']);

        self::assertSame(['36', ''], PhysicalDiceType::codes($form));
        self::assertFalse($form->isValid());
        self::assertSame([], $this->errors($form->get('unit_1')));
        self::assertSame(['"" is not the dice of this box: one character per die, 2 dice.'], $this->errors($form->get('unit_2')));
    }

    public function testACodeThatDoesNotReadIsRefusedOnItsField(): void
    {
        $form = $this->form(Shop::catalog()->box('starter'), 2);
        $form->submit(['unit_1' => '17', 'unit_2' => '111']);

        self::assertFalse($form->isValid());
        self::assertSame(['"17" is not the dice of this box: one character per die, 2 dice.'], $this->errors($form->get('unit_1')));
        self::assertSame(['"111" is not the dice of this box: one character per die, 2 dice.'], $this->errors($form->get('unit_2')));
        $error = $form->get('unit_1')->getErrors()[0];
        self::assertInstanceOf(FormError::class, $error);
        self::assertSame('physical_dice.invalid', $error->getMessageTemplate());
        self::assertSame(['%code%' => '17', '%dice%' => 2], $error->getMessageParameters());
        self::assertSame([], $this->errors($form));
    }

    public function testTheErrorIsTranslated(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 3).'/translations/tilttek_loot_tables.fr.yaml', 'fr', 'tilttek_loot_tables');

        $form = $this->factory(new PhysicalDiceType($translator))->create(PhysicalDiceType::class, null, ['box' => Shop::catalog()->box('starter'), 'units' => 1]);
        $form->submit(['unit_1' => 'x']);

        self::assertSame(['« x » n’est pas un jet des dés de cette boîte : un caractère par dé, 2 dés.'], $this->errors($form->get('unit_1')));
    }

    public function testTheBoxAndTheUnitsAreRequired(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->factory()->create(PhysicalDiceType::class, null, ['units' => 1]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unitsOutOfBounds(): iterable
    {
        yield 'none' => [0];
        yield 'more than a hundred' => [PhysicalDiceType::MAX_UNITS + 1];
    }

    #[DataProvider('unitsOutOfBounds')]
    public function testTheUnitsAreBounded(int $units): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->form(Shop::catalog()->box('starter'), $units);
    }

    public function testAHundredUnitsAreAsked(): void
    {
        self::assertCount(100, $this->form(Shop::catalog()->box('starter'), 100)->all());
        self::assertSame(100, PhysicalDiceType::MAX_UNITS);
        self::assertSame('tilttek_loot_tables', PhysicalDiceType::DOMAIN);
    }

    /**
     * @return FormInterface<array<string, string|null>|null>
     */
    private function form(LootBox $box, int $units): FormInterface
    {
        return $this->factory()->create(PhysicalDiceType::class, null, ['box' => $box, 'units' => $units]);
    }

    private function factory(PhysicalDiceType $type = new PhysicalDiceType()): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType($type)->getFormFactory();
    }

    /**
     * @template T
     *
     * @param FormInterface<T> $form
     *
     * @return list<string>
     */
    private function errors(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        return $errors;
    }
}
