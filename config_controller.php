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
    global $route, $session, $redis, $path;
    
    $emonhub_config_file = "/etc/emonhub/emonhub.conf";
    $emonhub_logfile = "/var/log/emonhub/emonhub.log";
    $emonhub_restart_log = "emonhub-restart.log";

    if (!$session['write']) return false;
    
    if ($route->action == '') {
        $route->format = "html";
        return view("Modules/config/view.php", array());
    }

    // ---------------------------------------------------------
        
    else if ($route->action == 'get') {
        $route->format = "text";
        return file_get_contents($emonhub_config_file);
    }
    
    else if ($route->action == 'getemonhublog') {
        $route->format = "text";
        
        $start_pos = 0;
        if(isset($_GET['pos'])) {
            $start_pos = (int) $_GET['pos'];
            if ($start_pos<0) $start_pos = 0;
        }
        
        if (file_exists($emonhub_logfile)) {
            $size = filesize($emonhub_logfile);
            if ($fh = fopen($emonhub_logfile,'r')) {
                
                if ($start_pos==0) {
                    $start_pos = $size-100000;
                    if ($start_pos<0) $start_pos = 0;
                    // Find first new line
                    fseek($fh,$start_pos);
                    $result = fread($fh,1000);
                    $pos = strpos($result,"\n");
                    $start_pos+=$pos+1;
                }
                
                $bytes_to_read = $size-$start_pos;
                
                $result = "";
                if ($bytes_to_read>0) {
                    fseek($fh,$start_pos);
                    $result = fread($fh,$bytes_to_read);
                }
                
                fclose($fh);
                return $size."\n".$result;
            }
        }
        return "0\nemonhub.log does not exist";
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
        if (file_exists($emonhub_logfile)) {
          ob_start();
          readfile($emonhub_logfile);
          echo(trim(ob_get_clean()));
        } else {
          echo($emonhub_logfile . " does not exist!");
          passthru("journalctl -u emonhub --no-pager");        
        }
        exit;
    }

    return array('content'=>$result);
}
