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

namespace Coinsnap\Result;

if (!defined('_PS_VERSION_')) {
    exit;
}

class User extends AbstractResult
{
    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }

    public function getEmail(): string
    {
        $data = $this->getData();
        return $data['email'];
    }

    public function emailedConfirmed(): bool
    {
        $data = $this->getData();
        return $data['emailedConfirmed'];
    }

    public function requiresEmailConfirmation(): bool
    {
        $data = $this->getData();
        return $data['requiresEmailConfirmation'];
    }

    public function getCreated(): int
    {
        $data = $this->getData();
        return $data['created'];
    }

    public function getRoles(): array
    {
        $data = $this->getData();
        return $data['roles'];
    }
}
