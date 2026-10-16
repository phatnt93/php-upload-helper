<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocaleParityTest extends TestCase
{
    private const I18N_DIR = __DIR__ . '/../../../src/I18n';

    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        yield 'vi' => ['vi'];
    }

    #[DataProvider('localeProvider')]
    public function testLocaleHasSameKeysAsDefault(string $locale): void
    {
        $en = require self::I18N_DIR . '/en.php';
        $other = require self::I18N_DIR . "/{$locale}.php";

        self::assertSame([], array_keys(array_diff_key($en, $other)), "Missing keys in '{$locale}'.");
        self::assertSame([], array_keys(array_diff_key($other, $en)), "Extra keys in '{$locale}'.");
    }

    #[DataProvider('localeProvider')]
    public function testPlaceholdersMatchDefault(string $locale): void
    {
        $en = require self::I18N_DIR . '/en.php';
        $other = require self::I18N_DIR . "/{$locale}.php";

        foreach ($en as $key => $template) {
            self::assertNotSame('', trim($other[$key] ?? ''), "Empty translation for '{$key}' in '{$locale}'.");
            self::assertSame(
                self::placeholders($template),
                self::placeholders($other[$key] ?? ''),
                "Placeholder mismatch for '{$key}' in '{$locale}'."
            );
        }
    }

    /** @return list<string> */
    private static function placeholders(string $template): array
    {
        preg_match_all('/\{[a-z_]+\}/', $template, $matches);
        $found = array_values(array_unique($matches[0]));
        sort($found);

        return $found;
    }
}
