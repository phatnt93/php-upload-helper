<?php

declare(strict_types=1);

namespace UploadHelper\Core;

use UploadHelper\Contracts\ImageDriverInterface;
use UploadHelper\Contracts\StorageInterface;

class ActionContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private ?string $destinationDir = null,
        private bool $overwrite = false,
        private bool $autoIncrement = false,
        private int $chmod = 0755,
        private ?ImageDriverInterface $imageDriver = null,
        private ?StorageInterface $storage = null,
        private array $attributes = []
    ) {
    }

    public function getDestinationDir(): ?string
    {
        return $this->destinationDir;
    }

    public function setDestinationDir(?string $dir): self
    {
        $this->destinationDir = $dir;
        return $this;
    }

    public function isOverwrite(): bool
    {
        return $this->overwrite;
    }

    public function setOverwrite(bool $overwrite): self
    {
        $this->overwrite = $overwrite;
        return $this;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(bool $autoIncrement): self
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    public function getChmod(): int
    {
        return $this->chmod;
    }

    public function setChmod(int $chmod): self
    {
        $this->chmod = $chmod;
        return $this;
    }

    public function getImageDriver(): ?ImageDriverInterface
    {
        return $this->imageDriver;
    }

    public function setImageDriver(?ImageDriverInterface $driver): self
    {
        $this->imageDriver = $driver;
        return $this;
    }

    public function getStorage(): ?StorageInterface
    {
        return $this->storage;
    }

    public function setStorage(?StorageInterface $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }
}
