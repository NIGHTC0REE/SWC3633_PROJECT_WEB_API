<?php

function register_error_handlers(): void
{
    ini_set('display_errors', '0');

    set_error_handler(function (int $severity, string $message, string $file, int $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        return false;
    });

    set_exception_handler(function (Throwable $exception) {
        error_log($exception);

        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error(409, 'Request conflicts with existing data');
        }

        json_error(500, 'Internal server error');
    });
}
