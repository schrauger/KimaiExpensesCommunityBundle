<?php

declare(strict_types=1);

namespace KimaiPlugin\MileageExpenseBundle\Form;

use KimaiPlugin\MileageExpenseBundle\Entity\ExpenseCategory;
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
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => 'Visible to users',
            ])
            ->add('helpText', TextareaType::class, [
                'required' => false,
                'label' => 'Help text',
                'help' => 'Displayed when creating an expense.',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseCategory::class,
        ]);
    }
}
