<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use KimaiPlugin\KimaiExpensesCommunityBundle\Query\ExpenseQuery;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Filter toolbar for the expense overview. Submitted with GET and an empty
 * block prefix, so URLs stay readable (?begin=2026-09-01&customer=3).
 */
final class ExpenseToolbarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('searchTerm', SearchType::class, [
                'required' => false,
                'label' => 'Search',
                'attr' => ['placeholder' => 'Description or category'],
            ])
            // Plain calendar dates: UTC on both sides so the form itself never
            // shifts the day. The repository applies the user's timezone.
            ->add('begin', DateType::class, [
                'required' => false,
                'label' => 'From',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'UTC',
            ])
            ->add('end', DateType::class, [
                'required' => false,
                'label' => 'To',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'UTC',
            ])
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Customer',
                'placeholder' => 'All',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Project',
                'placeholder' => 'All',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('p')->orderBy('p.name', 'ASC'),
                'choice_attr' => static function (Project $project): array {
                    return ['data-customer' => (string) ($project->getCustomer()?->getId() ?? '')];
                },
            ])
            ->add('activity', EntityType::class, [
                'class' => Activity::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Activity',
                'placeholder' => 'All',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('a')->orderBy('a.name', 'ASC'),
            ])
            ->add('category', EntityType::class, [
                'class' => ExpenseCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Category',
                'placeholder' => 'All',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('cat')->orderBy('cat.name', 'ASC'),
            ])
            ->add('billable', ChoiceType::class, [
                'required' => false,
                'label' => 'Billable',
                'placeholder' => 'All',
                'choices' => ['Billable' => 'yes', 'Not billable' => 'no'],
            ])
            ->add('exported', ChoiceType::class, [
                'required' => false,
                'label' => 'Exported',
                'placeholder' => 'All',
                'choices' => ['Exported' => 'yes', 'Not exported' => 'no'],
            ]);

        if ($options['include_user']) {
            $builder->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'displayName',
                'required' => false,
                'label' => 'User',
                'placeholder' => 'All',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('u')->orderBy('u.username', 'ASC'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseQuery::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
            'include_user' => false,
        ]);

        $resolver->setAllowedTypes('include_user', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
