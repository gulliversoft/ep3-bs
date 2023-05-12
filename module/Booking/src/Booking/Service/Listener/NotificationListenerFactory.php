<?php

namespace Booking\Service\Listener;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class NotificationListenerFactory implements FactoryInterface
{

    public function createService(ServiceLocatorInterface $sm)
    {
        return new NotificationListener(
            $sm->get('Base\Manager\OptionManager'),
            $sm->get('Base\Manager\ConfigManager'),
            $sm->get('Booking\Manager\ReservationManager'),
            $sm->get('Booking\Manager\Booking\BillManager'),
            $sm->get('Square\Manager\SquareManager'),
            $sm->get('User\Manager\UserManager'),
            $sm->get('User\Service\MailService'),
            $sm->get('Backend\Service\MailService'),
            $sm->get('ViewHelperManager')->get('DateFormat'),
            $sm->get('ViewHelperManager')->get('DateRange'),
            $sm->get('ViewHelperManager')->get('PriceFormat'),
            $sm->get('Translator'));
    }

}
