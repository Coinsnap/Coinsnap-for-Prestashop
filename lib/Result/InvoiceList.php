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


class InvoiceList extends AbstractListResult
{
    /**
     * @return \Coinsnap\Result\Invoice[]
     */
    public function all(): array
    {
        $invoices = [];
        foreach ($this->getData() as $invoice) {
            $invoices[] = new \Coinsnap\Result\Invoice($invoice);
        }
        return $invoices;
    }

    /**
     * @return \Coinsnap\Result\Invoice[]
     */
    public function getInvoicesByStatus(string $status): array
    {
        $r = array_filter(
            $this->getInvoices(),
            function (\Coinsnap\Result\Invoice $invoice) use ($status) {
                return $invoice->getStatus() === $status;
            }
        );

        // Renumber results
        return array_values($r);
    }

    /**
     * @deprecated 2.0.0 Please use `all()` instead.
     * @see all()
     *
     * @return \Coinsnap\Result\Invoice[]
     */
    public function getInvoices(): array
    {
        $r = [];
        foreach ($this->getData() as $invoiceData) {
            $r[] = new \Coinsnap\Result\Invoice($invoiceData);
        }
        return $r;
    }
}
