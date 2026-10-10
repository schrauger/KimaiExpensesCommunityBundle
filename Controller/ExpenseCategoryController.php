<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Controller;

use App\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\ExpenseCategoryType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseCategoryRepository;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use KimaiPlugin\KimaiExpensesCommunityBundle\Security\ExpensePermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/expenses/categories')]
#[IsGranted(ExpensePermissions::MANAGE_CATEGORY)]
final class ExpenseCategoryController extends AbstractController
{
    use ModalFormTrait;

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
        return $this->processForm($request, new ExpenseCategory(), true);
    }

    #[Route('/{id}/edit', name: 'kimai_expenses_community_category_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        return $this->processForm($request, $this->findCategory($id), false);
    }

    #[Route('/{id}/delete', name: 'kimai_expenses_community_category_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $category = $this->findCategory($id);

        if (!$this->isCsrfTokenValid('delete-category-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->expenses->countByCategory($category) > 0) {
            $this->addFlash('error', 'This category cannot be deleted because expenses already use it. Hide it instead.');
            return $this->redirectToRoute('kimai_expenses_community_category');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'Category deleted.');
        return $this->redirectToRoute('kimai_expenses_community_category');
    }

    /**
     * Shared by create and edit. Works like Kimai's own controllers so that
     * Kimai's modal-ajax-form handling applies: AJAX requests get only the
     * modal markup, a valid submit redirects, an invalid submit re-renders.
     */
    private function processForm(Request $request, ExpenseCategory $category, bool $isNew): Response
    {
        $modal = $this->isModalRequest($request);
        $params = $modal ? ['modal' => 1] : [];
        $action = $isNew
            ? $this->generateUrl('kimai_expenses_community_category_create', $params)
            : $this->generateUrl('kimai_expenses_community_category_edit', ['id' => $category->getId()] + $params);

        $form = $this->createForm(ExpenseCategoryType::class, $category, ['action' => $action]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $this->entityManager->persist($category);
            }
            $this->entityManager->flush();

            $this->addFlash('success', $isNew ? 'Category created.' : 'Category updated.');

            $listUrl = $this->generateUrl('kimai_expenses_community_category');

            return $modal ? $this->modalSaved($listUrl) : $this->redirect($listUrl);
        }

        $template = $modal
            ? '@KimaiExpensesCommunity/category/modal.html.twig'
            : '@KimaiExpensesCommunity/category/form.html.twig';

        return $this->render($template, [
            'form' => $form->createView(),
            'title' => $isNew ? 'New expense category' : 'Edit expense category',
        ]);
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
