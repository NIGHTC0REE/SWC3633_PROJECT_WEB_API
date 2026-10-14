<?php

const DATETIME_FORMATS = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'];

function parse_datetime(string $value): ?DateTime
{
    foreach (DATETIME_FORMATS as $format) {
        $date = DateTime::createFromFormat($format, $value);

        if ($date && $date->format($format) === $value) {
            return $date;
        }
    }

    return null;
}

function password_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Min 8 characters';
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Must contain letters and numbers';
    }

    return null;
}

function rule_error($value, array $rules): ?string
{
    $numeric = in_array('int', $rules, true) || in_array('numeric', $rules, true);

    foreach ($rules as $rule) {
        [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

        $error = match ($name) {
            'string'   => is_string($value) ? null : 'Must be text',
            'int'      => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'Must be a whole number',
            'numeric'  => is_numeric($value) ? null : 'Must be a number',
            'bool'     => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true) ? null : 'Must be true or false',
            'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Valid email required',
            'phone'    => preg_match('/^[0-9+\-]{7,20}$/', (string) $value) ? null : 'Invalid phone',
            'password' => password_error((string) $value),
            'datetime' => parse_datetime((string) $value) ? null : 'Use format YYYY-MM-DD HH:MM',
            'date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) && strtotime((string) $value) ? null : 'Use format YYYY-MM-DD',
            'in'       => in_array((string) $value, explode(',', $argument), true) ? null : 'Must be one of: ' . $argument,
            'min'      => ($numeric ? $value >= $argument : mb_strlen((string) $value) >= $argument) ? null : 'Minimum is ' . $argument,
            'max'      => ($numeric ? $value <= $argument : mb_strlen((string) $value) <= $argument) ? null : 'Maximum is ' . $argument,
            default    => null,
        };

        if ($error) {
            return $error;
        }
    }

    return null;
}

function cast_value($value, array $rules)
{
    if (in_array('int', $rules, true)) {
        return (int) $value;
    }

    if (in_array('numeric', $rules, true)) {
        return (float) $value;
    }

    if (in_array('bool', $rules, true)) {
        return in_array($value, [true, 1, '1', 'true'], true) ? 1 : 0;
    }

    if (in_array('datetime', $rules, true)) {
        return parse_datetime((string) $value)->format('Y-m-d H:i:s');
    }

    return $value;
}

function validate(array $input, array $rules, bool $partial = false): array
{
    $errors = [];
    $clean  = [];

    foreach ($rules as $field => $ruleString) {
        $ruleList = explode('|', $ruleString);
        $value    = $input[$field] ?? null;
        $value    = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            if (in_array('required', $ruleList, true) && !$partial) {
                $errors[$field] = 'Required';
            }

            continue;
        }

        if ($error = rule_error($value, $ruleList)) {
            $errors[$field] = $error;
            continue;
        }

        $clean[$field] = cast_value($value, $ruleList);
    }

    if ($errors) {
        json_error(422, 'Validation failed', $errors);
    }

    return $clean;
}
