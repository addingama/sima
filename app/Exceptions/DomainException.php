<?php

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis domain (transisi status tidak valid, dll.). */
class DomainException extends RuntimeException
{
}
