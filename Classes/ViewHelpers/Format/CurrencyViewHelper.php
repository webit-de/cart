<?php

namespace Extcode\Cart\ViewHelpers\Format;

/*
 * This file is part of the package extcode/cart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class CurrencyViewHelper extends AbstractViewHelper
{
    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * Output is escaped already. We must not escape children, to avoid double encoding.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        parent::initializeArguments();

        $this->registerArgument(
            'currencySign',
            'string',
            'The currency sign. (e.g. $ or €)',
            false
        );
        $this->registerArgument(
            'decimalSeparator',
            'string',
            'The decimal point separator.',
            false
        );
        $this->registerArgument(
            'thousandsSeparator',
            'string',
            'The thousands separator',
            false
        );
        $this->registerArgument(
            'prependCurrency',
            'bool',
            'Select if the curreny sign should be prepended',
            false
        );
        $this->registerArgument(
            'separateCurrency',
            'bool',
            'Separate the currency sign from the number by a single space.',
            false
        );
        $this->registerArgument(
            'decimals',
            'int',
            'Set decimals places.',
            false
        );
        $this->registerArgument(
            'currencyTranslation',
            'float',
            'Set decimals places.',
            false,
            1.00
        );
    }

    /**
     * @return string the formatted amount.
     */
    public function render()
    {
        $currencySign = $this->arguments['currencySign'];
        $decimalSeparator = $this->arguments['decimalSeparator'];
        $thousandsSeparator = $this->arguments['thousandsSeparator'];
        $prependCurrency = $this->arguments['prependCurrency'];
        $separateCurrency = $this->arguments['separateCurrency'];
        $decimals = $this->arguments['decimals'];
        $currencyTranslation = $this->arguments['currencyTranslation'];

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

        $floatToFormat = $this->renderChildren();
        if (empty($floatToFormat)) {
            $floatToFormat = 0.0;
        } else {
            $floatToFormat = floatval($floatToFormat);
        }

        if ($currencyTranslation && $currencyTranslation > 0.0) {
            $floatToFormat = $floatToFormat / $currencyTranslation;
        }

        $output = number_format($floatToFormat, $decimals, $decimalSeparator, $thousandsSeparator);
        if ($currencySign !== '') {
            $currencySeparator = $separateCurrency ? ' ' : '';
            if ($prependCurrency === true) {
                $output = $currencySign . $currencySeparator . $output;
            } else {
                $output = $output . $currencySeparator . $currencySign;
            }
        }
        return $output;
    }

    public function injectConfigurationManager(ConfigurationManager $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }
}
