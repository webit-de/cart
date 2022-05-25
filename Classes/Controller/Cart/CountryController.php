<?php

namespace Extcode\Cart\Controller\Cart;

use Psr\Http\Message\ResponseInterface;
/*
 * This file is part of the package extcode/cart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */
class CountryController extends ActionController
{
    /**
     *
     */
    public function updateAction(): ResponseInterface
    {
        //ToDo check country is allowed by TypoScript

        $this->cartUtility->updateCountry($this->settings['cart'], $this->pluginSettings, $this->request);

        $this->cart = $this->sessionHandler->restore($this->settings['cart']['pid']);

        $taxClasses = $this->parserUtility->parseTaxClasses($this->pluginSettings, $this->cart->getBillingCountry());

        $this->cart->setTaxClasses($taxClasses);
        $this->cart->reCalc();

        $this->parseData();

        $paymentId = $this->cart->getPayment()->getId();
        if ($this->payments[$paymentId]) {
            $payment = $this->payments[$paymentId];
            $this->cart->setPayment($payment);
        } else {
            foreach ($this->payments as $payment) {
                if ($payment->isPreset()) {
                    $this->cart->setPayment($payment);
                }
            }
        }
        $shippingId = $this->cart->getShipping()->getId();
        if ($this->shippings[$shippingId]) {
            $shipping = $this->shippings[$shippingId];
            $this->cart->setShipping($shipping);
        } else {
            foreach ($this->shippings as $shipping) {
                if ($shipping->isPreset()) {
                    $this->cart->setShipping($shipping);
                }
            }
        }

        $this->sessionHandler->write($this->cart, $this->settings['cart']['pid']);

        $this->cartUtility->updateService($this->cart, $this->pluginSettings);

        $this->view->assign('cart', $this->cart);

        $assignArguments = [
            'shippings' => $this->shippings,
            'payments' => $this->payments,
            'specials' => $this->specials
        ];
        $this->view->assignMultiple($assignArguments);
        return $this->htmlResponse();
    }
}
