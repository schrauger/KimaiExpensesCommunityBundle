<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DateTimePickerType;
use App\Form\Type\ProjectType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The expense editor.
 *
 * Layout and look come from Kimai's form theme, so the field order on screen is
 * decided by the Twig template (_form_fields.html.twig), not by the order below.
 * The one order that matters here is submit order: "category" must be added
 * BEFORE "cost", because Expense::setCategory() copies the category's default
 * cost, which would otherwise overwrite a cost the user just typed.
 */
final class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canEditExport = (bool) $options['can_edit_export'];
        $timezone = (string) $options['timezone'];
        $userTimezone = new \DateTimeZone($timezone);
        $utc = new \DateTimeZone('UTC');

        // Kimai's own pickers: grouped, colour-dotted, searchable lists whose
        // customer -> project -> activity selects reload each other through
        // Kimai's API (and select a lone project automatically), exactly like
        // the timesheet editor.
        $this->addKimaiPicker($builder, 'customer', CustomerType::class, [
            'required' => false,
            'placeholder' => '',
            'project_enabled' => true,
        ], [
            'class' => Customer::class,
            'choice_label' => 'name',
            'required' => false,
            'placeholder' => '— None —',
        ]);

        $this->addKimaiPicker($builder, 'project', ProjectType::class, [
            'required' => false,
            'placeholder' => '',
            'activity_enabled' => true,
            // Keep showing a project after it has ended, so old expenses stay editable.
            'ignore_date' => true,
        ], [
            'class' => Project::class,
            'choice_label' => 'name',
            'required' => false,
            'placeholder' => '— None —',
            'choice_attr' => static fn (Project $project): array => [
                'data-customer' => (string) ($project->getCustomer()?->getId() ?? ''),
            ],
        ]);

        $this->addKimaiPicker($builder, 'activity', ActivityType::class, [
            'required' => false,
            'placeholder' => '',
        ], [
            'class' => Activity::class,
            'choice_label' => 'name',
            'required' => false,
            'placeholder' => '— None —',
        ]);

        $builder
            ->add('category', EntityType::class, [
                'class' => ExpenseCategory::class,
                'choice_label' => 'name',
                'placeholder' => '',
                'label' => 'Category',
                'choice_attr' => static function (ExpenseCategory $category): array {
                    return [
                        // Read by the form script: the rate shown in "Extended settings",
                        // the help text under the field, and a default description.
                        'data-default-cost' => $category->getDefaultCost(),
                        'data-help' => (string) $category->getHelpText(),
                        'data-description' => (string) $category->getDescription(),
                        // How the editor adapts to the category (see the form script):
                        // quantity shown by default + its label, and price entered vs fixed.
                        'data-unit' => (string) $category->getUnit(),
                        'data-ask-quantity' => $category->isAskQuantity() ? '1' : '0',
                        'data-price-entered' => $category->isPriceEntered() ? '1' : '0',
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
            // Kimai's DateTimePickerType hands its timezone options to its inner date
            // and time fields too. With different model and view timezones the
            // conversion happened twice and pushed the date back a day, so both are the
            // user's timezone here and UTC <-> user timezone is converted below.
            ->add('date', DateTimePickerType::class, [
                'label' => 'Date and time',
                'model_timezone' => $timezone,
                'view_timezone' => $timezone,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            // The next three live in "Extended settings": by default an expense is
            // billable, not exported, and priced by its category.
            ->add('cost', TrimmedDecimalType::class, [
                'label' => 'Cost per unit',
                // Show at least 2 decimals (1.00), more only when the rate needs them.
                'min_decimals' => 2,
                // Never disabled here: for categories where the price is typed in, every
                // user must be able to enter it. For fixed-rate categories the form script
                // makes it read-only without the permission, and the controller enforces it.
            ])
            ->add('billable', CheckboxType::class, [
                'required' => false,
                'label' => 'Billable',
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

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Lets the template tell the form script whether this user may change a fixed rate.
        $view->vars['can_edit_cost'] = (bool) $options['can_edit_cost'];
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

    /**
     * Add one of Kimai's picker types, or a plain entity select when this Kimai
     * version does not accept the options (the picker classes are Kimai
     * internals and their options can change between releases).
     *
     * @param array<string, mixed> $options  options for Kimai's picker
     * @param array<string, mixed> $fallback options for the plain EntityType
     */
    private function addKimaiPicker(
        FormBuilderInterface $builder,
        string $name,
        string $pickerType,
        array $options,
        array $fallback,
    ): void {
        try {
            $builder->add($name, $pickerType, $options);
            // Children are resolved lazily; get() resolves it now so a bad option throws here.
            $builder->get($name);
        } catch (\Throwable) {
            $builder->add($name, EntityType::class, $fallback);
        }
    }
}
