<?php

use Extcode\CartPayone\Controller\Order\PaymentController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

// configure plugins

ExtensionUtility::configurePlugin(
    'CartPayone',
    'Cart',
    [
        PaymentController::class => 'success, cancel',
    ],
    [
        PaymentController::class => 'success, cancel',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
