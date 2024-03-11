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
if (!defined('_PS_VERSION_')) {
    exit;
}

spl_autoload_register(function ($className) {
    $searchPattern = 'Coinsnap';
    // Abort here if we do not try to load Coinsnap namespace.
    if (strpos($className, $searchPattern) !== 0) {
        return;
    }

    // Convert namespace and class to file path.
    $filePath =  __DIR__ . str_replace([$searchPattern, '\\'], ['', DIRECTORY_SEPARATOR], $className).'.php';
    if (file_exists($filePath)) {
        require_once($filePath);
        return;
    }
});
