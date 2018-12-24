<?php

    $domain = "messages";

    $menu_left[] = array(
        'id'=>"config_menu",
        'name'=>"EmonHub", 
        'path'=>"config" , 
        'session'=>"write", 
        'order' => 8,
        'icon'=>'icon-bullhorn icon-white',
        'hideinactive'=>1
    );

    $menu_dropdown_config[] = array(
        'id'=>"config_menu_setup",
        'name'=>"EmonHub", 
        'path'=>"config" , 
        'session'=>"write", 
        'order' => 8,
        'icon'=>'icon-bullhorn'
    );
    
    

