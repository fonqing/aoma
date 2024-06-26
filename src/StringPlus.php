<?php

namespace aoma;

class StringPlus
{
    /**
     * Encode string
     *
     * @param mixed $string
     * @return mixed|string
     */
    public static function htmlEncode(mixed $string): mixed
    {
        if (!is_string($string)) {
            return $string;
        }
        $string = rawurldecode(trim($string));
        $string = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $string);
        do {
            $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $string, -1, $count);
        } while ($count);
        return htmlentities(trim($string), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate random string
     *
     * @param string $type
     * @param int $length
     * @return string
     */
    public static function random(string $type = 'captcha', int $length = 4): string
    {
        $characters = match ($type) {
            'captcha' => 'ACEFGHJKLMNPQRTUVWXY345679',
            'number'  => '0123456789',
            'letters' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            default   => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        };
        return (string) substr(str_shuffle(str_repeat($characters, $length)), 0, $length);
    }

    /**
     * function str_contains() is available since PHP 8.0.0
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function str_contains(string $haystack, string $needle): bool
    {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }
        return strpos($haystack, $needle) !== false;
    }

    /**
     * Generate unique id
     *
     * @param string $prefix
     * @param int $length
     * @return string
     */
    public static function uniqueId(string $prefix = '', int $length = 13): string
    {
        if (function_exists("random_bytes")) {
            try {
                $bytes = random_bytes(ceil($length / 2));
            } catch (\Exception $e) {
                return uniqid($prefix);
            }
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($length / 2));
        } else {
            return uniqid($prefix);
        }
        return $prefix . substr(bin2hex($bytes), 0, $length);
    }

    /**
     * Split string into to parts by a specific character
     *
     * @param string $string
     * @param string $splitter
     * @return array|string
     */
    public static function split(string $string, string $splitter): array|string
    {
        if (empty($splitter)) {
            return str_split($string);
        }
        $pos = strpos($string, $splitter);
        if ($pos === false) {
            return $string;
        }
        return [
            substr($string, 0, $pos),
            substr($string, $pos + 1)
        ];
    }

}