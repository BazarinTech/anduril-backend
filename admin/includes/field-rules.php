<?php
/**
 * ADMIN FIELD RULES
 * =================
 * What each editable admin field may hold, and the one code path that applies
 * it -- for the Add forms and for every actions/update_*.php endpoint.
 *
 * WHY THIS EXISTS
 * MySQL runs with STRICT_TRANS_TABLES. A value longer than its column, or text
 * in an INT column, is not truncated: the INSERT/UPDATE throws. None of the
 * admin handlers checked anything first, so an incentive name one character
 * too long, a product tier over 20 characters or a salary with too many
 * digits answered with a bare HTTP 500. Each rule's `max` is the width of the
 * column it writes to (db/schema.sql), so a value that passes here fits.
 *
 * RULE TYPES
 *   text         trimmed string, `max` characters (the columns are utf8mb4)
 *   amount       plain decimal, at most two places, `max` characters as stored
 *   count        whole number from `min` (default 0) to `max`
 *   choice       one of `options`, matched case-insensitively, stored as listed
 *   email        valid address, `max` characters
 *   phone        9-15 digits, an optional leading '+' dropped, `max` digits
 *   permissions  bracketed list drawn from ADMIN_PERMISSIONS, e.g. [view][edit]
 *   code         letters, digits, '-' and '_' only, `max` characters
 *
 * `unique` => true rejects a value another row of the same table already has.
 * `insert_only` => true accepts the field on an Add form but not in the inline
 * editor, keeping each table's editable fields what they were.
 */

const ADMIN_PERMISSIONS = ['view', 'edit', 'add', 'finance'];

