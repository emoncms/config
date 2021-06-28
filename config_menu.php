<?php

global $session;
if ($session["write"]) {
    $menu["setup"]["l2"]['config'] = array(
        "name"=>"Emonhub",
        "href"=>"config",
        "default"=>"config/OEM",
        "order"=>-1, 
        "icon"=>"bullhorn",
        
        "l3"=>array(
            "oem"=>array(
                "name"=>_("1. OEM Hardware"),
                "href"=>"config/OEM", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "nodes"=>array(
                "name"=>_("2. Nodes"),
                "href"=>"config/nodes", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "http"=>array(
                "name"=>_("3. HTTP"),
                "href"=>"config/http", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "mqtt"=>array(
                "name"=>_("4. MQTT"),
                "href"=>"config/mqtt", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "editor"=>array(
                "name"=>_("Editor"),
                "href"=>"config/editor", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "log"=>array(
                "name"=>_("Log"),
                "href"=>"config/log", 
                "order"=>1, 
                "icon"=>"input"
            )
        )  
    );
    
    
}
