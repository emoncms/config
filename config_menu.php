<?php

global $session;
if ($session["write"]) {
    $menu["setup"]["l2"]['config'] = array(
        "name"=>"Emonhub",
        "href"=>"config", 
        "order"=>-1, 
        "icon"=>"bullhorn",
        
        "l3"=>array(
            "connect"=>array(
                "name"=>_("Connect"),
                "href"=>"config/connect", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "nodes"=>array(
                "name"=>_("Nodes"),
                "href"=>"config/nodes", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "log"=>array(
                "name"=>_("Log"),
                "href"=>"config/log", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "editor"=>array(
                "name"=>_("Editor"),
                "href"=>"config/editor", 
                "order"=>1, 
                "icon"=>"input"
            )
        )  
    );
    
    
}
