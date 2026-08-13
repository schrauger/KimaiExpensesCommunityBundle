<?php

declare(strict_types=1);

namespace KimaiPlugin\MileageExpenseBundle\Controller;

use App\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\MileageExpenseBundle\Entity\ExpenseCategory;
use KimaiPlugin\MileageExpenseBundle\Form\ExpenseCategoryType;
use KimaiPlugin\MileageExpenseBundle\Repository\ExpenseCategoryRepository;
use KimaiPlugin\MileageExpenseBundle\Repository\ExpenseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/expenses/categories')]
#[IsGranted('manage_mileage_expense_category')]
final class ExpenseCategoryController extends AbstractController
{
    public function __construct(
        private readonly ExpenseCategoryRepository $categories,
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'mileage_expense_category', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@MileageExpense/category/index.html.twig', [
            'categories' => $this->categories->findBy([], ['name' => 'ASC']),
            'title' => 'Expense categories',
        ]);
    }

    #[Route('/create', name: 'mileage_expense_category_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $category = new ExpenseCategory();
        $form = $this->createForm(ExpenseCategoryType::class, $category, [
            'action' => $this->generateUrl('mileage_expense_category_create'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Category created.');
            return $this->redirectToRoute('mileage_expense_category');
        }

        return $this->render('@MileageExpense/category/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'New expense category',
        ]);
    }

    #[Route('/{id}/edit', name: 'mileage_expense_category_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $category = $this->findCategory($id);
        $form = $this->createForm(ExpenseCategoryType::class, $category, [
            'action' => $this->generateUrl('mileage_expense_category_edit', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Category updated.');
            return $this->redirectToRoute('mileage_expense_category');
        }

        return $this->render('@MileageExpense/category/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit expense category',
        ]);
    }

    #[Route('/{id}/delete', name: 'mileage_expense_category_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $category = $this->findCategory($id);

        if (!$this->isCsrfTokenValid('delete-category-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->expenses->countByCategory($category) > 0) {
            $this->addFlash('danger', 'This category cannot be deleted because expenses already use it. Hide it instead.');
            return $this->redirectToRoute('mileage_expense_category');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'Category deleted.');
        return $this->redirectToRoute('mileage_expense_category');
    }

    private function findCategory(int $id): ExpenseCategory
    {
        $category = $this->categories->find($id);
        if (!$category instanceof ExpenseCategory) {
            throw $this->createNotFoundException('Expense category not found.');
        }

        return $category;
    }
}
