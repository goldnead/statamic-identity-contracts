<?php

namespace Goldnead\IdentityContracts\Support;

/**
 * Does this string look like an email address a person could actually have?
 *
 * `filter_var($value, FILTER_VALIDATE_EMAIL)` is the reflex, and it is wrong
 * for this question: it predates RFC 6531 and rejects every non-ASCII
 * character, in the local part and in the domain alike. So a contact whose
 * address carries an umlaut — stored happily by every addon in this family,
 * mailed to, subscribed, granted access — did not look like a contact to the
 * identity layer at all. It fell through to the anonymous actor, and the join
 * to the CRM record was quietly lost.
 *
 * Nothing failed visibly. The row was written, it simply had no `contact_uuid`,
 * which is exactly the column somebody later filters on to ask "what did this
 * person do?".
 *
 * The domain is judged through its punycode form; a Unicode local part is
 * judged on its own terms instead of being declared malformed.
 */
final class EmailShape
{
    public static function plausible(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        // 254 octets is the largest address an SMTP path can carry.
        if (strlen($value) > 254 || substr_count($value, '@') !== 1) {
            return false;
        }

        [$lokal, $domain] = explode('@', $value);

        if ($lokal === '' || $domain === '' || strlen($lokal) > 64) {
            return false;
        }

        if (($domain = self::punycode($domain)) === null) {
            return false;
        }

        if (! self::hatUnicode($lokal)) {
            return filter_var($lokal.'@'.$domain, FILTER_VALIDATE_EMAIL) !== false;
        }

        return mb_check_encoding($lokal, 'UTF-8')
            && ! preg_match('/[\s@,;:<>"\[\]\\\\]|[\x00-\x1F\x7F]/u', $lokal)
            && filter_var('probe@'.$domain, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Without ext-intl there is no way to judge an international domain.
     * Declining is better than guessing: the caller stays conservative.
     */
    private static function punycode(string $domain): ?string
    {
        if (! self::hatUnicode($domain)) {
            return $domain;
        }

        if (! function_exists('idn_to_ascii')) {
            return null;
        }

        $ascii = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return $ascii === false ? null : $ascii;
    }

    private static function hatUnicode(string $wert): bool
    {
        return (bool) preg_match('/[^\x00-\x7F]/', $wert);
    }
}
