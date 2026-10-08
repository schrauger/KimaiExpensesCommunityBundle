<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
            ])
            ->add('unit', TextType::class, [
                'label' => 'Unit',
                'help' => 'Examples: mile, km, item, night.',
            ])
            ->add('defaultCost', NumberType::class, [
                'label' => 'Default cost per unit',
                'scale' => 4,
                'html5' => true,
            ])
            ->add('color', TextType::class, [
                'required' => false,
                'label' => 'Color',
                'help' => 'Optional hex colour, e.g. #3b82f6. Shown as a dot next to the category in lists.',
                'attr' => ['placeholder' => '#3b82f6', 'maxlength' => 7],
            ])
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => 'Visible to users',
                'help' => 'Hide a category instead of deleting it once expenses use it.',
            ])
            ->add('helpText', TextareaType::class, [
                'required' => false,
                'label' => 'Help text',
                'help' => 'Shown under the category field when someone enters an expense.',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'help' => 'Copied into the expense description when this category is chosen (only if that is still empty).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseCategory::class,
        ]);
    }
}
