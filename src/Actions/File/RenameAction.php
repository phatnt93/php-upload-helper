<?php

declare(strict_types=1);

namespace UploadHelper\Actions\File;

use Closure;
use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;

class RenameAction implements ActionInterface
{
    private ?string $strategy = null;
    private int $randomLength = 16;
    private string $hashAlgo = 'sha256';
    private int $hashLength = 64;
    private ?string $prefix = null;
    private ?string $suffix = null;
    /** @var string|Closure|null */
    private string|Closure|null $customName = null;

    public function getName(): string
    {
        return 'rename';
    }

    public function slug(): self
    {
        $this->strategy = 'slug';
        return $this;
    }

    public function uuid(): self
    {
        $this->strategy = 'uuid';
        return $this;
    }

    public function random(int $length = 16): self
    {
        $this->strategy = 'random';
        $this->randomLength = max(4, $length);
        return $this;
    }

    public function timestamp(): self
    {
        $this->strategy = 'timestamp';
        return $this;
    }

    public function hash(string $algo = 'sha256', int $length = 64): self
    {
        $this->strategy = 'hash';
        $this->hashAlgo = $algo;
        $this->hashLength = max(8, min(128, $length));
        return $this;
    }

    /**
     * @param string|Closure(UploadedFile): string $name
     */
    public function custom(string|Closure $name): self
    {
        $this->strategy = 'custom';
        $this->customName = $name;
        return $this;
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

    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        $baseName = $file->getCurrentName();

        if ($this->strategy !== null) {
            $baseName = match ($this->strategy) {
                'slug'      => self::slugify($file->getOriginalName()),
                'uuid'      => self::generateUuid(),
                'random'    => self::generateRandom($this->randomLength),
                'timestamp' => date('Ymd_His_') . bin2hex(random_bytes(2)),
                'hash'      => substr($file->getHash($this->hashAlgo), 0, $this->hashLength),
                'custom'    => is_callable($this->customName)
                    ? (string) ($this->customName)($file)
                    : (string) $this->customName,
                default     => $baseName,
            };
        }

        if ($this->prefix !== null && $this->prefix !== '') {
            $baseName = $this->prefix . $baseName;
        }

        if ($this->suffix !== null && $this->suffix !== '') {
            $baseName = $baseName . $this->suffix;
        }

        $file->setCurrentName($baseName);

        return $file;
    }

    public static function slugify(string $text): string
    {
        // 1. Lấy phần filename bỏ extension
        $filename = pathinfo($text, PATHINFO_FILENAME);

        // 2. Chuyển đổi ký tự tiếng Việt có dấu
        $vietnameseMap = [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
            'À' => 'a', 'Á' => 'a', 'Ả' => 'a', 'Ã' => 'a', 'Ạ' => 'a',
            'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a', 'Ặ' => 'a',
            'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a', 'Ậ' => 'a',
            'Đ' => 'd',
            'È' => 'e', 'É' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e', 'Ẹ' => 'e',
            'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ể' => 'e', 'Ễ' => 'e', 'Ệ' => 'e',
            'Ì' => 'i', 'Í' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i', 'Ị' => 'i',
            'Ò' => 'o', 'Ó' => 'o', 'Ỏ' => 'o', 'Õ' => 'o', 'Ọ' => 'o',
            'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o', 'Ộ' => 'o',
            'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o', 'Ợ' => 'o',
            'Ù' => 'u', 'Ú' => 'u', 'Ủ' => 'u', 'Ũ' => 'u', 'Ụ' => 'u',
            'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ử' => 'u', 'Ữ' => 'u', 'Ự' => 'u',
            'Ỳ' => 'y', 'Ý' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y', 'Ỵ' => 'y',
        ];
        $filename = strtr($filename, $vietnameseMap);

        // 3. Thay thế ký tự không phải chữ và số thành dấu gạch ngang
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filename);
        $slug = trim((string) $slug, '-_');

        return strtolower($slug !== '' ? $slug : 'file');
    }

    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function generateRandom(int $length = 16): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Random name length must be at least 1.');
        }

        $bytes = max(1, intdiv($length + 1, 2));
        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }
}
