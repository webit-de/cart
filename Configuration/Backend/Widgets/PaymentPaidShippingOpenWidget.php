<?php
declare(strict_types=1);
namespace Extcode\Cart\Configuration\Backend\Widget;

return function (\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set('dashboard.widget.extcode.cart.payment_paid_shipping_open')
        ->class(\Extcode\Cart\Widgets\PaymentPaidShippingOpen::class)
        ->arg('$dataProvider', new \Symfony\Component\DependencyInjection\Reference('extcode.cart.provider.payment_paid_shipping_open'))
        ->arg('$view', new \Symfony\Component\DependencyInjection\Reference('dashboard.views.widget'))
        ->tag('dashboard.widget', [
            'identifier' => 'PaymentPaidShippingOpen',
            'groupNames' => 'cart',
            'title' => 'LLL:EXT:cart/Resources/Private/Language/locallang_be.xlf:dashboard.widgets.payment_paid_shipping_open.title',
            'description' => 'LLL:EXT:cart/Resources/Private/Language/locallang_be.xlf:dashboard.widgets.payment_paid_shipping_open.description',
            'iconIdentifier' => 'content-widget-list',
            'height' => 'large',
            'width' => 'medium'
        ]);
};
