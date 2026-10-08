<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\ReceiptUploadType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use KimaiPlugin\KimaiExpensesCommunityBundle\Security\ExpensePermissions;
use KimaiPlugin\KimaiExpensesCommunityBundle\Service\ExpenseAccessChecker;
use KimaiPlugin\KimaiExpensesCommunityBundle\Service\ReceiptStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Attach, view and remove the receipt (PDF or image) of one expense.
 *
 * This is a normal page rather than a field in the expense modal on purpose:
 * file uploads need a multipart form, which Kimai's AJAX modal is not known to
 * submit. Viewing follows the same visibility rule as the expense itself;
 * uploading and removing follow the edit rules (including the export lock).
 */
#[Route('/expenses/{id}/receipt', requirements: ['id' => '\d+'])]
#[IsGranted(ExpensePermissions::VIEW)]
final class ExpenseReceiptController extends AbstractController
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExpenseAccessChecker $access,
        private readonly ReceiptStorage $storage,
    ) {
    }

    #[Route('', name: 'kimai_expenses_community_receipt', methods: ['GET', 'POST'])]
    public function manage(int $id, Request $request): Response
    {
        $expense = $this->findViewableExpense($id);
        $canChange = $this->canChange($expense);

        $form = $canChange ? $this->createForm(ReceiptUploadType::class) : null;

        if ($form !== null) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var UploadedFile $upload */
                $upload = $form->get('receipt')->getData();

                try {
                    $previous = $this->storage->attach($expense, $upload);
                    $this->entityManager->flush();
                    // Only now that the new file is safely recorded, drop the old one.
                    $this->storage->delete($previous);

                    $this->addFlash('success', 'Receipt saved.');
                } catch (\Throwable) {
                    $this->addFlash('danger', 'The receipt could not be saved. Check the file type and the folder permissions of var/data.');
                }

                return $this->redirectToRoute('kimai_expenses_community_receipt', ['id' => $expense->getId()]);
            }
        }

        return $this->render('@KimaiExpensesCommunity/receipt/manage.html.twig', [
            'expense' => $expense,
            'form' => $form?->createView(),
            'can_change' => $canChange,
            'title' => 'Receipt',
            'timezone' => $this->getAuthenticatedUser()->getTimezone(),
        ]);
    }

    #[Route('/download', name: 'kimai_expenses_community_receipt_download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $expense = $this->findViewableExpense($id);
        $path = $this->storage->getPath($expense);

        if ($path === null) {
            throw $this->createNotFoundException('This expense has no receipt.');
        }

        $name = $expense->getReceiptOriginalName() ?? 'receipt';

        $response = new BinaryFileResponse($path);
        // The type was detected from the file content when it was uploaded; nosniff stops
        // the browser from second-guessing it.
        $response->headers->set('Content-Type', $expense->getReceiptMimeType() ?? 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $name,
            preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'receipt'
        );

        return $response;
    }

    #[Route('/remove', name: 'kimai_expenses_community_receipt_remove', methods: ['POST'])]
    #[IsGranted(ExpensePermissions::EDIT)]
    public function remove(int $id, Request $request): Response
    {
        $expense = $this->findViewableExpense($id);

        if (!$this->canChange($expense)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('remove-receipt-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $previous = $this->storage->detach($expense);
        $this->entityManager->flush();
        $this->storage->delete($previous);

        $this->addFlash('success', 'Receipt removed.');

        return $this->redirectToRoute('kimai_expenses_community_receipt', ['id' => $expense->getId()]);
    }

    /** Whether the current user may upload or remove the receipt. */
    private function canChange(Expense $expense): bool
    {
        return $this->isGranted(ExpensePermissions::EDIT) && $this->access->canModify($expense);
    }

    private function findViewableExpense(int $id): Expense
    {
        $expense = $this->expenses->find($id);

        if (!$expense instanceof Expense) {
            throw $this->createNotFoundException('Expense not found.');
        }

        if (!$this->access->canView($expense)) {
            throw $this->createAccessDeniedException();
        }

        return $expense;
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
