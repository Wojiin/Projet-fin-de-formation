<?php

namespace App\Service;

use App\Exception\ApiProblemException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/** Valide et stocke les illustrations des fiches techniques. */
final readonly class TechnicalSheetImageStorage
{
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const string RELATIVE_DIRECTORY = '/uploads/fiches-techniques';
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private Filesystem $filesystem,
    ) {
    }

    public function store(mixed $image): string
    {
        if (!$image instanceof UploadedFile || !$image->isValid()) {
            throw new ApiProblemException(
                'TECHNICAL_SHEET_IMAGE_REQUIRED',
                'Une image valide est obligatoire.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($image->getPathname());
        if (!is_string($mimeType) || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new ApiProblemException(
                'TECHNICAL_SHEET_IMAGE_TYPE_INVALID',
                'Seules les images JPEG, PNG et WebP sont acceptées.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (($image->getSize() ?? 0) > self::MAX_FILE_SIZE) {
            throw new ApiProblemException(
                'TECHNICAL_SHEET_IMAGE_TOO_LARGE',
                'L’image ne peut pas dépasser 5 Mo.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $targetDirectory = $this->projectDir.'/public'.self::RELATIVE_DIRECTORY;
        $this->filesystem->mkdir($targetDirectory, 0775);
        $filename = bin2hex(random_bytes(16)).'.'.self::ALLOWED_MIME_TYPES[$mimeType];

        try {
            $image->move($targetDirectory, $filename);
        } catch (FileException) {
            throw new ApiProblemException(
                'TECHNICAL_SHEET_IMAGE_UPLOAD_FAILED',
                'L’image n’a pas pu être enregistrée.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return self::RELATIVE_DIRECTORY.'/'.$filename;
    }
}
