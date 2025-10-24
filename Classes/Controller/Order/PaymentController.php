<?php

namespace Extcode\CartPayone\Controller\Order;

/*
 * This file is part of the package extcode/cart-payone.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Service\SessionHandler;
use Extcode\CartPayone\Event\Order\CancelEvent;
use Extcode\CartPayone\Event\Order\FinishEvent;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Psr\EventDispatcher\EventDispatcherInterface;

class PaymentController extends ActionController
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var array
     */
    protected $cartPluginSettings;

    /**
     * @var array
     */
    protected $pluginSettings;

    public function __construct(
        protected PersistenceManager $persistenceManager,
        protected SessionHandler $sessionHandler,
        protected CartRepository $cartRepository,
        protected PaymentRepository $paymentRepository,
        protected EventDispatcherInterface $eventDispatcher
    ) {
    }

    protected function initializeAction(): void
    {
        $this->cartPluginSettings =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->pluginSettings =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartPayone'
            );
    }

    public function successAction(): ResponseInterface
    {
        if ($this->request->hasArgument('hash') && !empty($this->request->getArgument('hash'))) {
            $hash = $this->request->getArgument('hash');

            $querySettings = $this->cartRepository->createQuery()->getQuerySettings();
            $querySettings->setStoragePageIds([$this->cartPluginSettings['settings']['order']['pid']]);
            $this->cartRepository->setDefaultQuerySettings($querySettings);

            $this->cart = $this->cartRepository->findOneBy(['sHash' => $hash]);

            if ($this->cart) {
                $orderItem = $this->cart->getOrderItem();
                $payment = $orderItem->getPayment();

                if ($payment->getStatus() !== 'paid') {
                    $payment->setStatus('paid');

                    $this->paymentRepository->update($payment);
                    $this->persistenceManager->persistAll();

                    $orderItem = $this->cart->getOrderItem();
                    $finishEvent = new FinishEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                    $this->eventDispatcher->dispatch($finishEvent);
                }

                return $this->redirect('show', 'Cart\Order', 'Cart', ['orderItem' => $orderItem]);
            } else {
                $this->addFlashMessage(
                    LocalizationUtility::translate(
                        'tx_cartpayone.controller.order.payment.action.success.error_occured',
                        'CartPayone'
                    ),
                    '',
                    ContextualFeedbackSeverity::ERROR
                );
            }
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpayone.controller.order.payment.action.success.access_denied',
                    'CartPayone'
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->htmlResponse();
    }

    public function cancelAction(): ResponseInterface
    {
        if ($this->request->hasArgument('hash') && !empty($this->request->getArgument('hash'))) {
            $hash = $this->request->getArgument('hash');

            $querySettings = $this->cartRepository->createQuery()->getQuerySettings();
            $querySettings->setStoragePageIds([$this->cartPluginSettings['settings']['order']['pid']]);
            $this->cartRepository->setDefaultQuerySettings($querySettings);

            $this->cart = $this->cartRepository->findOneBy(['fHash' => $hash]);

            if ($this->cart) {
                $orderItem = $this->cart->getOrderItem();
                $payment = $orderItem->getPayment();

                $this->restoreCartSession();

                if ($payment->getStatus() !== 'canceled') {
                    $payment->setStatus('canceled');

                    $this->paymentRepository->update($payment);
                    $this->persistenceManager->persistAll();

                    $orderItem = $this->cart->getOrderItem();
                    $finishEvent = new CancelEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                    $this->eventDispatcher->dispatch($finishEvent);
                }

                $this->addFlashMessageToCartCart('tx_cartpayone.controller.order.payment.action.cancel.successfully_canceled');

                return $this->redirect('show', 'Cart\Cart', 'Cart');
            } else {
                $this->addFlashMessage(
                    LocalizationUtility::translate(
                        'tx_cartpayone.controller.order.payment.action.cancel.error_occured',
                        'CartPayone'
                    ),
                    '',
                    ContextualFeedbackSeverity::ERROR
                );
            }
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartpayone.controller.order.payment.action.cancel.access_denied',
                    'CartPayone'
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->htmlResponse();
    }

    protected function addFlashMessageToCartCart(string $translationKey): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            LocalizationUtility::translate(
                $translationKey,
                'CartPayone'
            ),
            '',
            ContextualFeedbackSeverity::ERROR,
            true
        );

        $flashMessageService = new FlashMessageService();
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier('extbase.flashmessages.tx_cart_cart');
        $messageQueue->enqueue($flashMessage);
    }

    protected function restoreCartSession(): void
    {
        $cart = $this->cart->getCart();
        $cart->resetOrderNumber();
        $cart->resetInvoiceNumber();
        $this->sessionHandler->writeCart($this->cartPluginSettings['settings']['cart']['pid'], $cart);
    }
}
