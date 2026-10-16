<?php

declare(strict_types=1);

namespace UploadHelper\I18n;

use Stringable;
use UploadHelper\Exceptions\InvalidLocaleException;

/**
 * Translates error codes into end-user messages.
 *
 * Lookup order: current locale dictionary -> 'en' dictionary -> the key itself.
 */
final class Translator
{
    public const DEFAULT_LOCALE = 'en';

    private const LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?$/D';

    private static string $locale = self::DEFAULT_LOCALE;

    /** @var array<string, array<string, string>> */
    private static array $dictionaries = [];

    /** @var array<string, array<string, string>> */
    private static array $customTranslations = [];

    public static function setLocale(string $locale): void
    {
        self::assertValidLocale($locale);
        self::$locale = $locale;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Register or override translations for a locale without touching library files.
     *
     * @param array<string, string> $messages
     */
    public static function addTranslations(string $locale, array $messages): void
    {
        self::assertValidLocale($locale);
        self::$customTranslations[$locale] = array_merge(self::$customTranslations[$locale] ?? [], $messages);
    }

    /**
     * Whether a message template exists for the key (in the given locale or the 'en' fallback).
     */
    public static function has(string $key, ?string $locale = null): bool
    {
        return self::findTemplate($key, $locale ?? self::$locale) !== null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function translate(string $key, array $params = [], ?string $locale = null): string
    {
        $template = self::findTemplate($key, $locale ?? self::$locale) ?? $key;

        $replacements = [];
        foreach ($params as $paramKey => $value) {
            $formatted = self::formatValue($value);
            if ($formatted !== null) {
                $replacements['{' . $paramKey . '}'] = $formatted;
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * Restore the default locale and drop runtime-registered translations.
     */
    public static function reset(): void
    {
        self::$locale = self::DEFAULT_LOCALE;
        self::$customTranslations = [];
    }

    private static function findTemplate(string $key, string $locale): ?string
    {
        self::assertValidLocale($locale);

        foreach (array_unique([$locale, self::DEFAULT_LOCALE]) as $candidate) {
            if (isset(self::$customTranslations[$candidate][$key])) {
                return self::$customTranslations[$candidate][$key];
            }

            $dictionary = self::loadDictionary($candidate);
            if (isset($dictionary[$key])) {
                return $dictionary[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function loadDictionary(string $locale): array
    {
        if (!isset(self::$dictionaries[$locale])) {
            // Locale is validated against LOCALE_PATTERN before reaching here, so it cannot traverse paths.
            $file = __DIR__ . DIRECTORY_SEPARATOR . $locale . '.php';
            self::$dictionaries[$locale] = is_file($file) ? (array) require $file : [];
        }

        return self::$dictionaries[$locale];
    }

    private static function assertValidLocale(string $locale): void
    {
        if (preg_match(self::LOCALE_PATTERN, $locale) !== 1) {
            throw InvalidLocaleException::forLocale($locale);
        }
    }

    private static function formatValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $items = array_filter(array_map(self::formatValue(...), $value), static fn (?string $v): bool => $v !== null);

            return implode(', ', $items);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }
}
