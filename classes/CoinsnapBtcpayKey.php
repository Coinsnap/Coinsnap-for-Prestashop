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

if (!defined('_PS_VERSION_')) {
    exit;
}

// A pasted BTCPay key is accepted only when BTCPay itself says it is limited to the configured store.
class CoinsnapBtcpayKey
{
    // $permissions as returned by GET /api/v1/api-keys/current. Unrestricted, server, user
    // or all-stores rights control far more than this module needs, so they are refused.
    public static function isAcceptable(array $permissions, string $storeId): bool
    {
        if ($permissions === [] || $storeId === '') {
            return false;
        }
        foreach ($permissions as $permission) {
            [$policy, $scope] = array_pad(explode(':', (string) $permission, 2), 2, '');
            if (strpos($policy, 'btcpay.store.') !== 0 || $scope !== $storeId) {
                return false;
            }
        }

        return true;
    }

    // The key's permissions, or null when BTCPay does not confirm the key.
    // Throws when the server can't be reached, so the caller can tell that apart from a bad key.
    public static function fetchPermissions(string $serverUrl, string $apiKey, ?\Coinsnap\Http\ClientInterface $http = null): ?array
    {
        $http = $http ?? new \Coinsnap\Http\CurlClient();
        $response = $http->request('GET', rtrim($serverUrl, '/') . '/api/v1/api-keys/current', [
            'Accept' => 'application/json',
            'Authorization' => 'token ' . $apiKey,
        ]);
        if ((int) $response->getStatus() >= 500) {
            throw new \RuntimeException('BTCPay server unavailable');
        }
        if ((int) $response->getStatus() !== 200) {
            return null;
        }
        $key = json_decode($response->getBody(), true);

        return is_array($key['permissions'] ?? null) ? array_values($key['permissions']) : null;
    }
}
