<?php

    $menu['setup'][] = array(
        'text' => _("Emonhub"),
        'path' => 'config',
        'icon' => 'bullhorn',
        'data' => array(
            'sidebar' => '#sidebar_config'
        )
    );

    // used in config_controller.php to build the tabs
    $menu['_config_tabs'] = array(
        array('text'=>_('EmonCMS Connect'), 'path'=>'config/connect'),
        array('text'=>_('Calibration'), 'path'=>'config/calibration'),
        array('text'=>_('EmonHub.Conf Editor'), 'path'=>'config/editor'),
        array('text'=>_('View Log'), 'path'=>'config#log')
    );