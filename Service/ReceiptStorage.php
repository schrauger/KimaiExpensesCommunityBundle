<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Service;

use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores receipt files (PDF or image) on the server's disk.
 *
 * Design notes:
 *  - Files live in var/data/kimai-expenses-community/receipts, OUTSIDE the web
 *    root, and are only served through a controller that checks permissions.
 *  - The stored name is random (never the user's file name), so nothing a user
 *    uploads can influence a path. The original name is kept in the database
 *    for display and download only.
 *  - The type is detected from the file's content (not the client's claim) and
 *    must be one of MIME_EXTENSIONS.
 *
 * The service only touches the entity in memory and the disk. Persisting is the
 * caller's job. Because a failed flush must not lose a good file, methods that
 * replace a file return the OLD file name so the caller can delete it AFTER a
 * successful flush.
 */
final class ReceiptStorage
{
    /** Per-file limit. PHP's own upload_max_filesize / post_max_size apply first. */
    public const MAX_SIZE = '10M';

    /** Accepted content types and the extension each is stored with. */
    public const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private readonly string $directory;

    public function __construct(#[Autowire(param: 'kernel.project_dir')] string $projectDir)
    {
        $this->directory = $projectDir . '/var/data/kimai-expenses-community/receipts';
    }

    /**
     * @return list<string>
     */
    public static function allowedMimeTypes(): array
    {
        return array_keys(self::MIME_EXTENSIONS);
    }

    /**
     * Save the upload and point the expense at it.
     *
     * @return string|null file name of the receipt that was replaced (delete it after flushing)
     */
    public function attach(Expense $expense, UploadedFile $upload): ?string
    {
        // Read everything we need BEFORE move(): afterwards the object points to the new path.
        $mimeType = (string) $upload->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if ($extension === null) {
            throw new \InvalidArgumentException('Unsupported receipt type.');
        }

        $displayName = $this->cleanDisplayName($upload->getClientOriginalName(), $extension);

        $this->ensureDirectory();
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $upload->move($this->directory, $filename);

        $previous = $expense->getReceiptFilename();
        $expense->setReceipt($filename, $displayName, $mimeType);

        return $previous;
    }

    /**
     * Detach the receipt from the expense.
     *
     * @return string|null file name to delete after flushing
     */
    public function detach(Expense $expense): ?string
    {
        $previous = $expense->getReceiptFilename();
        $expense->clearReceipt();

        return $previous;
    }

    /**
     * Absolute path of the expense's receipt, or null when there is none (or the file is missing).
     */
    public function getPath(Expense $expense): ?string
    {
        $filename = $expense->getReceiptFilename();

        if ($filename === null || !$this->isStoredName($filename)) {
            return null;
        }

        $path = $this->directory . '/' . $filename;

        return is_file($path) ? $path : null;
    }

    /**
     * Remove a stored file. Unknown or malformed names are ignored, which also
     * means a tampered database value can never delete anything outside the folder.
     */
    public function delete(?string $filename): void
    {
        if ($filename === null || !$this->isStoredName($filename)) {
            return;
        }

        $path = $this->directory . '/' . $filename;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function isStoredName(string $filename): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png|webp)$/', $filename) === 1;
    }

    /**
     * A safe, readable name for display/download: letters, digits and a few
     * punctuation marks only, with the extension matching the detected type.
     */
    private function cleanDisplayName(string $clientName, string $extension): string
    {
        $base = pathinfo($clientName, PATHINFO_FILENAME);
        $base = preg_replace('/[^\p{L}\p{N}\-_. ()]+/u', '_', $base) ?? '';
        $base = trim(mb_substr($base, 0, 120), " ._");

        return ($base !== '' ? $base : 'receipt') . '.' . $extension;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Cannot create the receipt folder "%s".', $this->directory));
        }
    }
}
