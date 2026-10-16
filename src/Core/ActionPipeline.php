<?php

declare(strict_types=1);

namespace UploadHelper\Core;

use UploadHelper\Contracts\ActionInterface;

class ActionPipeline
{
    /** @var array<int, ActionInterface> */
    private array $actions = [];

    /**
     * @param array<int, ActionInterface> $actions
     */
    public function __construct(array $actions = [])
    {
        $this->actions = $actions;
    }

    public function addAction(ActionInterface $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    /**
     * @return array<int, ActionInterface>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Thực thi tuần tự toàn bộ chuỗi Action trên tệp upload
     */
    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        $currentFile = $file;
        foreach ($this->actions as $action) {
            $currentFile = $action->execute($currentFile, $context);
        }
        return $currentFile;
    }
}
