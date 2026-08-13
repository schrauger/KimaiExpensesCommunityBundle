<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Controller;

use App\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\ExpenseCategoryType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseCategoryRepository;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/expenses/categories')]
#[IsGranted('manage_kimai_expenses_community_category')]
final class ExpenseCategoryController extends AbstractController
{
    public function __construct(
        private readonly ExpenseCategoryRepository $categories,
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'kimai_expenses_community_category', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@KimaiExpensesCommunity/category/index.html.twig', [
            'categories' => $this->categories->findBy([], ['name' => 'ASC']),
            'title' => 'Expense categories',
        ]);
    }

    #[Route('/create', name: 'kimai_expenses_community_category_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $category = new ExpenseCategory();
        $form = $this->createForm(ExpenseCategoryType::class, $category, [
            'action' => $this->generateUrl('kimai_expenses_community_category_create'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Category created.');
            return $this->redirectToRoute('kimai_expenses_community_category');
        }

        return $this->render('@KimaiExpensesCommunity/category/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'New expense category',
        ]);
    }

    #[Route('/{id}/edit', name: 'kimai_expenses_community_category_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $category = $this->findCategory($id);
        $form = $this->createForm(ExpenseCategoryType::class, $category, [
            'action' => $this->generateUrl('kimai_expenses_community_category_edit', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Category updated.');
            return $this->redirectToRoute('kimai_expenses_community_category');
        }

        return $this->render('@KimaiExpensesCommunity/category/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit expense category',
        ]);
    }

    #[Route('/{id}/delete', name: 'kimai_expenses_community_category_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $category = $this->findCategory($id);

        if (!$this->isCsrfTokenValid('delete-category-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->expenses->countByCategory($category) > 0) {
            $this->addFlash('danger', 'This category cannot be deleted because expenses already use it. Hide it instead.');
            return $this->redirectToRoute('kimai_expenses_community_category');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'Category deleted.');
        return $this->redirectToRoute('kimai_expenses_community_category');
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
