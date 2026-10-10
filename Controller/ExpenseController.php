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
use KimaiPlugin\KimaiExpensesCommunityBundle\Security\ExpensePermissions;
use KimaiPlugin\KimaiExpensesCommunityBundle\Service\ExpenseAccessChecker;
use KimaiPlugin\KimaiExpensesCommunityBundle\Service\ExpenseCsvExporter;
use KimaiPlugin\KimaiExpensesCommunityBundle\Service\ReceiptStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Expense overview (My / All), create, edit, copy, delete and the bulk export-state action.
 *
 * Create, edit and copy behave like Kimai's own controllers so that Kimai's
 * "modal-ajax-form" links work: AJAX requests get only the modal markup, a
 * valid submit redirects, and an invalid submit returns the form again (200).
 */
#[Route('/expenses')]
final class ExpenseController extends AbstractController
{
    use ModalFormTrait;

    /** Rows per page in the overview. */
    private const PER_PAGE = 50;

    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExpenseAccessChecker $access,
        private readonly ReceiptStorage $receipts,
        private readonly ExpenseCsvExporter $csvExporter,
    ) {
    }

    /** My expenses: always limited to the logged-in user. */
    #[Route('', name: 'kimai_expenses_community', methods: ['GET'])]
    #[IsGranted(ExpensePermissions::VIEW)]
    public function index(Request $request): Response
    {
        return $this->listExpenses($request, false);
    }

    /** All expenses: every user, with a User filter and column. */
    #[Route('/all', name: 'kimai_expenses_community_all', methods: ['GET'])]
    #[IsGranted(ExpensePermissions::VIEW)]
    #[IsGranted(ExpensePermissions::VIEW_OTHER)]
    public function all(Request $request): Response
    {
        return $this->listExpenses($request, true);
    }

    #[Route('/create', name: 'kimai_expenses_community_create', methods: ['GET', 'POST'])]
    #[IsGranted(ExpensePermissions::CREATE)]
    public function create(Request $request): Response
    {
        $expense = new Expense();
        $expense->setUser($this->getAuthenticatedUser());

        return $this->processForm($request, $expense, true);
    }

    /**
     * "Create copy": opens the create form pre-filled from an existing expense.
     * The form posts to the normal create route, so nothing else is needed.
     */
    #[Route('/{id}/duplicate', name: 'kimai_expenses_community_duplicate', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ExpensePermissions::CREATE)]
    public function duplicate(int $id, Request $request): Response
    {
        $source = $this->findExpense($id);

        if (!$this->access->canView($source)) {
            throw $this->createAccessDeniedException();
        }

        return $this->processForm(
            $request,
            $source->duplicateFor($this->getAuthenticatedUser()),
            true,
            'Create copy'
        );
    }

    #[Route('/{id}/edit', name: 'kimai_expenses_community_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ExpensePermissions::EDIT)]
    public function edit(int $id, Request $request): Response
    {
        $expense = $this->findExpense($id);
        $this->assertUserCanModify($expense);

        return $this->processForm($request, $expense, false);
    }

    #[Route('/{id}/delete', name: 'kimai_expenses_community_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExpensePermissions::DELETE)]
    public function delete(int $id, Request $request): Response
    {
        $expense = $this->findExpense($id);
        $this->assertUserCanModify($expense);

        if (!$this->isCsrfTokenValid('delete-expense-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Delete the receipt file only after the database row is gone, so a
        // failed delete never leaves an expense pointing at a missing file.
        $receiptFile = $expense->getReceiptFilename();

        $this->entityManager->remove($expense);
        $this->entityManager->flush();

        $this->receipts->delete($receiptFile);

        $this->addFlash('success', 'Expense deleted.');

        return $this->redirect($this->resolveReturnUrl($request));
    }

    /**
     * Batch update of the export state ("Mark as exported / not exported"),
     * the equivalent of Kimai's batch update for timesheets.
     *
     * Like Kimai, changing the state needs the "edit export" permission, and
     * records that are already exported are locked unless the user also has
     * the "edit exported" permission.
     */
    #[Route('/export-state', name: 'kimai_expenses_community_export_state', methods: ['POST'])]
    #[IsGranted(ExpensePermissions::VIEW)]
    #[IsGranted(ExpensePermissions::EDIT_EXPORT)]
    public function exportState(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('expense-export-state', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $returnUrl = $this->resolveReturnUrl($request);
        $target = $request->request->get('state') === 'exported';
        $ids = array_values(array_filter(array_map('intval', $request->request->all('ids'))));

        if ($ids === []) {
            $this->addFlash('error', 'No expenses selected.');

            return $this->redirect($returnUrl);
        }

        $changed = 0;
        $skipped = 0;

        foreach ($this->expenses->findBy(['id' => $ids]) as $expense) {
            if (!$this->access->canModify($expense)) {
                ++$skipped;
                continue;
            }

            if ($expense->isExported() !== $target) {
                $expense->setExported($target);
                ++$changed;
            }
        }

        $this->entityManager->flush();

        if ($changed > 0) {
            $this->addFlash('success', sprintf(
                '%d expense(s) marked as %s.',
                $changed,
                $target ? 'exported' : 'not exported'
            ));
        }

        if ($skipped > 0) {
            $this->addFlash('error', sprintf(
                '%d expense(s) could not be changed (locked as exported, or owned by another user).',
                $skipped
            ));
        }

        return $this->redirect($returnUrl);
    }

    /**
     * Shared by "My expenses" and "All expenses". Handles the filter toolbar,
     * the CSV download (?export=csv) and pagination (?page=N).
     */
    private function listExpenses(Request $request, bool $allUsers): Response
    {
        $user = $this->getAuthenticatedUser();
        $timezone = new \DateTimeZone($user->getTimezone());
        $query = new ExpenseQuery();

        $toolbar = $this->createForm(ExpenseToolbarType::class, $query, [
            'include_user' => $allUsers,
        ]);
        $toolbar->handleRequest($request);

        // Counted before the owner is forced below, so "My expenses" shows no filter badge.
        $filterCount = $query->countFilter($allUsers);

        if (!$allUsers) {
            // Applied after the toolbar is handled, so a crafted ?user=
            // parameter can never widen "My expenses" to other users.
            $query->setUser($user);
        }

        if ($request->query->get('export') === 'csv') {
            $this->denyAccessUnlessGranted(ExpensePermissions::EXPORT);

            return $this->csvExporter->createResponse(
                $this->expenses->findByQuery($query, $timezone),
                $timezone,
                $allUsers
            );
        }

        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $this->expenses->paginate($query, $timezone, $page, self::PER_PAGE);
        $totalCount = \count($paginator);
        $pages = max(1, (int) ceil($totalCount / self::PER_PAGE));

        if ($page > $pages) {
            // A stale ?page= after filtering: show the last page instead of an empty one.
            $page = $pages;
            $paginator = $this->expenses->paginate($query, $timezone, $page, self::PER_PAGE);
        }

        $rows = iterator_to_array($paginator, false);

        // Per-row lock state, so locked rows are not offered Edit / Delete.
        $editable = [];
        foreach ($rows as $expense) {
            $editable[$expense->getId()] = $this->access->canModify($expense);
        }

        $listRoute = $allUsers ? 'kimai_expenses_community_all' : 'kimai_expenses_community';
        $canChangeExport = $this->isGranted(ExpensePermissions::EDIT_EXPORT);
        $columns = $this->tableColumns($allUsers, $canChangeExport);

        return $this->render('@KimaiExpensesCommunity/expense/index.html.twig', [
            'expenses' => $rows,
            'editable' => $editable,
            'toolbar' => $toolbar->createView(),
            'filter_count' => $filterCount,
            'show_user' => $allUsers,
            'can_change_export' => $canChangeExport,
            'can_export' => $this->isGranted(ExpensePermissions::EXPORT),
            'table_name' => $allUsers ? 'expenses_community_all' : 'expenses_community',
            'columns' => $columns,
            'list_route' => $listRoute,
            'reset_url' => $this->generateUrl($listRoute),
            'title' => $allUsers ? 'All expenses' : 'My expenses',
            'timezone' => $user->getTimezone(),
            'page' => $page,
            'pages' => $pages,
            'per_page' => self::PER_PAGE,
            'total_count' => $totalCount,
            'sum_total' => $this->expenses->sumTotal($query, $timezone),
        ]);
    }

    /**
     * Column definitions in the format Kimai's datatable macros expect: a plain
     * string is the column's CSS class, an array can also carry a title.
     * Titles that are Kimai translation keys (date, customer, project, user)
     * are translated by Kimai; the others are plain text.
     *
     * "alwaysVisible" columns cannot be hidden in Kimai's column chooser; the
     * d-none d-*-table-cell classes hide a column on narrow screens.
     *
     * @return array<string, string|array<string, string>>
     */
    private function tableColumns(bool $allUsers, bool $canSelect): array
    {
        $columns = [];

        if ($canSelect) {
            $columns['select'] = [
                'class' => 'alwaysVisible w-1',
                'title' => '',
                'html_after' => '<input class="form-check-input m-0 align-middle" type="checkbox" data-expense-select-all aria-label="Select all">',
            ];
        }

        $columns['date'] = 'alwaysVisible';
        $columns['category'] = ['class' => 'alwaysVisible', 'title' => 'Category'];
        $columns['customer'] = 'd-none d-md-table-cell';
        $columns['project'] = 'd-none d-lg-table-cell';
        $columns['activity'] = 'd-none d-xl-table-cell';
        $columns['quantity'] = ['class' => 'text-end d-none d-sm-table-cell', 'title' => 'Quantity'];
        $columns['cost'] = ['class' => 'text-end d-none d-xl-table-cell', 'title' => 'Cost'];
        $columns['total'] = ['class' => 'text-end alwaysVisible', 'title' => 'Total'];
        $columns['status'] = ['class' => 'd-none d-lg-table-cell', 'title' => 'Status'];

        if ($allUsers) {
            // Last data column, like Kimai's "All times".
            $columns['user'] = 'd-none d-md-table-cell';
        }

        $columns['actions'] = 'actions alwaysVisible';

        return $columns;
    }

    /**
     * Shared by create, copy and edit.
     */
    private function processForm(Request $request, Expense $expense, bool $isNew, ?string $title = null): Response
    {
        $user = $this->getAuthenticatedUser();
        $returnUrl = $this->resolveReturnUrl($request);
        $canEditCost = $this->isGranted(ExpensePermissions::EDIT_COST);

        // Remember the persisted rate. A normal user is not allowed to submit
        // a changed cost, even if they manipulate the HTML form in a browser.
        $originalCost = $expense->getCost();
        $originalCategoryId = $isNew ? null : $expense->getCategory()->getId();
        $modal = $this->isModalRequest($request);

        // The form posts back to the same kind of request it was loaded with.
        $params = ['returnUrl' => $returnUrl] + ($modal ? ['modal' => 1] : []);
        $action = $isNew
            ? $this->generateUrl('kimai_expenses_community_create', $params)
            : $this->generateUrl('kimai_expenses_community_edit', ['id' => $expense->getId()] + $params);

        $form = $this->createForm(ExpenseType::class, $expense, [
            'action' => $action,
            'can_edit_cost' => $canEditCost,
            'can_edit_export' => $this->isGranted(ExpensePermissions::EDIT_EXPORT),
            'timezone' => $user->getTimezone(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $expense->getCategory();

            // A fixed rate always comes from the server, never from the browser. Categories
            // whose price is typed in per expense are open to everyone.
            if (!$canEditCost && !$category->isPriceEntered()) {
                $sameCategory = !$isNew && $originalCategoryId === $category->getId();
                $expense->setCost($sameCategory ? $originalCost : $category->getDefaultCost());
            }

            if ($isNew) {
                $this->entityManager->persist($expense);
            }
            $this->entityManager->flush();

            $this->addFlash('success', $isNew ? 'Expense created.' : 'Expense updated.');

            return $modal ? $this->modalSaved($returnUrl) : $this->redirect($returnUrl);
        }

        $template = $modal
            ? '@KimaiExpensesCommunity/expense/modal.html.twig'
            : '@KimaiExpensesCommunity/expense/form.html.twig';

        return $this->render($template, [
            'form' => $form->createView(),
            'title' => $title ?? ($isNew ? 'New expense' : 'Edit expense'),
            'cancel_url' => $returnUrl,
        ]);
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
        if (!$this->access->canModify($expense)) {
            throw $this->createAccessDeniedException(
                $expense->isExported() ? 'Exported expenses cannot be changed.' : 'Access Denied.'
            );
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
