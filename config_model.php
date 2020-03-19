<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 
 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project:
 http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
class Config
{
    public $config_file = "/etc/emonhub/emonhub.conf";
    public $logfile = "/var/log/emonhub/emonhub.log";
    public $restart_log_name = "emonhub-restart.log";
    
    public $log_levels = array(
        1=>"DEBUG",
        2=>"INFO",
        3=>"WARNING",
        4=>"ERROR",
        5=>"CRITICAL"
    );

    public function __construct()
    {
         if (!file_exists($this->config_file)) {
             if (file_exists("/home/pi/data/emonhub.conf")) {
                 $this->config_file = "/home/pi/data/emonhub.conf";
             }
         }
    }
    
    public function get_log_level(){
        if (is_file($this->config_file)) {
            $file = file_get_contents($this->config_file);
            preg_match('/^\s*loglevel = (.*)$/m', $file, $matches);
        }
        if(!empty($matches[1])) {
            return $matches[1];
        }else{
            return null;
        }
    }
    
    /*
    public function sub_nav_tabs() {
        include_once "Lib/misc/nav_functions.php";
        $menu = load_menu();
        $tabs = '';
        if(!empty($menu['_config_tabs'])) {
            array_walk($menu['_config_tabs'], function(&$item, $key) {
                $item['markup'] = makeListLink($item);
            });
            $tab_items = array_column($menu['_config_tabs'], 'markup');
            $tabs = view('Modules/config/Views/tabs.php', array('items'=>$tab_items));
            // return <html> with tabs
        }
        return $tabs;
    }
    */
}
