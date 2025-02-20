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

use Coinsnap\Exception\ConnectException;
use Coinsnap\Exception\RequestException;

interface ClientInterface
{
    /**
     * Sends the HTTP request to API server.
     *
     * @param string $method
     * @param string $url
     * @param array  $headers
     * @param string $body
     *
     * @throws ConnectException
     * @throws RequestException
     *
     * @return ResponseInterface
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): ResponseInterface;
}
