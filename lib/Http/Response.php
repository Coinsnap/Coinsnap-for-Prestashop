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
namespace Coinsnap\Http;

if (!defined('_PS_VERSION_')) {
    exit;
}


class Response implements ResponseInterface
{
    /** @var int */
    private $status;

    /** @var string */
    private $body;

    /** @var array */
    private $headers;

    public function __construct(int $status, string $body, array $headers)
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
