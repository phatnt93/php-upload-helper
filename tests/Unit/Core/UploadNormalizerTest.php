<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use UploadHelper\Core\UploadNormalizer;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Tests\TestCase;

class UploadNormalizerTest extends TestCase
{
    public function testNormalizeSingleFile(): void
    {
        $tmp = $this->createTempFile('dummy', 'jpg');
        $raw = [
            'name'     => 'avatar.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1024,
        ];

        $files = UploadNormalizer::normalize($raw);

        $this->assertCount(1, $files);
        $file = $files[0];
        $this->assertInstanceOf(UploadedFile::class, $file);
        $this->assertSame('avatar.jpg', $file->getOriginalName());
        $this->assertSame('avatar', $file->getCurrentName());
        $this->assertSame('jpg', $file->getCurrentExtension());
        $this->assertSame('image/jpeg', $file->getClientMime());
        $this->assertSame(1024, $file->getSize());
        $this->assertSame($tmp, $file->getTmpPath());
        $this->assertTrue($file->isOk());
    }

    public function testNormalizeFlatMultiFilesArray(): void
    {
        $tmp1 = $this->createTempFile('1', 'jpg');
        $tmp2 = $this->createTempFile('2', 'png');

        $raw = [
            'name'     => ['photo1.jpg', 'photo2.png'],
            'type'     => ['image/jpeg', 'image/png'],
            'tmp_name' => [$tmp1, $tmp2],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [100, 200],
        ];

        $files = UploadNormalizer::normalize($raw);

        $this->assertCount(2, $files);
        $this->assertSame('photo1.jpg', $files[0]->getOriginalName());
        $this->assertSame('photo2.png', $files[1]->getOriginalName());
        $this->assertSame('0', $files[0]->getFieldKey());
        $this->assertSame('1', $files[1]->getFieldKey());
    }

    public function testNormalizeDeeplyNestedArray(): void
    {
        $tmp = $this->createTempFile('nested', 'pdf');

        $raw = [
            'name'     => [
                'company' => [
                    'docs' => [
                        0 => 'contract.pdf',
                    ],
                ],
            ],
            'type'     => [
                'company' => [
                    'docs' => [
                        0 => 'application/pdf',
                    ],
                ],
            ],
            'tmp_name' => [
                'company' => [
                    'docs' => [
                        0 => $tmp,
                    ],
                ],
            ],
            'error'    => [
                'company' => [
                    'docs' => [
                        0 => UPLOAD_ERR_OK,
                    ],
                ],
            ],
            'size'     => [
                'company' => [
                    'docs' => [
                        0 => 5000,
                    ],
                ],
            ],
        ];

        $files = UploadNormalizer::normalize($raw);

        $this->assertCount(1, $files);
        $this->assertSame('contract.pdf', $files[0]->getOriginalName());
        $this->assertSame('company.docs.0', $files[0]->getFieldKey());
    }

    public function testIgnoreNoFileUploadsByDefault(): void
    {
        $raw = [
            'name'     => ['file1.jpg', ''],
            'type'     => ['image/jpeg', ''],
            'tmp_name' => ['/tmp/1', ''],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
            'size'     => [100, 0],
        ];

        $files = UploadNormalizer::normalize($raw);

        $this->assertCount(1, $files);
        $this->assertSame('file1.jpg', $files[0]->getOriginalName());
    }

    public function testIncludeNoFileWhenConfigured(): void
    {
        $raw = [
            'name'     => 'empty.jpg',
            'type'     => '',
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'size'     => 0,
        ];

        $files = UploadNormalizer::normalize($raw, ignoreNoFile: false);

        $this->assertCount(1, $files);
        $this->assertSame(UPLOAD_ERR_NO_FILE, $files[0]->getErrorCode());
        $this->assertFalse($files[0]->isOk());
    }

    public function testNormalizeFullFilesSuperglobal(): void
    {
        $tmp1 = $this->createTempFile('avatar', 'png');
        $tmp2 = $this->createTempFile('cv', 'pdf');

        $superglobal = [
            'avatar' => [
                'name'     => 'user.png',
                'type'     => 'image/png',
                'tmp_name' => $tmp1,
                'error'    => UPLOAD_ERR_OK,
                'size'     => 1234,
            ],
            'documents' => [
                'name'     => ['cv.pdf'],
                'type'     => ['application/pdf'],
                'tmp_name' => [$tmp2],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [4321],
            ],
        ];

        $files = UploadNormalizer::normalize($superglobal);

        $this->assertCount(2, $files);
        $this->assertSame('user.png', $files[0]->getOriginalName());
        $this->assertSame('avatar', $files[0]->getFieldKey());
        $this->assertSame('cv.pdf', $files[1]->getOriginalName());
        $this->assertSame('documents.0', $files[1]->getFieldKey());
    }

    public function testNormalizeEmptyInput(): void
    {
        $this->assertEmpty(UploadNormalizer::normalize(null));
        $this->assertEmpty(UploadNormalizer::normalize([]));
        $this->assertEmpty(UploadNormalizer::normalize(''));
    }
}
