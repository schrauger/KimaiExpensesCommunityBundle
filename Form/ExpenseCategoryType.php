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
            ->add('priceEntered', CheckboxType::class, [
                'required' => false,
                'label' => 'Enter the price on each expense',
                'help' => 'Tick for receipts and other variable amounts: people type the amount when they add an expense. Leave unticked for a fixed rate such as mileage, taken from "Default cost".',
            ])
            ->add('defaultCost', NumberType::class, [
                'required' => false,
                'label' => 'Default cost per unit',
                'scale' => 4,
                'html5' => true,
                'help' => 'The fixed rate (for example 0.70 per mile). When the price is entered per expense this is only an optional prefill; leave it 0 for none.',
            ])
            ->add('askQuantity', CheckboxType::class, [
                'required' => false,
                'label' => 'Ask for a quantity',
                'help' => 'Show the quantity field by default (miles, nights, litres ...). Otherwise it stays in Extended settings and is 1.',
            ])
            ->add('unit', TextType::class, [
                'required' => false,
                'label' => 'Unit label',
                'help' => 'Optional, e.g. Miles or Nights. Used as the quantity field\'s label and shown after quantities in lists. Empty shows "Quantity".',
                'attr' => ['maxlength' => 30],
            ])
            ->add('color', TextType::class, [
                'required' => false,
                'label' => 'Color',
                'help' => 'Optional hex colour, e.g. #3b82f6. Shown as a dot next to the category in lists.',
                'attr' => ['placeholder' => '#3b82f6', 'maxlength' => 7],
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
            ])
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => 'Visible to users',
                'help' => 'Hide a category instead of deleting it once expenses use it.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseCategory::class,
        ]);
    }
}
