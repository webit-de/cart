<?php
declare(strict_types=1);
namespace Extcode\Cart\Configuration\Backend\Widget;

return function (\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set('dashboard.widget.extcode.cart.turnover_per_day')
        ->class(\TYPO3\CMS\Dashboard\Widgets\BarChartWidget::class)
        ->arg('$dataProvider', new \Symfony\Component\DependencyInjection\Reference('extcode.cart.provider.turnover_per_day'))
        ->arg('$view', new \Symfony\Component\DependencyInjection\Reference('dashboard.views.widget'))
        ->tag('dashboard.widget', [
            'identifier' => 'TurnoverPerDay',
            'groupNames' => 'cart',
            'title' => 'LLL:EXT:cart/Resources/Private/Language/locallang_be.xlf:dashboard.widgets.turnover_per_day.title',
            'description' => 'LLL:EXT:cart/Resources/Private/Language/locallang_be.xlf:dashboard.widgets.turnover_per_day.description',
            'iconIdentifier' => 'content-widget-chart-bar',
            'additionalCssClasses' => 'dashboard-item--chart',
            'height' => 'medium',
            'width' => 'medium'
        ]);
};
