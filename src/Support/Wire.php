<?php

declare(strict_types=1);

namespace PayamBridge\Support;

use InvalidArgumentException;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Http\OperationContext;

final class Wire
{
    public static function integer(string $value): int
    {
        if (! preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('ID/cursor is not a canonical integer within the platform range.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $data */
    public static function id(array $data, string $field, OperationContext $context): string
    {
        $value = $data[$field] ?? null;
        if ((! is_int($value) && ! is_string($value)) || (string) $value === '') {
            throw new SdkException(ErrorKind::Protocol, $context);
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
    public static function object(mixed $value, OperationContext $context): array
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new SdkException(ErrorKind::Protocol, $context);
        }

        return $value;
    }

    public static function text(string $text, ?int $max = null): void
    {
        if ($text === '' || ! mb_check_encoding($text, 'UTF-8') || ($max !== null && mb_strlen($text, 'UTF-8') > $max)) {
            throw new InvalidArgumentException('Text must be valid, nonempty UTF-8 and within the provider limit.');
        }
    }
}
