<?php

global $session;
if ($session["write"]) $menu["setup"]["l2"]['config'] = array("name"=>"Emonhub","href"=>"config", "order"=>5, "icon"=>"bullhorn");
