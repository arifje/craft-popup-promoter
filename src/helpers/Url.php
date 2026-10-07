<?php

declare(strict_types=1);

namespace arifje\craftpopuppromoter\helpers;

final class Url
{
    public static function safeCta(string $url): string
    {
        $url = trim($url, ' ');

        // Browsers can ignore control characters in schemes or treat backslashes as slashes.
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false) {
            return '';
        }

        if ($scheme !== null) {
            if (!in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
                return '';
            }

            if (in_array(strtolower($scheme), ['http', 'https'], true) && !filter_var($url, FILTER_VALIDATE_URL)) {
                return '';
            }
        }

        return $url;
    }
}
