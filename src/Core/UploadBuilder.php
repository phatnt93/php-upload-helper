<?php

declare(strict_types=1);

namespace UploadHelper\Core;

use Closure;
use UploadHelper\Actions\File\AutoIncrementAction;
use UploadHelper\Actions\File\RenameAction;
use UploadHelper\Actions\File\SaveAction;
use UploadHelper\Actions\Image\AutoOrientAction;
use UploadHelper\Actions\Image\CropAction;
use UploadHelper\Actions\Image\FilterAction;
use UploadHelper\Actions\Image\FlipAction;
use UploadHelper\Actions\Image\FormatConvertAction;
use UploadHelper\Actions\Image\ResizeAction;
use UploadHelper\Actions\Image\RotateAction;
use UploadHelper\Actions\Image\StripExifAction;
use UploadHelper\Actions\Image\WatermarkImageAction;
use UploadHelper\Actions\Image\WatermarkTextAction;
use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Contracts\ImageDriverInterface;
use UploadHelper\Contracts\ScannerInterface;
use UploadHelper\Contracts\StorageInterface;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\I18n\Translator;
use UploadHelper\Results\SingleResult;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Validation\SecurityValidator;

class UploadBuilder
{
    private SecurityValidator $validator;
    private ActionPipeline $pipeline;
    private ActionContext $context;
    private RenameAction $renameAction;
    private bool $renameActionAdded = false;
    private bool $isProcessed = false;
    private ?SingleResult $lastResult = null;
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
    private bool $allowEnlarge = false;
    private ?string $destinationDir = null;
    private int $chmod = 0755;

