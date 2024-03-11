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


class Store extends AbstractClient
{
    public function getStore($storeId): \Coinsnap\Result\Store
    {
        $url = $this->getApiUrl().COINSNAP_SERVER_PATH.'/' . urlencode($storeId);

        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            $json_decode = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            // \Coinsnap\WC\Helper\Logger::debug( 'ConnectionSettings: ' . print_r( $json_decode, true ), true );
            if(json_last_error() === JSON_ERROR_NONE) {
                return new \Coinsnap\Result\Store($json_decode);
            } else {
                return new \Coinsnap\Result\Store(array('result' => false, 'error' => 'Coinsnap server is not available'));
            }
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * @return \Coinsnap\Result\Store[]
     */
    public function getStores(): array
    {
        $url = $this->getApiUrl().COINSNAP_SERVER_PATH;
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            $r = [];
            $data = json_decode($response->getBody(), true);
            foreach ($data as $item) {
                $item = new \Coinsnap\Result\Store($item);
                $r[] = $item;
            }
            return $r;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
