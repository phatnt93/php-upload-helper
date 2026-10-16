<?php

declare(strict_types=1);

namespace UploadHelper\Core;

use UploadHelper\Contracts\ImageDriverInterface;
use UploadHelper\Contracts\ScannerInterface;
use UploadHelper\Contracts\StorageInterface;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\I18n\Translator;
use UploadHelper\Results\BatchResult;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Storage\RollbackManager;

class BatchUploadBuilder
{
    private FileCollection $collection;
    /** @var array<int, callable(UploadBuilder, int): void> */
    private array $customHandlers = [];
    /** @var array<string, callable> */
    private array $variants = [];
    /** @var array<int, callable> */
    private array $beforeProcessHooks = [];
    /** @var array<int, callable> */
    private array $afterProcessHooks = [];
    /** @var array<int, callable> */
    private array $onSuccessHooks = [];
    /** @var array<int, callable> */
    private array $onErrorHooks = [];
    private bool $rollbackOnError = false;
    private ?string $destinationDir = null;
    private int $chmod = 0755;
    private bool $overwrite = false;
    private bool $autoIncrement = false;

    // Rules chung cho toàn bộ batch
    /** @var array<int, string> */
    private array $allowedExtensions = [];
    /** @var array<int, string> */
    private array $allowedMimes = [];
    private int|string|null $maxSize = null;
    private int|string|null $minSize = null;
    /** @var array<string, mixed> */
    private array $dimensionConstraints = [];
    private ?int $maxPixels = null;
    private int|string|null $maxImageMemory = null;
    private bool $blockPdfJavaScript = false;
    private bool $blockOfficeMacros = false;
    private ?ScannerInterface $scanner = null;
    private bool $hardenDestination = false;
    private bool $stripExif = false;
    private bool $stripExifKeepOrientation = true;

    // Naming chung
    private ?string $namingStrategy = null;
    private string $hashAlgo = 'sha256';
    private int $hashLength = 64;
    private int $randomLength = 16;
    private ?string $prefix = null;
    private ?string $suffix = null;

    // Image actions chung
    /** @var array<int, array{type: string, args: array<string, mixed>}> */
    private array $imageActions = [];

    private ?ImageDriverInterface $imageDriver = null;
    private ?StorageInterface $storage = null;

    /**
     * @param array<int, UploadedFile>|FileCollection $input
     */
    public function __construct(array|FileCollection $input)
    {
        $this->collection = $input instanceof FileCollection ? $input : new FileCollection($input);
        $this->imageDriver = new GdDriver();
        $this->storage = new LocalStorage();
    }

    public function count(): int
    {
        return $this->collection->count();
    }

    public function getCollection(): FileCollection
    {
        return $this->collection;
    }

    /**
     * @param callable(UploadBuilder, int): void $callback
     */
    public function each(callable $callback): self
    {
        $this->customHandlers[] = $callback;
        return $this;
    }

    public function rollbackOnError(bool $rollback = true): self
    {
        $this->rollbackOnError = $rollback;
        return $this;
    }

    // --- Validation Rules Chung ---