    public function __construct(private ?UploadedFile $file = null)
    {
        $this->validator = new SecurityValidator();
        $this->pipeline = new ActionPipeline();
        $this->context = new ActionContext(
            imageDriver: new GdDriver(),
            storage: new LocalStorage(),
        );
        $this->renameAction = new RenameAction();
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    // --- Validation Rules ---

    /**
     * @param array<int, string> $extensions
     */
    public function allowedExtensions(array $extensions): self
    {
        $this->validator->setAllowedExtensions($extensions);
        return $this;
    }

    /**
     * @param array<int, string> $mimes
     */
    public function allowedMimes(array $mimes): self
    {
        $this->validator->setAllowedMimes($mimes);
        return $this;
    }

    public function maxSize(int|string|null $maxSize): self
    {
        $this->validator->setMaxSize($maxSize);
        return $this;
    }

    public function minSize(int|string|null $minSize): self
    {
        $this->validator->setMinSize($minSize);
        return $this;
    }

    public function dimensionBetween(
        ?int $minW = null,
        ?int $minH = null,
        ?int $maxW = null,
        ?int $maxH = null,
    ): self {
        $this->validator->setDimensionConstraints([
            'min_width'  => $minW,
            'min_height' => $minH,
            'max_width'  => $maxW,
            'max_height' => $maxH,
        ]);
        return $this;
    }

    public function dimension(int $width, int $height): self
    {
        $this->validator->setDimensionConstraints([
            'exact_width'  => $width,
            'exact_height' => $height,
        ]);
        return $this;
    }

    public function exactDimension(int $width, int $height): self
    {
        return $this->dimension($width, $height);
    }

    public function aspectRatio(float|string $ratio, float $tolerance = 0.05): self
    {
        $this->validator->setDimensionConstraints([
            'ratio'           => $ratio,
            'ratio_tolerance' => $tolerance,
        ]);
        return $this;
    }

    public function sanitizeSvg(bool $sanitize = true): self
    {
        $this->validator->setSanitizeSvg($sanitize);
        return $this;
    }

    public function maxPixels(int $maxPixels): self
    {
        $this->validator->setMaxPixels($maxPixels);
        return $this;
    }

    public function maxImageMemory(int|string $maxMemory): self
    {
        $this->validator->setMaxImageMemory($maxMemory);
        return $this;
    }

    public function blockPdfJavaScript(bool $block = true): self
    {
        $this->validator->setBlockPdfJavaScript($block);
        return $this;
    }

    public function blockOfficeMacros(bool $block = true): self
    {
        $this->validator->setBlockOfficeMacros($block);
        return $this;
    }

    public function scanner(ScannerInterface $scanner): self
    {
        $this->validator->setScanner($scanner);
        return $this;
    }

    // --- Naming Strategies ---

    public function slugName(): self
    {
        $this->renameAction->slug();
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function uuidName(): self
    {
        $this->renameAction->uuid();
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function randomName(int $length = 16): self
    {
        $this->renameAction->random($length);
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function timestampName(): self
    {
        $this->renameAction->timestamp();
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function hashName(string $algo = 'sha256', int $length = 64): self
    {
        $this->renameAction->hash($algo, $length);
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function sha256Name(int $length = 64): self
    {
        return $this->hashName('sha256', $length);
    }

    /**
     * @param string|Closure(UploadedFile): string $name
     */
    public function name(string|Closure $name): self
    {
        $this->renameAction->custom($name);
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->renameAction->prefix($prefix);
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    public function suffix(string $suffix): self
    {
        $this->renameAction->suffix($suffix);
        $this->ensureRenameActionInPipeline();
        return $this;
    }

    // --- Duplicate Handling & Storage Options ---

    public function overwrite(bool $overwrite = true): self
    {
        $this->context->setOverwrite($overwrite);
        return $this;
    }

    public function autoIncrementDuplicate(bool $auto = true): self
    {
        $this->context->setAutoIncrement($auto);
        return $this;
    }

    public function hardenDestination(bool $harden = true): self
    {
        $storage = $this->context->getStorage();
        if ($storage instanceof \UploadHelper\Storage\LocalStorage) {
            $storage->setHardenDirectory($harden);
        }
        return $this;
    }

    // --- Image Manipulation ---

    public function withoutEnlarging(bool $without = true): self
    {
        $this->allowEnlarge = !$without;
        return $this;
    }

    public function resize(
        int $width,
        int $height,
        string $mode = 'fit',
        string $background = '#FFFFFF',
    ): self {
        $this->pipeline->addAction(new ResizeAction(
            $width,
            $height,
            $mode,
            $this->allowEnlarge,
            $background,
        ));
        return $this;
    }

    public function crop(int $width, int $height, int $x = 0, int $y = 0): self
    {
        $this->pipeline->addAction(new CropAction($width, $height, $x, $y));
        return $this;
    }

    public function rotate(float $angle, string $background = '#000000'): self
    {
        $this->pipeline->addAction(new RotateAction($angle, $background));
        return $this;
    }

    public function flip(string $direction = 'horizontal'): self
    {
        $this->pipeline->addAction(new FlipAction($direction));
        return $this;
    }

    public function flipHorizontal(): self
    {
        return $this->flip('horizontal');
    }

    public function flipVertical(): self
    {
        return $this->flip('vertical');
    }

    public function autoOrient(): self
    {
        $this->pipeline->addAction(new AutoOrientAction());
        return $this;
    }

    public function stripExif(bool $keepOrientation = true): self
    {
        $this->pipeline->addAction(new StripExifAction($keepOrientation));
        return $this;
    }

    public function stripGps(bool $keepOrientation = true): self
    {
        return $this->stripExif($keepOrientation);
    }

    public function watermarkImage(
        string $watermarkPath,
        string $position = 'bottom-right',
        int $opacity = 100,
        int $offsetX = 10,
        int $offsetY = 10,
    ): self {
        $this->pipeline->addAction(new WatermarkImageAction(
            $watermarkPath,
            $position,
            $opacity,
            $offsetX,
            $offsetY,
        ));
        return $this;
    }

    public function watermarkText(
        string $text,
        string $fontPath = '',
        int $size = 14,
        string $color = '#FFFFFF',
        string $position = 'bottom-right',
        ?string $background = null,
        int $backgroundOpacity = 0,
        int $offsetX = 10,
        int $offsetY = 10,
    ): self {
        $this->pipeline->addAction(new WatermarkTextAction(
            $text,
            $fontPath,
            $size,
            $color,
            $position,
            $background,
            $backgroundOpacity,
            $offsetX,
            $offsetY,
        ));
        return $this;
    }

    public function toWebp(int $quality = 85): self
    {
        $this->pipeline->addAction(new FormatConvertAction('webp', $quality));
        return $this;
    }

    public function toJpeg(int $quality = 85): self
    {
        $this->pipeline->addAction(new FormatConvertAction('jpeg', $quality));
        return $this;
    }

    public function toPng(): self
    {
        $this->pipeline->addAction(new FormatConvertAction('png'));
        return $this;
    }

    public function toAvif(int $quality = 80): self
    {
        $this->pipeline->addAction(new FormatConvertAction('avif', $quality));
        return $this;
    }

    public function grayscale(): self
    {
        $this->pipeline->addAction(new FilterAction('grayscale'));
        return $this;
    }

    public function brightness(int $level): self
    {
        $this->pipeline->addAction(new FilterAction('brightness', ['level' => $level]));
        return $this;
    }

    public function contrast(int $level): self
    {
        $this->pipeline->addAction(new FilterAction('contrast', ['level' => $level]));
        return $this;
    }

    public function blur(): self
    {
        $this->pipeline->addAction(new FilterAction('blur'));
        return $this;
    }

    public function sharpen(): self
    {
        $this->pipeline->addAction(new FilterAction('sharpen'));
        return $this;
    }

    public function invert(): self
    {
        $this->pipeline->addAction(new FilterAction('negative'));
        return $this;
    }

    public function addAction(ActionInterface $action): self
    {
        $this->pipeline->addAction($action);
        return $this;
    }

    public function imageDriver(ImageDriverInterface $driver): self
    {
        $this->context->setImageDriver($driver);
        return $this;
    }

    public function storage(StorageInterface $storage): self
    {
        $this->context->setStorage($storage);
        return $this;
    }

    // --- Destination & Execution ---

    public function getDestinationDir(): ?string
    {
        return $this->destinationDir;
    }

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function getLastResult(): ?SingleResult
    {
        return $this->lastResult;
    }

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

    public function destination(string $destinationDir, int $chmod = 0755): self
    {
        $this->destinationDir = $destinationDir;
        $this->chmod = $chmod;
        return $this;
    }

    public function saveTo(string $destinationDir, int $chmod = 0755): SingleResult
    {
        $this->destinationDir = $destinationDir;
        $this->chmod = $chmod;
        return $this->process();
    }

    public function process(): SingleResult
    {
        if ($this->file === null) {
            return $this->dispatchResult(new SingleResult(
                success: false,
                errorMessage: Translator::translate('ERR_NO_FILE'),
                errorCode: 'ERR_NO_FILE',
            ));
        }

        // Kích hoạt Before Process Hooks
        foreach ($this->beforeProcessHooks as $hook) {
            $hook($this->file);
        }

        $preservedTmpPath = null;

        try {
            // 1. Chạy bước kiểm tra bảo mật & hợp lệ
            $this->validator->validate($this->file);

            // 2. Bảo lưu bản sao của tệp gốc nếu có đăng ký biến thể (Variants)
            // Trong môi trường HTTP thật, move_uploaded_file() sẽ di chuyển và làm mất file gốc ở tmpPath
            if (!empty($this->variants) && file_exists($this->file->getTmpPath())) {
                $ext = $this->file->getCurrentExtension();
                $preservedTmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_preserved_' . uniqid('', true) . ($ext !== '' ? '.' . $ext : '');
                @copy($this->file->getTmpPath(), $preservedTmpPath);
            }

            // 3. Cấu hình context lưu trữ
            $this->context->setDestinationDir($this->destinationDir);
            $this->context->setChmod($this->chmod);

            // Thêm AutoIncrementAction nếu có yêu cầu
            if ($this->context->isAutoIncrement()) {
                $this->pipeline->addAction(new AutoIncrementAction());
            }

            // Thêm SaveAction nếu có destinationDir
            if ($this->destinationDir !== null) {
                $this->pipeline->addAction(new SaveAction($this->destinationDir, $this->chmod));
            }

            // 4. Thực thi Pipeline các actions
            $finalFile = $this->pipeline->execute($this->file, $this->context);

            $processed = new ProcessedFile(
                originalName: $this->file->getOriginalName(),
                savedPath: $finalFile->getCurrentPath(),
                filename: $finalFile->getCurrentFilename(),
                mime: $finalFile->getDetectedMime(),
                extension: $finalFile->getCurrentExtension(),
                size: $finalFile->getSize(),
                dimensions: $finalFile->getImageDimensions(),
                fieldKey: $this->file->getFieldKey(),
            );

            // 5. Xử lý các biến thể (Variants) nếu có
            $processedVariants = [];
            $variantErrors = [];

            if (!empty($this->variants)) {
                $variantSourcePath = ($preservedTmpPath !== null && file_exists($preservedTmpPath))
                    ? $preservedTmpPath
                    : $this->file->getTmpPath();

                foreach ($this->variants as $variantName => $configurator) {
                    $variantUploaded = new UploadedFile(
                        originalName: $this->file->getOriginalName(),
                        clientMime: $this->file->getClientMime(),
                        size: $this->file->getSize(),
                        tmpPath: $variantSourcePath,
                        errorCode: $this->file->getErrorCode(),
                        fieldKey: $this->file->getFieldKey(),
                    );

                    $variantBuilder = new self($variantUploaded);
                    $parentDriver = $this->context->getImageDriver();
                    if ($parentDriver !== null) {
                        $variantBuilder->imageDriver($parentDriver);
                    }
                    $parentStorage = $this->context->getStorage();
                    if ($parentStorage !== null) {
                        $variantBuilder->storage($parentStorage);
                    }

                    // Thực thi cấu hình biến thể
                    $configurator($variantBuilder);

                    if ($variantBuilder->isProcessed()) {
                        $variantResult = $variantBuilder->getLastResult() ?? $variantBuilder->process();
                    } elseif ($variantBuilder->getDestinationDir() === null && $this->destinationDir !== null) {
                        $variantBuilder->prefix("{$variantName}_");
                        $variantResult = $variantBuilder->saveTo($this->destinationDir, $this->chmod);
                    } else {
                        $variantResult = $variantBuilder->process();
                    }

                    if ($variantResult->isSuccess() && $variantResult->getFile() !== null) {
                        $processedVariants[$variantName] = $variantResult->getFile();
                    } else {
                        $variantErrors[$variantName] = $variantResult->getErrorMessage() ?? Translator::translate('ERR_VARIANT_FAILED');
                    }
                }
            }

            return $this->dispatchResult(new SingleResult(
                success: true,
                file: $processed,
                originalFile: $this->file,
                variants: $processedVariants,
                variantErrors: $variantErrors,
            ));

        } catch (UploadException $e) {
            // End users get a localized message; the English developer message stays in the context.
            $errorCode = $e->getErrorCode();
            $message = Translator::has($errorCode)
                ? Translator::translate($errorCode, $e->getContext())
                : Translator::translate('ERR_PROCESS_FAILED');

            return $this->dispatchResult(new SingleResult(
                success: false,
                originalFile: $this->file,
                errorMessage: $message,
                errorCode: $errorCode,
                context: $e->getContext() + ['exception' => $e::class, 'reason' => $e->getMessage()],
            ));
        } catch (\Throwable $e) {
            return $this->dispatchResult(new SingleResult(
                success: false,
                originalFile: $this->file,
                errorMessage: Translator::translate('ERR_INTERNAL'),
                errorCode: 'ERR_INTERNAL',
                context: ['exception' => $e::class, 'reason' => $e->getMessage()],
            ));
        } finally {
            if ($preservedTmpPath !== null && file_exists($preservedTmpPath)) {
                @unlink($preservedTmpPath);
            }
        }
    }

    private function dispatchResult(SingleResult $result): SingleResult
    {
        $this->isProcessed = true;
        $this->lastResult = $result;
        if ($result->isSuccess()) {
            foreach ($this->onSuccessHooks as $hook) {
                if ($result->getFile() !== null) {
                    $hook($result->getFile(), $result);
                }
            }
        } else {
            foreach ($this->onErrorHooks as $hook) {
                $hook($result->getErrorMessage() ?? '', $result->getErrorCode() ?? '', $result);
            }
        }

        foreach ($this->afterProcessHooks as $hook) {
            $hook($result);
        }

        return $result;
    }

    private function ensureRenameActionInPipeline(): void
    {
        if (!$this->renameActionAdded) {
            $this->pipeline->addAction($this->renameAction);
            $this->renameActionAdded = true;
        }
    }
}
