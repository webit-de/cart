<?php

namespace Extcode\Cart\Controller\Order;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Order Controller
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class PaymentController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Cart Repository
     *
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * Order Item Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\ItemRepository
     */
    protected $itemRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart = null;

    /**
     * Plugin Settings
     *
     * @var array
     */
    protected $pluginSettings;

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
     */
    public function injectCartRepository(
        \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\ItemRepository $itemRepository
     */
    public function injectItemRepository(
        \Extcode\Cart\Domain\Repository\Order\ItemRepository $itemRepository
    ) {
        $this->itemRepository = $itemRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\PaymentRepository $paymentRepository
     */
    public function injectPaymentRepository(
        \Extcode\Cart\Domain\Repository\Order\PaymentRepository $paymentRepository
    ) {
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Initialize Action
     */
    protected function initializeAction()
    {
        $this->pluginSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
            );
    }

    /**
     *
     */
    public function updateAction()
    {
        if ($this->request->hasArgument('hash') && !empty($this->request->getArgument('hash'))) {
            $hash = $this->request->getArgument('hash');

            $querySettings = $this->objectManager->get(
                \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
            );
            $querySettings->setStoragePageIds([$this->settings['order']['pid']]);
            $this->cartRepository->setDefaultQuerySettings($querySettings);

            $this->cart = $this->cartRepository->findOneBySHash($hash);

            if ($this->cart) {
                $orderItem = $this->cart->getOrderItem();
                $payment = $orderItem->getPayment();

                $payment->setStatus('paid');

                $this->paymentRepository->update($payment);
                $this->persistenceManager->persistAll();

                $this->addFlashMessage(
                    LocalizationUtility::translate(
                        'tx_cart.controller.order.action.payment_success.successfully_paid',
                        $this->extensionName
                    ),
                    '',
                    \TYPO3\CMS\Core\Messaging\AbstractMessage::OK
                );

                //@todo refactoring to finisher concept
                //$orderUtility = $this->objectManager->get(\Extcode\Cart\Utility\OrderUtility::class);
                //$orderUtility->autoGenerateDocuments($orderItem, $this->pluginSettings);

                $this->sendMails($orderItem, 'success', __CLASS__, __FUNCTION__);

                $this->view->assign('orderItem', $orderItem);
            } else {
                $this->addFlashMessage(
                    LocalizationUtility::translate(
                        'tx_cart.controller.order.action.payment_success.error_occured',
                        $this->extensionName
                    ),
                    '',
                    \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
                );
            }
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cart.controller.order.action.payment_success.access_denied',
                    $this->extensionName
                ),
                '',
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
            );
        }
    }

    /**
     * Send Mails
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     * @paran string $type
     * @param string $class
     * @param string $function
     */
    protected function sendMails(\Extcode\Cart\Domain\Model\Order\Item $orderItem, $type, $class, $function)
    {
        $billingAddress = $orderItem->getBillingAddress();
        if ($billingAddress instanceof \TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy) {
            $billingAddress = $billingAddress->_loadRealInstance();
        }

        $shippingAddress = null;
        if ($orderItem->getShippingAddress()) {
            $shippingAddress = $orderItem->getShippingAddress();
            if ($shippingAddress instanceof \TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy) {
                $shippingAddress = $shippingAddress->_loadRealInstance();
            }
        }

        $data = [
            'orderItem' => $orderItem,
            'cart' => $this->cart,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
        ];

        $signalSlotDispatcher = $this->objectManager->get(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );
        $signalSlotDispatcher->dispatch(
            $class,
            $function . 'AfterUpdatePaymentAndBefore' . ucfirst($type) . 'Mail',
            $data
        );

        $paymentCountry = $orderItem->getPayment()->getServiceCountry();
        $paymentId = $orderItem->getPayment()->getServiceId();

        if ($paymentCountry) {
            $serviceSettings = $this->pluginSettings['payments'][$paymentCountry]['options'][$paymentId];
        } else {
            $serviceSettings = $this->pluginSettings['payments']['options'][$paymentId];
        }

        $paymentStatus = $orderItem->getPayment()->getStatus();

        if (intval($serviceSettings['sendBuyerEmail'][$paymentStatus]) == 1) {
            $this->sendBuyerMail($orderItem);
        }
        if (intval($serviceSettings['sendSellerEmail'][$paymentStatus]) == 1) {
            $this->sendSellerMail($orderItem);
        }

        $signalSlotDispatcher = $this->objectManager->get(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );
        $signalSlotDispatcher->dispatch(
            $class,
            $function . 'AfterUpdatePaymentAndAfter' . ucfirst($type) . 'Mail',
            $data
        );
    }

    /**
     * Send a Mail to Buyer
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     */
    protected function sendBuyerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem
    ) {
        /* @var \Extcode\Cart\Service\MailHandler $mailHandler*/
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );
        $mailHandler->setCart($this->cart->getCart());
        $mailHandler->sendBuyerMail($orderItem);
    }

    /**
     * Send a Mail to Seller
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     */
    protected function sendSellerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem
    ) {
        /* @var \Extcode\Cart\Service\MailHandler $mailHandler*/
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );
        $mailHandler->setCart($this->cart->getCart());
        $mailHandler->sendSellerMail($orderItem);
    }
}
