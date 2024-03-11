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


class InvoicePayment extends AbstractResult
{
    public function getValue(): string
    {
        $data = $this->getData();
        return $data['value'];
    }

    public function getFee(): string
    {
        $data = $this->getData();
        return $data['fee'];
    }

    public function getDestination(): string
    {
        $data = $this->getData();
        return $data['destination'];
    }

    public function getStatus(): string
    {
        $data = $this->getData();
        return $data['status'];
    }

    public function getTransactionId(): string
    {
        $data = $this->getData();
        $id = $data['id'];
        $parts = explode('-', $id);
        return $parts[0];
    }

    /**
     * @return int Unix timestamp in seconds.
     */
    public function getReceivedTimestamp(): int
    {
        $data = $this->getData();
        return (int) $data['receivedDate'];
    }
}
