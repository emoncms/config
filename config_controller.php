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
    global $route, $session;
    $result = false;
    
    $emonhub_config_file = "/home/pi/data/emonhub.conf";
    $emonhub_logfile = "/var/log/emonhub/emonhub.log";
    
    if (!$session['write']) return false;
     
    if ($route->action == 'view') {
        $route->format = "html";
        $result = view("Modules/config/edit.php", array());
        return array('content'=>$result, 'fullwidth'=>false);
    }
    
    else if ($route->action == 'get') {
        $route->format = "text";
        return file_get_contents($emonhub_config_file);
    }
    
    else if ($route->action == 'getemonhublog') {
        $route->format = "text";
        ob_start();
        passthru("journalctl -u emonhub -n 30 --no-pager");
        return trim(ob_get_clean());
    }
    
    
    else if ($route->action == 'set' && isset($_POST['config'])) {
        $route->format = "text";
        $config = $_POST['config'];
        $fh = fopen($emonhub_config_file,"w");
        fwrite($fh,$config);
        fclose($fh);
        return "Config Saved";
    }
    
    else if ($route->action == 'downloadlog')
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
    // www-data ALL=(ALL) NOPASSWD:service emonhub restart
    else if ($route->action == 'restart')
    {
        exec("sudo service emonhub restart > /dev/null &");
        return true;
    }

    return array('content'=>$result);
}
