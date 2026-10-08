<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use KimaiPlugin\KimaiExpensesCommunityBundle\Service\ReceiptStorage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Single file field for attaching a receipt to an expense.
 *
 * The content type is verified from the file's actual content by the File
 * constraint, and ReceiptStorage checks it again before saving.
 */
final class ReceiptUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('receipt', FileType::class, [
            'label' => 'Receipt file',
            'help' => 'PDF, JPG, PNG or WebP, up to ' . ReceiptStorage::MAX_SIZE . 'B. Uploading a new file replaces the current one.',
            'attr' => ['accept' => '.pdf,.jpg,.jpeg,.png,.webp'],
            'constraints' => [
                new NotNull(message: 'Please choose a file.'),
                new File(
                    maxSize: ReceiptStorage::MAX_SIZE,
                    mimeTypes: ReceiptStorage::allowedMimeTypes(),
                    mimeTypesMessage: 'Please upload a PDF, JPG, PNG or WebP file.',
                ),
            ],
        ]);
    }
}
