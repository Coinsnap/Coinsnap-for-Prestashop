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
namespace Coinsnap\Client;

if (!defined('_PS_VERSION_')) {
    exit;
}


use Coinsnap\Exception\BadRequestException;
use Coinsnap\Exception\ForbiddenException;
use Coinsnap\Exception\RequestException;
use Coinsnap\Http\ClientInterface;
use Coinsnap\Http\CurlClient;
use Coinsnap\Http\Response;

class AbstractClient
{
    /** @var string */
    private $apiKey;
    /** @var string */
    private $baseUrl;
    /** @var string */
    private $apiPath = '/api/v1/';
    /** @var ClientInterface */
    private $httpClient;

    public function __construct(string $baseUrl, string $apiKey, ClientInterface $client = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;

        // Use the $client parameter to use a custom cURL client, for example if you need to disable CURLOPT_SSL_VERIFYHOST and CURLOPT_SSL_VERIFYPEER
        if ($client === null) {
            $client = new CurlClient();
        }
        $this->httpClient = $client;
    }

    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function getApiUrl(): string
    {
        return $this->baseUrl . $this->apiPath;
    }

    protected function getApiKey(): string
    {
        return $this->apiKey;
    }

    protected function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    protected function getRequestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-key' => $this->getApiKey()
        ];
    }

    protected function getExceptionByStatusCode(
        string $method,
        string $url,
        Response $response
    ): RequestException {
        $exceptions = [
            ForbiddenException::STATUS => ForbiddenException::class,
            BadRequestException::STATUS => BadRequestException::class,
        ];

        $class = $exceptions[$response->getStatus()] ?? RequestException::class;
        $e = new $class($method, $url, $response);
        return $e;
    }
}
