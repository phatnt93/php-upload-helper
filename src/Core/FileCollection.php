<?php

declare(strict_types=1);

namespace UploadHelper\Core;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, UploadedFile>
 */
class FileCollection implements Countable, IteratorAggregate
{
    /**
     * @param array<int, UploadedFile> $files
     */
    public function __construct(private array $files = []) {}

    /**
     * @return array<int, UploadedFile>
     */
    public function all(): array
    {
        return $this->files;
    }

    public function count(): int
    {
        return count($this->files);
    }

    public function isEmpty(): bool
    {
        return empty($this->files);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->files);
    }

    public function first(): ?UploadedFile
    {
        return $this->files[0] ?? null;
    }

    public function last(): ?UploadedFile
    {
        if (empty($this->files)) {
            return null;
        }
        return $this->files[count($this->files) - 1];
    }

    public function add(UploadedFile $file): self
    {
        $this->files[] = $file;
        return $this;
    }

    /**
     * @param callable(UploadedFile, int): void $callback
     */
    public function each(callable $callback): self
    {
        foreach ($this->files as $index => $file) {
            $callback($file, $index);
        }
        return $this;
    }

    /**
     * @param callable(UploadedFile, int): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        $filtered = [];
        foreach ($this->files as $index => $file) {
            if ($predicate($file, $index)) {
                $filtered[] = $file;
            }
        }
        return new self($filtered);
    }

    /**
     * @template T
     * @param callable(UploadedFile, int): T $callback
     * @return array<int, T>
     */
    public function map(callable $callback): array
    {
        $result = [];
        foreach ($this->files as $index => $file) {
            $result[] = $callback($file, $index);
        }
        return $result;
    }

    /**
     * @return Traversable<int, UploadedFile>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->files);
    }
}
