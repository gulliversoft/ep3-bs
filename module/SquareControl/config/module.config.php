<?php

return array(
    'router' => array(
        'routes' => array(
            'squarecontrol' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/squarecontrol',
                ),
                'may_terminate' => false,
            ),
        ),
    ),

    'controllers' => array(
        'invokables' => array(
            'SquareControl\Controller\SquareControl' => 'SquareControl\Controller\SquareControlController',
        ),
    ),

    'service_manager' => array(
        'factories' => array(
            'SquareControl\Service\SquareControlService' => 'SquareControl\Service\SquareControlServiceFactory',
            'Zend\Session\Config\ConfigInterface' => 'Zend\Session\Service\SessionConfigFactory',
            'Zend\Session\SessionManager' => 'Zend\Session\Service\SessionManagerFactory',
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
