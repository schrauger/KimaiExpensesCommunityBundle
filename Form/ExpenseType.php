<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Form\Type\DateTimePickerType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canEditCost = (bool) $options['can_edit_cost'];
        $canEditExport = (bool) $options['can_edit_export'];
        $timezone = (string) $options['timezone'];
        $userTimezone = new \DateTimeZone($timezone);
        $utc = new \DateTimeZone('UTC');

        // NOTE: Symfony submits fields in the order they are added here, not in
        // the order they appear on screen. Expense::setCategory() copies the
        // category's default cost onto the expense, so "category" must stay
        // BEFORE "cost" or that copy would overwrite a cost the user just typed.
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
                // The customer id on each option lets the form narrow this list
                // to the selected customer and auto-select a lone project.
                'choice_attr' => static function (Project $project): array {
                    return ['data-customer' => (string) ($project->getCustomer()?->getId() ?? '')];
                },
            ])
            ->add('activity', EntityType::class, [
                'class' => Activity::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
            ])
            ->add('category', EntityType::class, [
                'class' => ExpenseCategory::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                // Add the default rate to each option so the small bit of
                // client-side code can update the displayed rate immediately.
                'choice_attr' => static function (ExpenseCategory $category): array {
                    return [
                        'data-default-cost' => $category->getDefaultCost(),
                        // Used by the form script: help text under the field, and a
                        // default description for an empty description box.
                        'data-help' => (string) $category->getHelpText(),
                        'data-description' => (string) $category->getDescription(),
                    ];
                },
                'query_builder' => static function ($repository) {
                    return $repository->createQueryBuilder('category')
                        ->andWhere('category.visible = :visible')
                        ->setParameter('visible', true)
                        ->orderBy('category.name', 'ASC');
                },
            ])
            ->add('quantity', TrimmedDecimalType::class, [
                'label' => 'Quantity',
            ])
            // Kimai's DateTimePickerType passes model_timezone/view_timezone on
            // to its inner date and time fields as well. With two different
            // timezones (UTC model, user view) the conversion is applied once by
            // the parent and again by the date child, which pushed the date back
            // a day for users west of UTC. Kimai's own forms use the same
            // timezone for both, so do that here and convert UTC <-> user
            // timezone in the model transformer below.
            ->add('date', DateTimePickerType::class, [
                'label' => 'Date and time',
                'model_timezone' => $timezone,
                'view_timezone' => $timezone,
            ])
            ->add('billable', CheckboxType::class, [
                'required' => false,
                'label' => 'Billable',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('cost', TrimmedDecimalType::class, [
                'label' => 'Cost per unit',
                // Show at least 2 decimals (1.00), more only when the rate needs them.
                'min_decimals' => 2,
                // Disabled for normal users. The server also enforces the
                // permission; disabling this field is only a UI convenience.
                'disabled' => !$canEditCost,
            ])
            ->add('exported', CheckboxType::class, [
                'required' => false,
                'label' => 'Exported',
                'help' => 'Untick to make this expense available for invoicing again.',
                // A disabled field never changes the stored value.
                'disabled' => !$canEditExport,
            ]);

        // The entity stores the date as UTC. Hand the picker the same instant in
        // the user's timezone, and convert back to UTC on submit.
        $builder->get('date')->addModelTransformer(new CallbackTransformer(
            static fn (?\DateTimeInterface $value): ?\DateTime => $value === null
                ? null
                : \DateTime::createFromInterface($value)->setTimezone($userTimezone),
            static fn (?\DateTimeInterface $value): ?\DateTime => $value === null
                ? null
                : \DateTime::createFromInterface($value)->setTimezone($utc),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'can_edit_cost' => false,
            'can_edit_export' => false,
            'timezone' => date_default_timezone_get(),
        ]);

        $resolver->setAllowedTypes('can_edit_cost', 'bool');
        $resolver->setAllowedTypes('can_edit_export', 'bool');
        $resolver->setAllowedTypes('timezone', 'string');
    }
}
