<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\ExpenseToolbarType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\ExpenseType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Query\ExpenseQuery;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/expenses')]
final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** My expenses: always limited to the logged-in user. */
    #[Route('', name: 'kimai_expenses_community', methods: ['GET'])]
    #[IsGranted('view_kimai_expenses_community')]
    public function index(Request $request): Response
    {
        return $this->listExpenses($request, false);
    }

    /** All expenses: every user, with a User filter and column. */
    #[Route('/all', name: 'kimai_expenses_community_all', methods: ['GET'])]
    #[IsGranted('view_kimai_expenses_community')]
    #[IsGranted('view_other_timesheet')]
    public function all(Request $request): Response
    {
        return $this->listExpenses($request, true);
    }

    #[Route('/create', name: 'kimai_expenses_community_create', methods: ['GET', 'POST'])]
    #[IsGranted('create_kimai_expenses_community')]
    public function create(Request $request): Response
    {
        $expense = new Expense();
        $expense->setUser($this->getAuthenticatedUser());

        return $this->processForm($request, $expense, true);
    }

    #[Route('/{id}/edit', name: 'kimai_expenses_community_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_kimai_expenses_community')]
    public function edit(int $id, Request $request): Response
    {
        $expense = $this->findExpense($id);
        $this->assertUserCanModify($expense);

        return $this->processForm($request, $expense, false);
    }

    #[Route('/{id}/delete', name: 'kimai_expenses_community_delete', methods: ['POST'])]
    #[IsGranted('delete_kimai_expenses_community')]
    public function delete(int $id, Request $request): Response
    {
        $expense = $this->findExpense($id);
        $this->assertUserCanModify($expense);

        if (!$this->isCsrfTokenValid('delete-expense-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->entityManager->remove($expense);
        $this->entityManager->flush();

        $this->addFlash('success', 'Expense deleted.');

        return $this->redirect($this->resolveReturnUrl($request));
    }

    private function listExpenses(Request $request, bool $allUsers): Response
    {
        $user = $this->getAuthenticatedUser();
        $query = new ExpenseQuery();

        $toolbar = $this->createForm(ExpenseToolbarType::class, $query, [
            'include_user' => $allUsers,
        ]);
        $toolbar->handleRequest($request);

        if (!$allUsers) {
            // Applied after the toolbar is handled, so a crafted ?user=
            // parameter can never widen "My expenses" to other users.
            $query->setUser($user);
        }

        return $this->render('@KimaiExpensesCommunity/expense/index.html.twig', [
            'expenses' => $this->expenses->findByQuery($query, new \DateTimeZone($user->getTimezone())),
            'toolbar' => $toolbar->createView(),
            'show_user' => $allUsers,
            'reset_url' => $this->generateUrl($allUsers ? 'kimai_expenses_community_all' : 'kimai_expenses_community'),
            'title' => $allUsers ? 'All expenses' : 'My expenses',
            'timezone' => $user->getTimezone(),
        ]);
    }

    /**
     * Shared by create and edit. Renders the full page normally, and only the
     * modal content for AJAX requests. On success an AJAX request gets a JSON
     * redirect (the page then reloads and shows the flash message); an invalid
     * AJAX submit returns the form again with HTTP 422.
     */
    private function processForm(Request $request, Expense $expense, bool $isNew): Response
    {
        $user = $this->getAuthenticatedUser();
        $returnUrl = $this->resolveReturnUrl($request);
        $canEditCost = $this->isGranted('edit_kimai_expenses_community_cost');

        // Remember the persisted rate. A normal user is not allowed to submit
        // a changed cost, even if they manipulate the HTML form in a browser.
        $originalCost = $expense->getCost();

        $action = $isNew
            ? $this->generateUrl('kimai_expenses_community_create', ['returnUrl' => $returnUrl])
            : $this->generateUrl('kimai_expenses_community_edit', ['id' => $expense->getId(), 'returnUrl' => $returnUrl]);

        $form = $this->createForm(ExpenseType::class, $expense, [
            'action' => $action,
            'can_edit_cost' => $canEditCost,
            'timezone' => $user->getTimezone(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$canEditCost) {
                // The rate always comes from the server, never from the browser.
                $expense->setCost($isNew ? $expense->getCategory()->getDefaultCost() : $originalCost);
            }

            if ($isNew) {
                $this->entityManager->persist($expense);
            }
            $this->entityManager->flush();

            $this->addFlash('success', $isNew ? 'Expense created.' : 'Expense updated.');

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['redirect' => $returnUrl]);
            }

            return $this->redirect($returnUrl);
        }

        $template = $request->isXmlHttpRequest()
            ? '@KimaiExpensesCommunity/expense/modal.html.twig'
            : '@KimaiExpensesCommunity/expense/form.html.twig';

        return $this->render(
            $template,
            [
                'form' => $form->createView(),
                'title' => $isNew ? 'New expense' : 'Edit expense',
                'cancel_url' => $returnUrl,
            ],
            new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK)
        );
    }

    /**
     * Where to go after saving/deleting. Only same-site relative paths are
     * accepted, so the parameter cannot be used as an open redirect.
     */
    private function resolveReturnUrl(Request $request): string
    {
        $candidate = $request->query->get('returnUrl') ?? $request->request->get('returnUrl');

        if (
            \is_string($candidate)
            && $candidate !== ''
            && $candidate[0] === '/'
            && !str_starts_with($candidate, '//')
            && preg_match('/[\x00-\x1f\\\\]/', $candidate) !== 1
        ) {
            return $candidate;
        }

        return $this->generateUrl('kimai_expenses_community');
    }

    private function findExpense(int $id): Expense
    {
        $expense = $this->expenses->find($id);
        if (!$expense instanceof Expense) {
            throw $this->createNotFoundException('Expense not found.');
        }

        return $expense;
    }

    private function assertUserCanModify(Expense $expense): void
    {
        if ($expense->isExported() && !$this->isGranted('edit_exported_kimai_expenses_community')) {
            throw $this->createAccessDeniedException('Exported expenses cannot be changed.');
        }

        if (!$this->isGranted('view_other_timesheet')) {
            $currentUser = $this->getAuthenticatedUser();
            if ($expense->getUser()->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException();
            }
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('A logged-in Kimai user is required.');
        }

        return $user;
    }
}
