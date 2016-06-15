<?php

namespace Extcode\Cart\ViewHelpers\Format;

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

/**
 * Currency ViewHelper
 *
 * Currency ViewHelper was adapted from Fluid Currency ViewHelper but allows to define default behaviour through
 * TypoScript settings.
 *
 * @package cart
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class CurrencyViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Format\CurrencyViewHelper
{
    /**
     * @param string $currencySign (optional) The currency sign, eg $ or €.
     * @param string $decimalSeparator (optional) The separator for the decimal point.
     * @param string $thousandsSeparator (optional) The thousands separator.
     * @param boolean $prependCurrency (optional) Select if the curreny sign should be prepended
     * @param boolean $separateCurrency (optional) Separate the currency sign from the number by a single space, defaults to true due to backwards compatibility
     * @param integer $decimals (optional) Set decimals places.
     *
     * @return string the formatted amount.
     */
    public function render(
        $currencySign = null,
        $decimalSeparator = null,
        $thousandsSeparator = null,
        $prependCurrency = null,
        $separateCurrency = null,
        $decimals = null
    ) {
        $settings = $this->templateVariableContainer->get('settings');

        if ($settings && $settings['format'] && $settings['format']['currency']) {
            $currencyFormat = $settings['format']['currency'];

            if (!$currencySign) {
                if ($currencyFormat['currencySign']) {
                    $currencySign = $currencyFormat['currencySign'];
                }
            }
            if (!$decimalSeparator) {
                if ($currencyFormat['decimalSeparator']) {
                    $decimalSeparator = $currencyFormat['decimalSeparator'];
                }
            }
            if (!$thousandsSeparator) {
                if ($currencyFormat['thousandsSeparator']) {
                    $thousandsSeparator = $currencyFormat['thousandsSeparator'];
                }
            }
            if (!$prependCurrency) {
                if ($currencyFormat['prependCurrency']) {
                    $prependCurrency = filter_var($currencyFormat['prependCurrency'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            if (!$separateCurrency) {
                if ($currencyFormat['separateCurrency']) {
                    $separateCurrency = filter_var($currencyFormat['separateCurrency'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            if (!$decimals) {
                if ($currencyFormat['decimals']) {
                    $decimals = intval($currencyFormat['decimals']);
                }
            }
        }

        return parent::render(
            $currencySign,
            $decimalSeparator,
            $thousandsSeparator,
            $prependCurrency,
            $separateCurrency,
            $decimals
        );
    }
}