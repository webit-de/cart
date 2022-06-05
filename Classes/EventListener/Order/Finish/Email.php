<?php
declare(strict_types=1);
namespace Extcode\Cart\EventListener\Order\Finish;

/*
 * This file is part of the package extcode/cart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */
use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Event\Order\EventInterface;
use Extcode\Cart\Service\MailHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Email
{
    /**
     * Cart
     *
     * @var Cart
     */
    protected $cart;

    public function __invoke(EventInterface $event): void
    {
        $this->cart = $event->getCart();
        $orderItem = $event->getOrderItem();
        $settings = $event->getSettings();

        $paymentCountry = $orderItem->getPayment()->getServiceCountry();
        $paymentId = $orderItem->getPayment()->getServiceId();

        if ($paymentCountry) {
            $serviceSettings = $settings['payments'][$paymentCountry]['options'][$paymentId];
        } else {
            $serviceSettings = $settings['payments']['options'][$paymentId];
        }

        if (!isset($serviceSettings['preventBuyerEmail']) || 1 !== (int)$serviceSettings['preventSellerEmail']) {
            $this->sendBuyerMail($orderItem);
        }
        if (!isset($serviceSettings['preventSellerEmail']) || 1 !== (int)$serviceSettings['preventSellerEmail']) {
            $this->sendSellerMail($orderItem);
        }
    }

    /**
     * Send a Mail to Buyer
     *
     * @param Item $orderItem
     */
    protected function sendBuyerMail(
        Item $orderItem
    ) {
        $mailHandler = GeneralUtility::makeInstance(
            MailHandler::class
        );
        $mailHandler->setCart($this->cart);
        $mailHandler->sendBuyerMail($orderItem);
    }

    /**
     * Send a Mail to Seller
     *
     * @param Item $orderItem
     */
    protected function sendSellerMail(
        Item $orderItem
    ) {
        $mailHandler = GeneralUtility::makeInstance(
            MailHandler::class
        );
        $mailHandler->setCart($this->cart);
        $mailHandler->sendSellerMail($orderItem);
    }
}
