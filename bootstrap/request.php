<?php
/**
 * REQUEST INPUT
 * =============
 * Reading fields out of a decoded JSON body without trusting its shape.
 *
 * Endpoints used to read `$data['amount']`, `$data['userID']` and so on
 * directly. That fails in three distinct ways, and all of them were reachable
 * with a hand-written request:
 *
 *   missing key   "Undefined array key" warning, then NULL flows onward -- into
 *                 a NOT NULL column (a transfer died at its INSERT), or into a
 *                 function that demands a string.
 *   wrong type    `{"userID": ["x"]}` passes isset() and reaches
 *                 JWT::decode(), which declares `string $jwt`. That throws a
 *                 TypeError -- an Error, not an Exception -- so the endpoints'
 *                 `catch (\Exception $e)` does not see it, and the response is
 *                 an uncaught fatal rendered as an HTML 500.
 *   non-array     a body of `"hello"` decodes to a string, and indexing a
 *                 string with a key is itself an error.
 *
 * Every accessor here returns a plain string and never throws, so a malformed
 * request becomes an ordinary validation failure with a JSON answer.
 */

if (!function_exists('request_str')) {
    /**
     * A scalar field as a string, or $default when absent or not a scalar.
     *
     * Trimmed by default. Pass $trim = false for secrets: a password with a
     * trailing space is a different password, and silently trimming it would
     * lock that user out.
     */
    function request_str($data, $key, $default = '', $trim = true)
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        // Arrays and objects are not a field value. Casting one produces
        // "Array" and an "Array to string conversion" warning.
        if ($value === null || is_array($value) || is_object($value)) {
            return $default;
        }

        if (is_bool($value)) {
            return $default;
        }

        $value = (string) $value;

        return $trim ? trim($value) : $value;
    }
}

if (!function_exists('request_token')) {
    /**
     * The session token, as a string JWT::decode() will accept.
     *
     * An absent or malformed token comes back as '' rather than null or an
     * array. JWT::decode('') throws UnexpectedValueException ("Wrong number of
     * segments"), which IS an Exception, so every endpoint's existing
     * `catch (\Exception)` turns it into the usual "Invalid token" response
     * instead of a fatal.
     */
    function request_token($data)
    {
        return request_str($data, 'userID', '', false);
    }
}

if (!function_exists('request_choice')) {
    /**
     * A field that must be one of a fixed set of values.
     *
     * Returns $default when the field is absent, and null when it is present
     * but not one of $allowed -- so the caller can tell "not supplied" from
     * "supplied something invalid" and reject only the latter.
     */
    function request_choice($data, $key, array $allowed, $default)
    {
        $value = strtolower(request_str($data, $key));

        if ($value === '') {
            return $default;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }
}
