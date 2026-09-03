<?php

namespace App\Tests\Unit;

use App\Exception\ApiProblemException;
use App\Service\TechnicalSheetImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TechnicalSheetImageStorageTest extends TestCase
{
    private string $projectDirectory;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDirectory = sys_get_temp_dir().'/chirorg-storage-'.bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->projectDirectory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDirectory);
    }

    public function testItStoresAValidatedImageOutsideTheController(): void
    {
        $source = $this->projectDirectory.'/source.png';
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
        $upload = new UploadedFile($source, 'consigne.png', 'image/png', null, true);

        $url = (new TechnicalSheetImageStorage($this->projectDirectory, $this->filesystem))->store($upload);

        self::assertMatchesRegularExpression('#^/uploads/fiches-techniques/[a-f0-9]{32}\.png$#', $url);
        self::assertFileExists($this->projectDirectory.'/public'.$url);
    }

    public function testItRejectsMissingAndUnsupportedFiles(): void
    {
        $storage = new TechnicalSheetImageStorage($this->projectDirectory, $this->filesystem);

        try {
            $storage->store(null);
            self::fail('Un upload absent doit être refusé.');
        } catch (ApiProblemException $exception) {
            self::assertSame('TECHNICAL_SHEET_IMAGE_REQUIRED', $exception->errorCode);
        }

        $source = $this->projectDirectory.'/payload.txt';
        file_put_contents($source, 'not-an-image');

        try {
            $storage->store(new UploadedFile($source, 'payload.txt', 'text/plain', null, true));
            self::fail('Un format non image doit être refusé.');
        } catch (ApiProblemException $exception) {
            self::assertSame('TECHNICAL_SHEET_IMAGE_TYPE_INVALID', $exception->errorCode);
        }
    }
}