if (!function_exists('admin_rule_sets')) {
    function admin_rule_sets()
    {
        $status = ['label' => 'Status', 'type' => 'choice', 'options' => ['Active', 'Inactive'], 'required' => true];

        return [
            'users' => [
                'name'   => ['label' => 'Name',   'type' => 'text',  'max' => 255, 'required' => true],
                'email'  => ['label' => 'Email',  'type' => 'email', 'max' => 255, 'required' => true, 'unique' => true],
                'phone'  => ['label' => 'Phone',  'type' => 'phone', 'max' => 13,  'required' => true, 'unique' => true],
                'status' => $status,
                'role'   => ['label' => 'Role',   'type' => 'choice', 'options' => ['user', 'agent'], 'required' => true],
                // `upline` is deliberately absent. The Users page marks it
                // read-only because changing it re-points referral commission,
                // but the endpoint still accepted it.
            ],

            'admins' => [
                'name'        => ['label' => 'Name',        'type' => 'text',        'max' => 255, 'required' => false],
                'email'       => ['label' => 'Email',       'type' => 'email',       'max' => 255, 'required' => false],
                'phone'       => ['label' => 'Phone',       'type' => 'phone',       'max' => 15,  'required' => false],
                'username'    => ['label' => 'Username',    'type' => 'text',        'max' => 20,  'required' => true, 'unique' => true, 'insert_only' => true],
                'roles'       => ['label' => 'Role',        'type' => 'text',        'max' => 255, 'required' => true],
                'permissions' => ['label' => 'Permissions', 'type' => 'permissions', 'max' => 255, 'required' => true],
                'status'      => $status,
            ],

            'bonus' => [
                'name'        => ['label' => 'Bonus name',   'type' => 'text',   'max' => 255, 'required' => true],
                'target'      => ['label' => 'Bonus target', 'type' => 'count',  'max' => 999999999, 'required' => true],
                'reward'      => ['label' => 'Bonus reward', 'type' => 'amount', 'max' => 10,  'required' => true],
                'type'        => ['label' => 'Target type',  'type' => 'choice', 'options' => ['users', 'actives'], 'required' => true],
                'reward_type' => ['label' => 'Reward type',  'type' => 'choice', 'options' => ['money', 'products'], 'required' => true],
                'status'      => $status,
            ],

            'coupons' => [
                'code'   => ['label' => 'Coupon code',       'type' => 'code',   'max' => 10, 'required' => true, 'unique' => true],
                'amount' => ['label' => 'Amount',            'type' => 'amount', 'max' => 10, 'required' => true],
                // Minutes from creation, stored in an INT. A year is plenty.
                'expiry' => ['label' => 'Expiry (minutes)',  'type' => 'count',  'min' => 1, 'max' => 525600, 'required' => true],
            ],

            'incentives' => [
                'name'      => ['label' => 'Incentive name', 'type' => 'text',   'max' => 100, 'required' => true],
                // Copied onto wallets.level when an application is approved;
                // both columns are 50 wide (migration 005).
                'level'     => ['label' => 'Level',          'type' => 'text',   'max' => 50,  'required' => true],
                'salary'    => ['label' => 'Salary',         'type' => 'amount', 'max' => 20,  'required' => true],
                'referrals' => ['label' => 'Referrals',      'type' => 'count',  'max' => 1000000, 'required' => true],
                'status'    => $status,
                'bonusItem' => ['label' => 'Bonus item',     'type' => 'text',   'max' => 255, 'required' => false],
            ],

            'orders' => [
                'status' => ['label' => 'Status', 'type' => 'choice', 'options' => ['Active', 'Expired', 'inactive'], 'required' => true],
            ],

            'products' => [
                'name'        => ['label' => 'Product name', 'type' => 'text',   'max' => 255, 'required' => true],
                'max'         => ['label' => 'Price',        'type' => 'amount', 'max' => 10,  'required' => true],
                'min'         => ['label' => 'Minimum',      'type' => 'amount', 'max' => 10,  'required' => true],
                'returns'     => ['label' => 'Return',       'type' => 'amount', 'max' => 10,  'required' => true],
                'duration'    => ['label' => 'Duration (days)', 'type' => 'count', 'min' => 1, 'max' => 36500, 'required' => true, 'insert_only' => true],
                'tier'        => ['label' => 'Tier',         'type' => 'text',   'max' => 20,  'required' => true],
                'riskLevel'   => ['label' => 'Risk level',   'type' => 'count',  'max' => 100, 'required' => true, 'insert_only' => true],
                'order_limit' => ['label' => 'Order limit',  'type' => 'count',  'max' => 1000000, 'required' => true],
                'description' => ['label' => 'Description',  'type' => 'text',   'max' => 255, 'required' => true, 'insert_only' => true],
                'status'      => $status,
            ],

            'transactions' => [
                // 'Completed' is written by transfers, rewards and bonuses even
                // though the editor's dropdown does not offer it.
                'status'      => ['label' => 'Status', 'type' => 'choice', 'options' => ['Success', 'Completed', 'Pending', 'Processing', 'Failed', 'Declined'], 'required' => true],
                'type'        => ['label' => 'Type',        'type' => 'text',   'max' => 30,  'required' => true],
                'amount'      => ['label' => 'Amount',      'type' => 'amount', 'max' => 10,  'required' => true],
                'fees'        => ['label' => 'Fee',         'type' => 'amount', 'max' => 10,  'required' => true],
                'description' => ['label' => 'Description', 'type' => 'text',   'max' => 255, 'required' => false],
                'account'     => ['label' => 'Account',     'type' => 'text',   'max' => 255, 'required' => false],
                'method'      => ['label' => 'Method',      'type' => 'text',   'max' => 10,  'required' => true],
            ],

            'wallets' => [
                'balance'       => ['label' => 'Balance',         'type' => 'amount', 'max' => 10, 'required' => true],
                'income'        => ['label' => 'Product income',  'type' => 'amount', 'max' => 10, 'required' => true],
                'invite_income' => ['label' => 'Downline income', 'type' => 'amount', 'max' => 10, 'required' => true],
                'today_income'  => ['label' => "Today's income",  'type' => 'amount', 'max' => 10, 'required' => true],
                'level'         => ['label' => 'Level',           'type' => 'text',   'max' => 50, 'required' => true],
                'status'        => $status,
            ],
        ];
    }
}

if (!function_exists('admin_rules')) {
    /** The rules for one table. */
    function admin_rules($table)
    {
        $sets = admin_rule_sets();

        if (!isset($sets[$table])) {
            throw new InvalidArgumentException("No field rules for table '{$table}'.");
        }

        return $sets[$table];
    }
}

