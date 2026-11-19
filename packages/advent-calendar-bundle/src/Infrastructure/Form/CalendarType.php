<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;

/**
 * The form a site edits a calendar with, on a `CalendarRecord`: its identifier — fixed once the calendar exists, since
 * the openings are kept under it —, its title, timezone, first and closing dates, opening policy, publication and the
 * surprise of each box. The whole is checked by the library when it is submitted, and its refusal shown on the form.
 *
 * @extends AbstractType<CalendarRecord>
 */
final class CalendarType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', TextType::class, ['label' => 'form.calendar.id', 'empty_data' => '', 'disabled' => $options['existing'], 'attr' => ['maxlength' => 64, 'pattern' => '[a-z0-9][a-z0-9_.\-]{0,63}']])
            ->add('title', TextType::class, ['label' => 'form.calendar.title', 'empty_data' => '', 'attr' => ['maxlength' => 255]])
            ->add('timezone', TimezoneType::class, ['label' => 'form.calendar.timezone', 'empty_data' => 'UTC'])
            ->add('firstDate', DateType::class, ['label' => 'form.calendar.first_date', 'widget' => 'single_text', 'input' => 'string', 'input_format' => 'Y-m-d'])
            ->add('closingDate', DateType::class, ['label' => 'form.calendar.closing_date', 'widget' => 'single_text', 'input' => 'string', 'input_format' => 'Y-m-d', 'required' => false])
            ->add('policy', EnumType::class, ['label' => 'form.calendar.policy', 'class' => PolicyKind::class, 'empty_data' => PolicyKind::UntilClosing->value, 'choice_label' => static fn (PolicyKind $kind): string => 'policy.'.$kind->value])
            ->add('catchUpDays', IntegerType::class, ['label' => 'form.calendar.catch_up_days', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => OpeningPolicy::MAX_CATCH_UP_DAYS]])
            ->add('published', CheckboxType::class, ['label' => 'form.calendar.published', 'required' => false])
            ->add('surprises', CollectionType::class, [
                'label' => 'form.calendar.surprises',
                'entry_type' => SurpriseType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => ['data-max' => Calendar::MAX_DAYS],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, $this->check(...));

        // A first date left empty is an empty text, which the library refuses with its reason, rather than a null the
        // record cannot hold.
        $builder->get('firstDate')->addModelTransformer(new CallbackTransformer(
            static fn (?string $date): ?string => '' === $date ? null : $date,
            static fn (?string $date): string => $date ?? '',
        ));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CalendarRecord::class, 'existing' => false, 'translation_domain' => 'tilttek_advent_calendar']);
        $resolver->setAllowedTypes('existing', 'bool');
    }

    /**
     * The calendar checked as a whole, once its fields are read: the library's refusal becomes the form's error.
     */
    private function check(FormEvent $event): void
    {
        $record = $event->getData();

        if (!$record instanceof CalendarRecord || !$event->getForm()->isValid()) {
            return;
        }

        $record->surprises = array_values($record->surprises);

        try {
            $record->toCalendar();
        } catch (InvalidCalendar $invalidCalendar) {
            $event->getForm()->addError(new FormError($invalidCalendar->getMessage()));
        }
    }
}
