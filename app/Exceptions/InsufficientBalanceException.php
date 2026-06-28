<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public static function fund(string $fundName, string $available, string $requested): self
    {
        return new self(
            "Saldo Dana Amanah \"{$fundName}\" tidak mencukupi. Tersedia: {$available}, diminta: {$requested}."
        );
    }

    public static function account(string $accountName, string $available, string $requested): self
    {
        return new self(
            "Saldo Kas/Bank \"{$accountName}\" tidak mencukupi. Tersedia: {$available}, diminta: {$requested}."
        );
    }
}
