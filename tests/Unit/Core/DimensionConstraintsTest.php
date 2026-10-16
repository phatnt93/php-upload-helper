<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;

#[RequiresPhpExtension('gd')]
final class DimensionConstraintsTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, list<string>, int, int, string, ?string}>
     */
    public static function combinations(): iterable
    {
        $orders = [
            ['range', 'exact', 'ratio'],
            ['range', 'ratio', 'exact'],
            ['exact', 'range', 'ratio'],
            ['exact', 'ratio', 'range'],
            ['ratio', 'range', 'exact'],
            ['ratio', 'exact', 'range'],
        ];
        $cases = [
            'valid' => [50, 100, '1:1', null],
            'range fails' => [500, 100, '1:1', 'ERR_DIMENSION_MIN_WIDTH'],
            'exact fails' => [50, 200, '1:1', 'ERR_DIMENSION_EXACT_WIDTH'],
            'ratio fails' => [50, 100, '2:1', 'ERR_DIMENSION_RATIO'],
        ];

        foreach ([false, true] as $batch) {
            foreach ($orders as $order) {
                foreach ($cases as $name => [$minWidth, $exactWidth, $ratio, $error]) {
                    yield ($batch ? 'batch' : 'single') . ' ' . implode('-', $order) . ' ' . $name => [
                        $batch, $order, $minWidth, $exactWidth, $ratio, $error,
                    ];
                }
            }
        }
    }

    /** @param list<string> $order */
    #[DataProvider('combinations')]
    public function testConstraintsAccumulateInAnyOrder(
        bool $batch,
        array $order,
        int $minWidth,
        int $exactWidth,
        string $ratio,
        ?string $expectedError,
    ): void {
        $path = $this->createTestImage(100, 100, 'png');
        $file = new UploadedFile('photo.png', 'image/png', (int) filesize($path), $path);
        $builder = $batch ? Upload::files([$file]) : Upload::file($file);

        foreach ($order as $constraint) {
            match ($constraint) {
                'range' => $builder->dimensionBetween(minW: $minWidth),
                'exact' => $builder->exactDimension($exactWidth, 100),
                'ratio' => $builder->aspectRatio($ratio),
            };
        }

        $result = $builder->process();
        if ($batch) {
            $this->assertSame($expectedError === null, $result->allSuccessful());
            $this->assertCount($expectedError === null ? 1 : 0, $result->getSuccessful());
            $this->assertCount($expectedError === null ? 0 : 1, $result->getFailed());
            if ($expectedError !== null) {
                $this->assertSame($expectedError, $result->getFailed()[0]['code']);
            }
        } else {
            $this->assertSame($expectedError === null, $result->isSuccess());
            $this->assertSame($expectedError, $result->getErrorCode());
        }
    }
}