    /**
     * @param array<int, string> $extensions
     */
    public function allowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = $extensions;
        return $this;
    }

    /**
     * @param array<int, string> $mimes
     */
    public function allowedMimes(array $mimes): self
    {
        $this->allowedMimes = $mimes;
        return $this;
    }

    public function maxSize(int|string|null $maxSize): self
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    public function minSize(int|string|null $minSize): self
    {
        $this->minSize = $minSize;
        return $this;
    }

    public function dimensionBetween(
        ?int $minW = null,
        ?int $minH = null,
        ?int $maxW = null,
        ?int $maxH = null,
    ): self {
        $this->dimensionConstraints = array_replace($this->dimensionConstraints, [
            'min_width'  => $minW,
            'min_height' => $minH,
            'max_width'  => $maxW,
            'max_height' => $maxH,
        ]);
        return $this;
    }

    public function dimension(int $width, int $height): self
    {
        $this->dimensionConstraints['exact_width'] = $width;
        $this->dimensionConstraints['exact_height'] = $height;
        return $this;
    }

    public function exactDimension(int $width, int $height): self
    {
        return $this->dimension($width, $height);
    }

    public function aspectRatio(float|string $ratio, float $tolerance = 0.05): self
    {
        $this->dimensionConstraints['ratio'] = $ratio;
        $this->dimensionConstraints['ratio_tolerance'] = $tolerance;
        return $this;
    }

    public function maxPixels(int $maxPixels): self
    {
        $this->maxPixels = $maxPixels;
        return $this;
    }

    public function maxImageMemory(int|string $maxMemory): self
    {
        $this->maxImageMemory = $maxMemory;
        return $this;
    }

    public function blockPdfJavaScript(bool $block = true): self
    {
        $this->blockPdfJavaScript = $block;
        return $this;
    }

    public function blockOfficeMacros(bool $block = true): self
    {
        $this->blockOfficeMacros = $block;
        return $this;
    }

    public function scanner(ScannerInterface $scanner): self
    {
        $this->scanner = $scanner;
        return $this;
    }

    // --- Naming Chung ---

    public function slugName(): self
    {
        $this->namingStrategy = 'slug';
        return $this;
    }

    public function uuidName(): self
    {
        $this->namingStrategy = 'uuid';
        return $this;
    }

    public function randomName(int $length = 16): self
    {
        $this->namingStrategy = 'random';
        $this->randomLength = $length;
        return $this;
    }

    public function timestampName(): self
    {
        $this->namingStrategy = 'timestamp';
        return $this;
    }

    public function hashName(string $algo = 'sha256', int $length = 64): self
    {
        $this->namingStrategy = 'hash';
        $this->hashAlgo = $algo;
        $this->hashLength = $length;
        return $this;
    }

    public function sha256Name(int $length = 64): self
    {
        return $this->hashName('sha256', $length);
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function suffix(string $suffix): self
    {
        $this->suffix = $suffix;
        return $this;
    }

    // --- Storage & Duplicates ---

    public function overwrite(bool $overwrite = true): self
    {
        $this->overwrite = $overwrite;
        return $this;
    }

    public function autoIncrementDuplicate(bool $auto = true): self
    {
        $this->autoIncrement = $auto;
        return $this;
    }

    public function hardenDestination(bool $harden = true): self
    {
        $this->hardenDestination = $harden;
        if ($this->storage instanceof \UploadHelper\Storage\LocalStorage) {
            $this->storage->setHardenDirectory($harden);
        }
        return $this;
    }

    public function destination(string $destinationDir, int $chmod = 0755): self
    {
        $this->destinationDir = $destinationDir;
        $this->chmod = $chmod;
        return $this;
    }

    public function saveTo(string $destinationDir, int $chmod = 0755): self
    {
        return $this->destination($destinationDir, $chmod);
    }

    // --- Image Actions Chung ---

    public function resize(
        int $width,
        int $height,
        string $mode = 'fit',
        string $background = '#FFFFFF',
    ): self {
        $this->imageActions[] = [
            'type' => 'resize',
            'args' => ['width' => $width, 'height' => $height, 'mode' => $mode, 'background' => $background],
        ];
        return $this;
    }

    public function crop(int $width, int $height, int $x = 0, int $y = 0): self
    {
        $this->imageActions[] = [
            'type' => 'crop',
            'args' => ['width' => $width, 'height' => $height, 'x' => $x, 'y' => $y],
        ];
        return $this;
    }

    public function rotate(float $angle, string $background = '#000000'): self
    {
        $this->imageActions[] = [
            'type' => 'rotate',
            'args' => ['angle' => $angle, 'background' => $background],
        ];
        return $this;
    }

    public function stripExif(bool $keepOrientation = true): self
    {
        $this->stripExif = true;
        $this->stripExifKeepOrientation = $keepOrientation;
        return $this;
    }

    public function stripGps(bool $keepOrientation = true): self
    {
        return $this->stripExif($keepOrientation);
    }

    public function toWebp(int $quality = 85): self
    {
        $this->imageActions[] = [
            'type' => 'convert',
            'args' => ['format' => 'webp', 'quality' => $quality],
        ];
        return $this;
    }

    public function toJpeg(int $quality = 85): self
    {
        $this->imageActions[] = [
            'type' => 'convert',
            'args' => ['format' => 'jpeg', 'quality' => $quality],
        ];
        return $this;
    }

    public function grayscale(): self
    {
        $this->imageActions[] = ['type' => 'filter', 'args' => ['filter' => 'grayscale']];
        return $this;
    }

    public function invert(): self
    {
        $this->imageActions[] = ['type' => 'filter', 'args' => ['filter' => 'negative']];
        return $this;
    }

    public function imageDriver(ImageDriverInterface $driver): self
    {
        $this->imageDriver = $driver;
        return $this;
    }

    public function storage(StorageInterface $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    // --- Thực Thi Batch ---

    public function variant(string $name, callable $configurator): self
    {
        $this->variants[$name] = $configurator;
        return $this;
    }

    public function beforeProcess(callable $callback): self
    {
        $this->beforeProcessHooks[] = $callback;
        return $this;
    }

    public function afterProcess(callable $callback): self
    {
        $this->afterProcessHooks[] = $callback;
        return $this;
    }

    public function onSuccess(callable $callback): self
    {
        $this->onSuccessHooks[] = $callback;
        return $this;
    }

    public function onError(callable $callback): self
    {
        $this->onErrorHooks[] = $callback;
        return $this;
    }

    public function process(): BatchResult
    {
        foreach ($this->beforeProcessHooks as $hook) {
            $hook($this->collection);
        }

        $successful = [];
        $failed = [];
        $rollbackManager = new RollbackManager();

        foreach ($this->collection as $index => $uploadedFile) {
            $builder = new UploadBuilder($uploadedFile);

            if ($this->imageDriver !== null) {
                $builder->imageDriver($this->imageDriver);
            }
            if ($this->storage !== null) {
                $builder->storage($this->storage);
            }

            // 1. Áp dụng các quy tắc chung
            if (!empty($this->allowedExtensions)) {
                $builder->allowedExtensions($this->allowedExtensions);
            }
            if (!empty($this->allowedMimes)) {
                $builder->allowedMimes($this->allowedMimes);
            }
            if ($this->maxSize !== null) {
                $builder->maxSize($this->maxSize);
            }
            if ($this->minSize !== null) {
                $builder->minSize($this->minSize);
            }
            if (!empty($this->dimensionConstraints)) {
                if (isset($this->dimensionConstraints['exact_width'], $this->dimensionConstraints['exact_height'])) {
                    $builder->dimension($this->dimensionConstraints['exact_width'], $this->dimensionConstraints['exact_height']);
                }
                if (isset($this->dimensionConstraints['min_width']) || isset($this->dimensionConstraints['max_width']) || isset($this->dimensionConstraints['min_height']) || isset($this->dimensionConstraints['max_height'])) {
                    $builder->dimensionBetween(
                        $this->dimensionConstraints['min_width'] ?? null,
                        $this->dimensionConstraints['min_height'] ?? null,
                        $this->dimensionConstraints['max_width'] ?? null,
                        $this->dimensionConstraints['max_height'] ?? null,
                    );
                }
                if (isset($this->dimensionConstraints['ratio'])) {
                    $builder->aspectRatio(
                        $this->dimensionConstraints['ratio'],
                        $this->dimensionConstraints['ratio_tolerance'] ?? 0.05,
                    );
                }
            }
            if ($this->maxPixels !== null) {
                $builder->maxPixels($this->maxPixels);
            }
            if ($this->maxImageMemory !== null) {
                $builder->maxImageMemory($this->maxImageMemory);
            }
            if ($this->blockPdfJavaScript) {
                $builder->blockPdfJavaScript(true);
            }
            if ($this->blockOfficeMacros) {
                $builder->blockOfficeMacros(true);
            }
            if ($this->scanner !== null) {
                $builder->scanner($this->scanner);
            }
            if ($this->hardenDestination) {
                $builder->hardenDestination(true);
            }
            if ($this->stripExif) {
                $builder->stripExif($this->stripExifKeepOrientation);
            }

            // Naming chung
            if ($this->namingStrategy === 'slug') {
                $builder->slugName();
            } elseif ($this->namingStrategy === 'uuid') {
                $builder->uuidName();
            } elseif ($this->namingStrategy === 'random') {
                $builder->randomName($this->randomLength);
            } elseif ($this->namingStrategy === 'timestamp') {
                $builder->timestampName();
            } elseif ($this->namingStrategy === 'hash') {
                $builder->hashName($this->hashAlgo, $this->hashLength);
            }
            if ($this->prefix !== null) {
                $builder->prefix($this->prefix);
            }
            if ($this->suffix !== null) {
                $builder->suffix($this->suffix);
            }

            // Storage chung
            $builder->overwrite($this->overwrite);
            $builder->autoIncrementDuplicate($this->autoIncrement);

            // Image actions chung
            foreach ($this->imageActions as $action) {
                switch ($action['type']) {
                    case 'resize':
                        $builder->resize(
                            $action['args']['width'],
                            $action['args']['height'],
                            $action['args']['mode'],
                            $action['args']['background'],
                        );
                        break;
                    case 'crop':
                        $builder->crop(
                            $action['args']['width'],
                            $action['args']['height'],
                            $action['args']['x'],
                            $action['args']['y'],
                        );
                        break;
                    case 'rotate':
                        $builder->rotate($action['args']['angle'], $action['args']['background']);
                        break;
                    case 'convert':
                        if ($action['args']['format'] === 'webp') {
                            $builder->toWebp($action['args']['quality']);
                        } elseif ($action['args']['format'] === 'jpeg') {
                            $builder->toJpeg($action['args']['quality']);
                        }
                        break;
                    case 'filter':
                        if ($action['args']['filter'] === 'grayscale') {
                            $builder->grayscale();
                        } elseif ($action['args']['filter'] === 'negative') {
                            $builder->invert();
                        }
                        break;
                }
            }

            // Truyền các biến thể chung
            foreach ($this->variants as $variantName => $configurator) {
                $builder->variant($variantName, $configurator);
            }

            // 2. Cho phép can thiệp riêng từng file qua each()
            foreach ($this->customHandlers as $handler) {
                $handler($builder, $index);
            }

            // 3. Thực thi
            if ($this->destinationDir !== null) {
                $result = $builder->saveTo($this->destinationDir, $this->chmod);
            } else {
                $result = $builder->process();
            }

            if ($result->isSuccess() && $result->getFile() !== null) {
                $successful[] = $result->getFile();
                $rollbackManager->record($result->getFile()->getSavedPath());
            } else {
                $failed[] = [
                    'file'    => $uploadedFile,
                    'error'   => $result->getErrorMessage() ?? Translator::translate('ERR_PROCESS_FAILED'),
                    'code'    => $result->getErrorCode() ?? 'ERR_PROCESS_FAILED',
                    'context' => $result->getErrors()['context'] ?? [],
                ];

                // Nếu bật rollbackOnError, lập tức rollback toàn bộ các file đã lưu trước đó!
                if ($this->rollbackOnError) {
                    $rollbackManager->rollback();
                    // Giữ nguyên các file tiếp theo không lưu tiếp để tránh lãng phí IO
                    break;
                }
            }
        }

        $batchResult = new BatchResult(
            successful: $this->rollbackOnError && !empty($failed) ? [] : $successful,
            failed: $failed,
        );

        if ($batchResult->allSuccessful()) {
            foreach ($this->onSuccessHooks as $hook) {
                $hook($batchResult);
            }
        } else {
            foreach ($this->onErrorHooks as $hook) {
                $hook($batchResult);
            }
        }

        foreach ($this->afterProcessHooks as $hook) {
            $hook($batchResult);
        }

        return $batchResult;
    }
}