if (!function_exists('admin_validate_value')) {
    /**
     * Validate and normalise one value against one rule.
     *
     * Returns [value, null] on success or [null, message] on failure. The value
     * is what should be stored: trimmed text, a digit string, a canonical
     * choice or permission list.
     */
    function admin_validate_value(array $rule, $raw)
    {
        $label = $rule['label'];

        // Arrays, objects and booleans are not a field value; a JSON body can
        // carry any of them.
        if (is_array($raw) || is_object($raw) || is_bool($raw)) {
            return [null, "{$label} is not valid."];
        }

        $value = trim((string) $raw);

        if ($value === '') {
            return !empty($rule['required']) ? [null, "{$label} is required."] : ['', null];
        }

        switch ($rule['type']) {
            case 'text':
                if (mb_strlen($value, 'UTF-8') > $rule['max']) {
                    return [null, "{$label} must be {$rule['max']} characters or fewer."];
                }
                return [$value, null];

            case 'code':
                if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
                    return [null, "{$label} may only contain letters, numbers, '-' and '_'."];
                }
                if (strlen($value) > $rule['max']) {
                    return [null, "{$label} must be {$rule['max']} characters or fewer."];
                }
                return [$value, null];

            case 'amount':
                // is_numeric() would accept "1e5", "-5" and " 12", and those
                // would be stored exactly as typed.
                if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
                    return [null, "{$label} must be a number, e.g. 5000 or 5000.50."];
                }
                if (strlen($value) > $rule['max']) {
                    return [null, "{$label} is too large."];
                }
                return [$value, null];

            case 'count':
                $min = $rule['min'] ?? 0;

                if (!preg_match('/^\d+$/', $value)) {
                    return [null, "{$label} must be a whole number."];
                }
                // Length first, so a 30-digit string is not cast to a float.
                if (strlen(ltrim($value, '0')) > strlen((string) $rule['max']) || (int) $value > $rule['max']) {
                    return [null, "{$label} must be " . number_format($rule['max']) . ' or less.'];
                }
                if ((int) $value < $min) {
                    return [null, "{$label} must be at least " . number_format($min) . '.'];
                }
                return [(string) (int) $value, null];

            case 'choice':
                foreach ($rule['options'] as $option) {
                    if (strcasecmp($option, $value) === 0) {
                        return [$option, null];
                    }
                }
                return [null, "{$label} must be one of: " . implode(', ', $rule['options']) . '.'];

            case 'email':
                if (strlen($value) > $rule['max'] || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    return [null, "{$label} must be a valid email address."];
                }
                return [$value, null];

            case 'phone':
                $digits = preg_replace('/[\s-]/', '', $value);
                $digits = ltrim($digits, '+');

                if (!preg_match('/^\d{9,15}$/', $digits)) {
                    return [null, "{$label} must be 9 to 15 digits, e.g. 254712345678."];
                }
                if (strlen($digits) > $rule['max']) {
                    return [null, "{$label} must be {$rule['max']} digits or fewer."];
                }
                return [$digits, null];

            case 'permissions':
                // Accept "[view][edit]" and store it in a fixed order, so the
                // same rights always read the same way.
                if (!preg_match('/^(\[[a-z]+\])+$/', strtolower(preg_replace('/\s+/', '', $value)))) {
                    return [null, "{$label} must be a bracketed list, e.g. [view][edit][add][finance]."];
                }
                preg_match_all('/\[([a-z]+)\]/', strtolower($value), $m);
                $unknown = array_diff($m[1], ADMIN_PERMISSIONS);
                if ($unknown) {
                    return [null, "Unknown permission '" . reset($unknown) . "'. Use: " . implode(', ', ADMIN_PERMISSIONS) . '.'];
                }
                $kept = array_values(array_intersect(ADMIN_PERMISSIONS, $m[1]));
                return ['[' . implode('][', $kept) . ']', null];
        }

        return [null, "{$label} is not valid."];
    }
}

if (!function_exists('admin_grant_problem')) {
    /**
     * Whether the signed-in admin may set another admin's permissions from
     * $current to $proposed.
     *
     * Every permission that changes -- added or removed -- must be one the
     * acting admin holds. Otherwise [edit] or [add] would be enough to create
     * a second account with [finance], or to strip [finance] from the admin
     * who has it and leave nobody able to put it back.
     */
    function admin_grant_problem($query, $proposed, $current = '')
    {
        $parse = function ($list) {
            preg_match_all('/\[([a-z]+)\]/', strtolower((string) $list), $m);
            return $m[1];
        };

        $mine     = admin_permissions($query);
        $proposed = $parse($proposed);
        $current  = $parse($current);

        foreach (array_diff($proposed, $current) as $added) {
            if (!in_array($added, $mine, true)) {
                return "You cannot grant '{$added}' because your own account does not have it.";
            }
        }

        foreach (array_diff($current, $proposed) as $removed) {
            if (!in_array($removed, $mine, true)) {
                return "You cannot remove '{$removed}' because your own account does not have it.";
            }
        }

        return null;
    }
}

