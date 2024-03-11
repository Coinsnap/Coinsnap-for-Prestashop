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
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@coinsnap.io so we can send you a copy immediately.
 *
 * @author    Coinsnap <dev@coinsnap.io>
 * @copyright Since 2023 Coinsnap
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
declare(strict_types=1);
namespace Coinsnap\Result;

if (!defined('_PS_VERSION_')) {
    exit;
}


abstract class AbstractResult implements \ArrayAccess
{
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function offsetExists($offset): bool
    {
        $data = $this->getData();
        return array_key_exists($offset, $data);
    }

    public function offsetGet($offset): mixed
    {
        $data = $this->getData();
        return $data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        throw new \RuntimeException('You should not change the data in a result.');
    }

    public function offsetUnset($offset): void
    {
        throw new \RuntimeException('You should not change the data in a result.');
    }
}
