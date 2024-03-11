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


use Coinsnap\Util\PreciseNumber;

class Invoice extends AbstractResult
{
    public const STATUS_NEW = 'New';
    public const STATUS_INVALID = 'Invalid';
    public const STATUS_SETTLED = 'Settled';
    public const STATUS_EXPIRED = 'Expired';
    public const STATUS_PROCESSING = 'Processing';
    public const ADDITIONAL_STATUS_PAID_PARTIAL = 'PaidPartial';
    public const ADDITIONAL_STATUS_PAID_OVER = 'PaidOver';
    public const ADDITIONAL_STATUS_MARKED = 'Marked';
    public const ADDITIONAL_STATUS_PAID_LATE = 'PaidLate';

    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getAmount(): PreciseNumber
    {
        return PreciseNumber::parseString($this->getData()['amount']);
    }

    public function getCurrency(): string
    {
        return $this->getData()['currency'];
    }

    public function getType(): string
    {
        return $this->getData()['type'];
    }

    public function getCheckoutLink(): string
    {
        return $this->getData()['checkoutLink'];
    }

    public function getCreatedTime(): int
    {
        return $this->getData()['createdTime'];
    }

    public function getExpirationTime(): int
    {
        return $this->getData()['expirationTime'];
    }

    public function getMonitoringTime(): int
    {
        return $this->getData()['monitoringTime'];
    }

    public function isArchived(): bool
    {
        return $this->getData()['archived'];
    }

    public function isPaid(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_SETTLED || $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_PARTIAL;
    }

    public function isNew(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_NEW;
    }

    public function isFullyPaid(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_SETTLED;
    }

    public function getStatus(): string
    {
        return $this->getData()['status'];
    }

    public function isExpired(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_EXPIRED;
    }

    public function isProcessing(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_PROCESSING;
    }

    public function isInvalid(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_INVALID;
    }

    public function isOverpaid(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_OVER;
    }

    public function isPartiallyPaid(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_PARTIAL;
    }

    public function isMarked(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_MARKED;
    }

    public function isPaidLate(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_LATE;
    }

    public function getAvailableStatusesForManualMarking(): array //    @return string[] Example: ["Settled", "Invalid"]
    {return $this->getData()['availableStatusesForManualMarking'];
    }
}
