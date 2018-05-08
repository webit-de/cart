<?php

namespace Extcode\Cart\Utility;

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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;

/**
 * Product Utility
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class ProductUtility
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Plugin Settings
     *
     * @var array
     */
    protected $pluginSettings;

    /**
     * Tax Classes
     *
     * @var array
     */
    protected $taxClasses;

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(
        \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * Get Frontend User Group
     *
     * @return array
     */
    protected function getFrontendUserGroupIds()
    {
        $feGroupIds = [];
        $feUserId = (int)$GLOBALS['TSFE']->fe_user->user['uid'];
        if ($feUserId) {
            $frontendUserRepository = $this->objectManager->get(
                \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository::class
            );
            $feUser = $frontendUserRepository->findByUid($feUserId);
            $feGroups = $feUser->getUsergroup();
            if ($feGroups) {
                foreach ($feGroups as $feGroup) {
                    $feGroupIds[] = $feGroup->getUid();
                }
            }
        }
        return $feGroupIds;
    }

    /**
     * Get Cart/Product From Request
     *
     * @param array $pluginSettings TypoScript Plugin Settings
     * @param Request $request Request
     * @param \Extcode\Cart\Domain\Model\Cart\TaxClass[] $taxClasses Tax Class Array
     *
     * @return \Extcode\Cart\Domain\Model\Cart\Product[]
     */
    public function getProductsFromRequest(array $pluginSettings, Request $request, array $taxClasses)
    {
        if (!$this->pluginSettings) {
            $this->pluginSettings = $pluginSettings;
        }
        if (!$this->taxClasses) {
            $this->taxClasses = $taxClasses;
        }

        $multiple = 1;
        if ($this->pluginSettings['multiple']) {
            $argumentName = $this->pluginSettings['multiple'];
            if ($request->hasArgument($argumentName)) {
                $multiple = intval($request->getArgument($argumentName));
            }
        }

        $products = [];
        $preCartProductSets = [];

        if ($multiple == 1) {
            $preCartProductSets[1] = $this->retrieveCartProductValuesFromRequest($pluginSettings, $request);
        } else {
            // TODO: iterate over request
        }

        foreach ($preCartProductSets as $preCartProductSetKey => $cartProductValues) {
            if ($cartProductValues['contentId']) {
                $products[$preCartProductSetKey] = $this->getCartProductFromCE($cartProductValues);
            } elseif ($cartProductValues['productId']) {
                $products[$preCartProductSetKey] = $this->getCartProductFromDatabase($cartProductValues);
            }
        }

        return $products;
    }

    /**
     * Get CartProduct from Content Element
     *
     * @param array $cartProductValues
     *
     * @return \Extcode\Cart\Domain\Model\Cart\Product|null
     */
    protected function getCartProductFromCE(array $cartProductValues)
    {
        $cartProduct = null;

        $abstractPlugin = $this->objectManager->get(\TYPO3\CMS\Frontend\Plugin\AbstractPlugin::class);

        $row = $abstractPlugin->pi_getRecord('tt_content', $cartProductValues['contentId']);

        $flexformData = GeneralUtility::xml2array($row['pi_flexform']);

        $gpvarArr = ['productType', 'productId', 'sku', 'title', 'price', 'taxClassId', 'isNetPrice'];
        foreach ($gpvarArr as $gpvarVal) {
            $cartProductValues[$gpvarVal] = $abstractPlugin->pi_getFFvalue(
                $flexformData,
                'settings.' . $gpvarVal,
                'sDEF'
            );
        }

        $cartProduct = $this->objectManager->get(
            \Extcode\Cart\Domain\Model\Cart\Product::class,
            $cartProductValues['productType'],
            $cartProductValues['productId'],
            null,
            $cartProductValues['contentId'],
            $cartProductValues['sku'],
            $cartProductValues['title'],
            $cartProductValues['price'],
            $this->taxClasses[$cartProductValues['taxClassId']],
            $cartProductValues['quantity'],
            $cartProductValues['isNetPrice'],
            null
        );

        $attributes = explode("\n", $abstractPlugin->pi_getFFvalue($flexformData, 'settings.attributes', 'sDEF'));

        foreach ($attributes as $line) {
            list($key, $value) = explode('==', $line, 2);
            switch ($key) {
                case 'serviceAttribute1':
                    $cartProduct->setServiceAttribute1(floatval($value));
                    break;
                case 'serviceAttribute2':
                    $cartProduct->setServiceAttribute2(floatval($value));
                    break;
                case 'serviceAttribute3':
                    $cartProduct->setServiceAttribute3(floatval($value));
                    break;
                case 'minNumber':
                    $cartProduct->setMinNumberInCart(intval($value));
                    break;
                case 'maxNumber':
                    $cartProduct->setMaxNumberInCart(intval($value));
                    break;
                default:
            }
        }

        return $cartProduct;
    }

    /**
     * Get CartProduct from Database
     *
     * @param array $cartProductValues
     *
     * @return \Extcode\Cart\Domain\Model\Cart\Product|null
     */
    protected function getCartProductFromDatabase(array $cartProductValues)
    {
        $cartProduct = null;
        $repositoryClass = '';

        $productStorageId = $cartProductValues['productStorageId'];

        if (is_array($this->pluginSettings['productStorages']) &&
            is_array($this->pluginSettings['productStorages'][$productStorageId]) &&
            isset($this->pluginSettings['productStorages'][$productStorageId]['class'])
        ) {
            $repositoryClass = $this->pluginSettings['productStorages'][$productStorageId]['class'];
        }

        return $this->loadCartProductFromForeignDataStorage($cartProductValues, $productStorageId);
    }

    /**
     * Get CartProduct from Database
     *
     * @param array $cartProductValues
     * @param int $productStorageId
     *
     * @return \Extcode\Cart\Domain\Model\Cart\Product|null
     */
    protected function loadCartProductFromForeignDataStorage(array $cartProductValues, $productStorageId)
    {
        $cartProduct = null;

        $data = [
            'cartProductValues' => $cartProductValues,
            'productStorageId' => $productStorageId,
            'cartProduct' => $cartProduct,
            'taxClasses' => $this->taxClasses,
        ];

        $signalSlotDispatcher = $this->objectManager->get(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );
        $slotReturn = $signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__,
            [$data]
        );

        if ($slotReturn[0]['cartProduct'] instanceof \Extcode\Cart\Domain\Model\Cart\Product) {
            $cartProduct = $slotReturn[0]['cartProduct'];
        }

        return $cartProduct;
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     * @param array $products
     *
     * @return array
     */
    public function checkProductsBeforeAddToCart(\Extcode\Cart\Domain\Model\Cart\Cart $cart, $products)
    {
        list($errors, $products) = $this->checkStockOfProducts($cart, $products);

        $data = [
            'cart' => $cart,
            'products' => $products,
            'errors' => $errors,
        ];

        $signalSlotDispatcher = $this->objectManager->get(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );
        $slotReturn = $signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__,
            [$data]
        );

        $products = $slotReturn[0]['products'];
        $errors = $slotReturn[0]['errors'];

        return [$products, $errors];
    }

    /**
     * @param array $pluginSettings
     * @param Request $request Request
     *
     * @return array
     */
    public function retrieveCartProductValuesFromRequest(array $pluginSettings, Request $request)
    {
        if (!$this->pluginSettings) {
            $this->pluginSettings = $pluginSettings;
        }

        $cartProductValues = [];

        if ($request->hasArgument('productId')) {
            $cartProductValues['productId'] = intval($request->getArgument('productId'));
        }
        if ($request->hasArgument('productStorageId')) {
            $cartProductValues['productStorageId'] = intval($request->getArgument('productStorageId'));
        } else {
            $cartProductValues['productStorageId'] = 1;
        }

        if ($request->hasArgument('contentId')) {
            $cartProductValues['contentId'] = intval($request->getArgument('contentId'));
        }
        if ($request->hasArgument('quantity')) {
            $quantity = intval($request->getArgument('quantity'));
            $cartProductValues['quantity'] = $quantity ? $quantity : 1;
        }

        if ($request->hasArgument('feVariants')) {
            $requestFeVariants = $request->getArgument('feVariants');
            if (is_array($requestFeVariants)) {
                foreach ($requestFeVariants as $requestFeVariantKey => $requestFeVariantValue) {
                    $cartProductValues['feVariants'][$requestFeVariantKey] = $requestFeVariantValue;
                }
            }
        }

        if ($request->hasArgument('beVariants')) {
            $requestVariants = $request->getArgument('beVariants');
            if (is_array($requestVariants)) {
                foreach ($requestVariants as $requestVariantKey => $requestVariantValue) {
                    $cartProductValues['beVariants'][$requestVariantKey] = intval($requestVariantValue);
                }
            }
        }

        $data = [
            'pluginSettings' => $pluginSettings,
            'request' => $request,
            'cartProductValues' => $cartProductValues,
        ];

        $signalSlotDispatcher = $this->objectManager->get(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );

        $slotReturn = $signalSlotDispatcher->dispatch(
            __CLASS__,
            'changeCartProductValues',
            [$data]
        );

        $cartProductValues = $slotReturn[0]['cartProductValues'];

        return $cartProductValues;
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     * @param $productId
     * @param $backendVariantId
     *
     * @return int
     */
    protected function getBackendVariantQuantityFromCart(\Extcode\Cart\Domain\Model\Cart\Cart $cart, $productId, $backendVariantId)
    {
        if ($cart->getProduct($productId)) {
            $cartProduct = $cart->getProduct($productId);
            if ($cartProduct->getBeVariantById($backendVariantId)) {
                $cartBackendVariant = $cartProduct->getBeVariantById($backendVariantId);

                return $cartBackendVariant->getQuantity();
            }
        }
        return 0;
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     * @param $products
     *
     * @return array
     */
    protected function checkStockOfProducts(\Extcode\Cart\Domain\Model\Cart\Cart $cart, $products)
    {
        $errors = [];

        foreach ($products as $productKey => $product) {
            if ($product->isHandleStock()) {
                if ($product->isHandleStockInVariants()) {
                    list($products, $errors) = $this->checkStockOfBackendVariants($cart, $errors, $products, $product, $productKey);
                } else {
                    list($products, $errors) = $this->checkStockOfProduct($cart, $errors, $products, $product, $productKey);
                }
            }
        }

        return [$errors, $products];
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     * @param array $errors
     * @param $products
     * @param $product
     * @param $productKey
     *
     * @return mixed
     */
    protected function checkStockOfProduct(\Extcode\Cart\Domain\Model\Cart\Cart $cart, $errors, $products, $product, $productKey)
    {
        $qty = $product->getQuantity();
        if ($cart->getProduct($product->getId())) {
            $qty += $cart->getProduct($product->getId())->getQuantity();
        }

        if ($qty > $product->getStock()) {
            unset($products[$productKey]);

            $message = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate(
                'tx_cart.error.stock_handling.add',
                'cart'
            );
            $error = [
                'message' => $message,
                'severity' => \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
            ];

            array_push($errors, $error);
        }
        return [$products, $errors];
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     * @param array $errors
     * @param $products
     * @param $product
     * @param $productKey
     *
     * @return array
     */
    protected function checkStockOfBackendVariants(\Extcode\Cart\Domain\Model\Cart\Cart $cart, $errors, $products, $product, $productKey)
    {
        if ($product->getBeVariants()) {
            foreach ($product->getBeVariants() as $backendVariant) {
                $qty = $backendVariant->getQuantity();
                $qty += $this->getBackendVariantQuantityFromCart(
                    $cart,
                    $product->getId(),
                    $backendVariant->getId()
                );

                if ($qty > $backendVariant->getStock()) {
                    $product->removeBeVariants([$backendVariant->getId() => 1]);
                    if ($product->getBeVariants()) {
                        $products[$productKey] = $product;
                    } else {
                        unset($products[$productKey]);
                    }

                    $message = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate(
                        'tx_cart.error.stock_handling.add',
                        'cart'
                    );
                    $error = [
                        'message' => $message,
                        'severity' => \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
                    ];

                    array_push($errors, $error);
                }
            }
        }
        return [$products, $errors];
    }
}