if (!function_exists('admin_outranks')) {
    /** Whether an admin with $permissions holds anything the signed-in admin does not. */
    function admin_outranks($query, $permissions)
    {
        preg_match_all('/\[([a-z]+)\]/', strtolower((string) $permissions), $m);

        return (bool) array_diff($m[1], admin_permissions($query));
    }
}

if (!function_exists('admin_value_taken')) {
    /** Whether another row of $table already holds $value in $column. */
    function admin_value_taken($query, $table, $column, $value, $exceptId = 0)
    {
        foreach ($query->select($table, 'ID', [$column => $value]) as $row) {
            if ((int) $row['ID'] !== (int) $exceptId) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('admin_validate_form')) {
    /**
     * Validate a set of fields for an insert.
     *
     * $input maps each column to its raw value. Returns [values, null] or
     * [null, first message].
     */
    function admin_validate_form($query, $table, array $input)
    {
        $rules  = admin_rules($table);
        $values = [];

        foreach ($input as $column => $raw) {
            [$value, $problem] = admin_validate_value($rules[$column], $raw);

            if ($problem !== null) {
                return [null, $problem];
            }

            if (!empty($rules[$column]['unique']) && $value !== '' && admin_value_taken($query, $table, $column, $value)) {
                return [null, "{$rules[$column]['label']} '{$value}' is already in use."];
            }

            $values[$column] = $value;
        }

        return [$values, null];
    }
}

if (!function_exists('admin_form_input')) {
    /** A POST field as a string; anything else (arrays, absent) as ''. */
    function admin_form_input($name)
    {
        $raw = $_POST[$name] ?? '';

        return is_string($raw) ? $raw : '';
    }
}

if (!function_exists('admin_update_action')) {
    /**
     * The whole body of an actions/update_*.php endpoint.
     *
     * Reads {id, field, value}, checks the field is editable, validates the
     * value, confirms the row exists, runs $check for anything that depends on
     * the rest of the row, and writes. Always answers JSON; never lets a
     * database error surface as a 500 page.
     *
     * $check, when given, is called as $check($field, $value, $row) and returns
     * an error message or null.
     */
    function admin_update_action($query, $fileGetContent, $table, ?callable $check = null)
    {
        $data  = $fileGetContent->get_content();
        $id    = is_array($data) && isset($data['id']) && is_scalar($data['id']) ? (int) $data['id'] : 0;
        $field = is_array($data) && isset($data['field']) && is_string($data['field']) ? $data['field'] : '';
        $value = is_array($data) && array_key_exists('value', $data) ? $data['value'] : null;

        $reply = function ($status, $success, $message) use ($fileGetContent) {
            http_response_code($status);
            $fileGetContent->send_content(['success' => $success, 'message' => $message]);
            exit;
        };

        if ($id <= 0) {
            $reply(400, false, 'Invalid record id');
        }

        // `field` becomes a column name in the UPDATE, so the rule set is also
        // the allowlist.
        $rules = admin_rules($table);

        if (!isset($rules[$field]) || !empty($rules[$field]['insert_only'])) {
            $reply(400, false, 'Invalid field');
        }

        [$clean, $problem] = admin_validate_value($rules[$field], $value);

        if ($problem !== null) {
            $reply(422, false, $problem);
        }

        try {
            $row = $query->select($table, '*', ['ID' => $id])[0] ?? null;

            if ($row === null) {
                $reply(404, false, 'That record no longer exists.');
            }

            if (!empty($rules[$field]['unique']) && $clean !== '' && admin_value_taken($query, $table, $field, $clean, $id)) {
                $reply(409, false, "{$rules[$field]['label']} '{$clean}' is already in use.");
            }

            if ($check !== null) {
                $problem = $check($field, $clean, $row);

                if ($problem !== null) {
                    $reply(422, false, $problem);
                }
            }

            $query->update($table, [$field => $clean], ['ID' => $id]);
        } catch (\Throwable $e) {
            error_log("[admin/update_{$table}] " . $e->getMessage());
            $reply(500, false, 'Could not save the change. Please try again.');
        }

        $reply(200, true, 'Updated successfully');
    }
}
