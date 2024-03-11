<?php
/**
 * Copyright since 2023 Coinsnap
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Coinsnap <dev@coinsnap.io>
 * @copyright Since 2023 Coinsnap
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
declare(strict_types=1);
namespace Coinsnap\Util;

if (!defined('_PS_VERSION_')) {
    exit;
}


class PreciseNumber
{
    /** @var string */
    private $value;

    public static function parseFloat(float $value, int $precision = 8): PreciseNumber
    {
        return self::parseString(number_format($value, $precision, '.', ''));
    }

    public static function parseInt(int $value): PreciseNumber
    {
        return self::parseString((string)$value);
    }

    public static function parseString(string $value): PreciseNumber
    {
        if (is_numeric($value)) {
            return new self((string)$value);
        } else {
            throw new \InvalidArgumentException('Argument should be numeric.');
        }
    }

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
