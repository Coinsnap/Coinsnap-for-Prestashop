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

namespace Coinsnap\Client;

if (!defined('_PS_VERSION_')) {
    exit;
}
use Coinsnap\Result\ServerInfo;

class Server extends AbstractClient
{
    public function getInfo(): ServerInfo
    {
        $url = $this->getApiUrl().COINSNAP_SERVER_PATH.'/';//.urlencode($storeId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ServerInfo(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, (int)$response->getStatus(), $response->getBody());
        }
    }

    public function getHealthStatus(): bool
    {
        $url = $this->getApiUrl() . COINSNAP_SERVER_PATH.'/health';
        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, (int)$response->getStatus(), $response->getBody());
        }
    }
}
