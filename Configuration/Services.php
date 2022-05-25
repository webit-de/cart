<?php
declare(strict_types=1);
namespace Extcode\Cart\Configuration;

return function (\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator, \Symfony\Component\DependencyInjection\ContainerBuilder $containerBuilder) {
    if ($containerBuilder->hasDefinition('dashboard.views.widget')) {
        $containerConfigurator->import('Backend/Provider/PaymentPaidShippingOpenProvider.php');
        $containerConfigurator->import('Backend/Widgets/PaymentPaidShippingOpenWidget.php');

        $containerConfigurator->import('Backend/Provider/OrdersPerDayProvider.php');
        $containerConfigurator->import('Backend/Widgets/OrdersPerDayWidget.php');

        $containerConfigurator->import('Backend/Provider/TurnoverPerDayProvider.php');
        $containerConfigurator->import('Backend/Widgets/TurnoverPerDayWidget.php');
    }
};
