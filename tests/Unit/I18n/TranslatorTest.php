<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\I18n;

use UploadHelper\Exceptions\InvalidLocaleException;
use UploadHelper\I18n\Translator;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;

class TranslatorTest extends TestCase
{
    public function testDefaultLocaleIsEnglish(): void
    {
        $this->assertSame('en', Translator::getLocale());
        $this->assertStringStartsWith('No file provided', Translator::translate('ERR_NO_FILE'));
    }

    public function testTranslateVietnamese(): void
    {
        $msg = Translator::translate('ERR_EXTENSION_NOT_ALLOWED', [
            'extension' => 'exe',
            'allowed' => ['jpg', 'png'],
        ], 'vi');

        $this->assertStringContainsString('Định dạng tệp .exe không được phép tải lên', $msg);
        $this->assertStringContainsString('jpg, png', $msg);
    }

    public function testTranslateEnglish(): void
    {
        $msg = Translator::translate('ERR_EXTENSION_NOT_ALLOWED', [
            'extension' => 'exe',
            'allowed' => ['jpg', 'png'],
        ], 'en');

        $this->assertStringContainsString('File extension .exe is not allowed', $msg);
        $this->assertStringContainsString('jpg, png', $msg);
    }

    public function testFallbackKeyWhenNotFound(): void
    {
        $msg = Translator::translate('ERR_UNKNOWN_KEY_XYZ');
        $this->assertSame('ERR_UNKNOWN_KEY_XYZ', $msg);
        $this->assertFalse(Translator::has('ERR_UNKNOWN_KEY_XYZ'));
    }

    public function testUnknownLocaleFallsBackToEnglish(): void
    {
        Translator::setLocale('fr');

        $this->assertSame('fr', Translator::getLocale());
        $this->assertStringStartsWith('No file provided', Translator::translate('ERR_NO_FILE'));
    }

    public function testMissingKeyInLocaleFallsBackToEnglish(): void
    {
        Translator::addTranslations('fr', ['ERR_NO_FILE' => 'Aucun fichier fourni.']);
        Translator::setLocale('fr');

        $this->assertSame('Aucun fichier fourni.', Translator::translate('ERR_NO_FILE'));
        $this->assertStringStartsWith('The uploaded file is invalid', Translator::translate('ERR_VALIDATION'));
    }

    public function testAddTranslationsOverridesBuiltInMessage(): void
    {
        Translator::addTranslations('vi', ['ERR_FILE_TOO_LARGE' => 'Tệp quá lớn (tối đa {formatted_max}).']);

        $msg = Translator::translate('ERR_FILE_TOO_LARGE', ['formatted_max' => '2 MB'], 'vi');

        $this->assertSame('Tệp quá lớn (tối đa 2 MB).', $msg);
    }

    public function testReplacementValuesAreNotReinterpreted(): void
    {
        $msg = Translator::translate('ERR_PATH_TRAVERSAL', ['filename' => '{filename}{extension}', 'extension' => 'php'], 'en');

        $this->assertStringContainsString('{filename}{extension}', $msg);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLocaleProvider(): iterable
    {
        yield 'path traversal' => ['../../../etc/passwd'];
        yield 'directory separator' => ['vi/../en'];
        yield 'trailing newline' => ["en\n"];
        yield 'country code vn style uppercase' => ['VN'];
        yield 'empty' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLocaleProvider')]
    public function testInvalidLocaleIsRejected(string $locale): void
    {
        $this->expectException(InvalidLocaleException::class);
        Translator::setLocale($locale);
    }

    public function testInvalidLocaleIsRejectedOnTranslate(): void
    {
        $this->expectException(InvalidLocaleException::class);
        Translator::translate('ERR_NO_FILE', [], '../en');
    }

    public function testResultMessageFollowsActiveLocale(): void
    {
        $tmp = $this->createTempFile('plain text', 'txt');

        Translator::setLocale('vi');
        $result = Upload::fromPath($tmp, 'notes.txt')->allowedExtensions(['jpg'])->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_EXTENSION_NOT_ALLOWED', $result->getErrorCode());
        $this->assertStringContainsString('Định dạng tệp .txt không được phép tải lên', (string) $result->getErrorMessage());

        $errors = $result->getErrors();
        $this->assertStringContainsString('File extension .txt is not allowed', (string) $errors['context']['reason']);
    }

    public function testInternalErrorDoesNotLeakDetailsToEndUser(): void
    {
        $result = Upload::fromPath($this->createTempFile('x', 'txt'), 'a.txt')
            ->name(static function (): string {
                throw new \RuntimeException('secret internal path /var/www');
            })
            ->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_INTERNAL', $result->getErrorCode());
        $this->assertStringNotContainsString('/var/www', (string) $result->getErrorMessage());
        $this->assertSame('secret internal path /var/www', $result->getErrors()['context']['reason']);
    }
}
