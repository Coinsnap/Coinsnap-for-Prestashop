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


use Coinsnap\Result\InvoicePaymentMethod;
use Coinsnap\Util\PreciseNumber;

class Invoice extends AbstractClient
{
    public function createInvoice(
        string $storeId,
        string $currency,
        ?PreciseNumber $amount = null,
        ?string $orderId = null,
        ?string $buyerEmail = null,
        ?string $customerName = null,
        ?string $redirectUrl = null,
        ?string $referralCode = null,
        ?array $metaData = null,
        ?InvoiceCheckoutOptions $checkoutOptions = null
    ): \Coinsnap\Result\Invoice {
        $url = $this->getApiUrl().''.COINSNAP_SERVER_PATH.'/'.urlencode($storeId).'/invoices';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        // Prepare metadata.
        $metaDataMerged = [];
        if(!empty($orderId)) {
            $metaDataMerged['orderNumber'] = $orderId;
        }
        if(!empty($customerName)) {
            $metaDataMerged['customerName'] = $customerName;
        }

        $body_array = array(
            'amount' => $amount !== null ? $amount->__toString() : null,
                'currency' => $currency,
                'buyerEmail' => $buyerEmail,
                'redirectUrl' => $redirectUrl,
                'orderId' => $orderId,
                'metadata' => (count($metaDataMerged) > 0) ? $metaDataMerged : null,
        //        'checkout' => $checkoutOptions ? $checkoutOptions->toArray() : null,
                'referralCode' => $referralCode
        );


        $body = json_encode($body_array, JSON_THROW_ON_ERROR);

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new \Coinsnap\Result\Invoice(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            print_r($response);
            exit;
            //throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getInvoice(string $storeId, string $invoiceId): \Coinsnap\Result\Invoice
    {

        $url = $this->getApiUrl().''.COINSNAP_SERVER_PATH.'/'.urlencode($storeId).'/invoices/'.urlencode($invoiceId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new \Coinsnap\Result\Invoice(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
