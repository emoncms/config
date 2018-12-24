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

function config_controller()
{
    global $route, $session, $redis;
    $result = false;
    
    $emonhub_config_file = "/home/pi/data/emonhub.conf";
    $emonhub_logfile = "/var/log/emonhub/emonhub.log";
    
    if (!$session['write']) return array('content'=>false);
     
    if ($route->action == '') {
        $route->format = "html";
        $result = view("Modules/config/view.php", array());
        return array('content'=>$result, 'fullwidth'=>false);
    }

    if ($route->action == 'editor') {
        $route->format = "html";
        $conf = $redis->get("get:emonhubconf");
        $result = view("Modules/config/editor.php", array("conf"=>$conf));
        return array('content'=>$result, 'fullwidth'=>false);
    }
    
    if ($route->action == "setemonhub" && isset($_POST['config'])) {
        $route->format = "json";
        $config = json_decode($_POST['config']);
        if ($config!=null) {
            $redis->set("set:emonhubconf",json_encode($config));
        }
    }
        
    if ($route->action == 'get') {
        $route->format = "text";
        $result = file_get_contents($emonhub_config_file);
    }
    
    if ($route->action == 'getemonhublog') {
        $route->format = "text";
        ob_start();
        passthru("tail -30 ".$emonhub_logfile);
        $result = trim(ob_get_clean());
    }
    
    
    if ($route->action == 'set' && isset($_POST['config'])) {
        $route->format = "text";
        $config = $_POST['config'];
        $fh = fopen($emonhub_config_file,"w");
        fwrite($fh,$config);
        fclose($fh);
        $result = "Config Saved";
    }
    
    if ($route->action == 'downloadlog')
    {
        header("Content-Type: application/octet-stream");
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($emonhub_logfile) . "\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        flush();
        if (file_exists($emonhub_logfile))
        {
          ob_start();
          readfile($emonhub_logfile);
          echo(trim(ob_get_clean()));
        }
        else
        {
          echo($emonhub_logfile . " does not exist!");
        }
        exit;
    }

  // emonHub restart requires added to /etc/sudoers:
  // www-data ALL=(ALL) NOPASSWD:/etc/init.d/emonhub restart
  if ($route->action == 'restart')
  {
    exec("sudo /etc/init.d/emonhub restart > /dev/null &");
  }

    return array('content'=>$result);
}
