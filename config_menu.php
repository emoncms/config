<?php

    $menu['setup'][] = array(
        'text' => _("Emonhub"),
        'path' => 'config',
        'icon' => 'bullhorn',
        'active' => 'config',
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

// UNCOMMENT THE FOLLOWING TO PUT THE CONFIG MENU ITEMS IN THE EMONCMS SIDEBAR
// ---------------------------------------------------------------------------
    // $menu['sidebar']['emoncms'][] = array(
    //     'text' => _("Emonhub"),
    //     'path' => 'config',
    //     'active'=>'config',
    //     'icon' => 'show_chart',
    //     'order' => 2,
    //     'li_id' => 'config-link',
    //     'data'=> array('sidebar' => '#sidebar_config')
    // );

    // $menu['sidebar']['includes']['emoncms']['config'] = array(
    //     array('text'=>_('EmonCMS Connect'), 'path'=>'config/connect'),
    //     array('text'=>_('Calibration'), 'path'=>'config/calibration'),
    //     array('text'=>_('EmonHub.Conf Editor'), 'path'=>'config/editor'),
    //     array('text'=>_('View Log'), 'path'=>'config#log')
    // );