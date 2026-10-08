<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Service;

use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a list of expenses as a CSV download that opens cleanly in Excel,
 * LibreOffice and Google Sheets.
 *
 * - A UTF-8 byte-order mark is written so spreadsheet programs detect the encoding.
 * - Free-text cells that start with = + - @ (or a tab/CR) are prefixed with an
 *   apostrophe so a spreadsheet never runs them as a formula ("CSV injection").
 *   Numbers are written as-is, so negative amounts stay numeric.
 */
final class ExpenseCsvExporter
{
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param iterable<Expense> $expenses
     */
    public function createResponse(iterable $expenses, \DateTimeZone $timezone, bool $includeUser): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($expenses, $timezone, $includeUser): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            $header = ['Date', 'Category', 'Customer', 'Project', 'Activity', 'Description',
                'Quantity', 'Unit', 'Cost', 'Total', 'Billable', 'Exported', 'Receipt'];
            if ($includeUser) {
                $header[] = 'User';
            }
            fputcsv($out, $header, ',', '"', '\\');

            foreach ($expenses as $expense) {
                fputcsv($out, $this->row($expense, $timezone, $includeUser), ',', '"', '\\');
            }

            fclose($out);
        });

        $filename = sprintf('expenses-%s.csv', (new \DateTimeImmutable('now', $timezone))->format('Ymd-Hi'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
    }

    /**
     * @return list<string>
     */
    private function row(Expense $expense, \DateTimeZone $timezone, bool $includeUser): array
    {
        $row = [
            \DateTimeImmutable::createFromInterface($expense->getDate())->setTimezone($timezone)->format('Y-m-d H:i'),
            $this->text($expense->getCategory()->getName()),
            $this->text($expense->getCustomer()?->getName()),
            $this->text($expense->getProject()?->getName()),
            $this->text($expense->getActivity()?->getName()),
            $this->text($expense->getDescription()),
            $this->decimal($expense->getQuantity()),
            $this->text($expense->getCategory()->getUnit()),
            $this->decimal($expense->getCost()),
            number_format($expense->getTotal(), 2, '.', ''),
            $expense->isBillable() ? 'yes' : 'no',
            $expense->isExported() ? 'yes' : 'no',
            $this->text($expense->getReceiptOriginalName()),
        ];

        if ($includeUser) {
            $row[] = $this->text($expense->getUser()->getDisplayName());
        }

        return $row;
    }

    private function text(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && \in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** "47.5000" -> "47.5", "2.0000" -> "2" */
    private function decimal(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
