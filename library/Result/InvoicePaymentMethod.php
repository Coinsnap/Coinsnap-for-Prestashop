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

class InvoicePaymentMethod extends AbstractResult
{
    /**
     * @return InvoicePayment[]
     */
    public function getPayments(): array
    {
        $r = [];
        $data = $this->getData();
        foreach ($data['payments'] as $payment) {
            $r[] = new \Coinsnap\Result\InvoicePayment($payment);
        }

        return $r;
    }

    public function getDestination(): string
    {
        $data = $this->getData();
        return $data['destination'];
    }

    public function getRate(): string
    {
        $data = $this->getData();
        return $data['rate'];
    }

    public function getPaymentMethodPaid(): string
    {
        $data = $this->getData();
        return $data['paymentMethodPaid'];
    }

    public function getTotalPaid(): string
    {
        $data = $this->getData();
        return $data['totalPaid'];
    }

    public function getDue(): string
    {
        $data = $this->getData();
        return $data['due'];
    }

    public function getAmount(): string
    {
        $data = $this->getData();
        return $data['amount'];
    }

    public function getNetworkFee(): string
    {
        $data = $this->getData();
        return $data['networkFee'];
    }

    public function getPaymentMethod(): string
    {
        $data = $this->getData();
        return $data['paymentMethod'];
    }

    public function getCryptoCode(): string
    {
        $data = $this->getData();
        // For future compatibility check if cryptoCode exists.
        if (isset($data['cryptoCode'])) {
            return $data['cryptoCode'];
        } else {
            // Extract cryptoCode from paymentMethod string.
            $parts = explode('-', $data['paymentMethod']);
            return $parts[0];
        }
    }
}
