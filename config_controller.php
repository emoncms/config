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
    global $route, $session, $redis, $path, $log, $settings;
    
    $emonhub_config_file = "/etc/emonhub/emonhub.conf";
    $emonhub_logfile = "/var/log/emonhub/emonhub.log";
    $emonhub_restart_log = "emonhub-restart.log";

    if (!$session['write']) return false;
    
    $log_levels = array("DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL");

    if ($route->action == '') {
        $route->format = "html";
        $config = @file_get_contents($emonhub_config_file);
        $level = "";
        if ($config !== false && preg_match('/^\[hub\]\s*$(.*?)(?=^\[|\z)/ms', $config, $hub)) {
            if (preg_match('/^\s*loglevel\s*=\s*(\w+)/m', $hub[1], $m)) $level = strtoupper($m[1]);
        }
        return view("Modules/config/view.php", array("log_levels" => $log_levels, "level" => $level));
    }

    else if ($route->action == 'setloglevel') {
        $route->format = "json";
        if (!$session['admin']) return array('success' => false, 'message' => "Admin access required");

        $level = isset($_POST['level']) ? strtoupper($_POST['level']) : "";
        if (!in_array($level, $log_levels, true)) return array('success' => false, 'message' => "Invalid log level");

        $config = @file_get_contents($emonhub_config_file);
        if ($config === false) return array('success' => false, 'message' => "Could not read $emonhub_config_file");

        // Replace or insert loglevel in [hub] section
        if (!preg_match('/^\[hub\]\s*$(.*?)(?=^\[|\z)/ms', $config, $hub, PREG_OFFSET_CAPTURE)) {
            return array('success' => false, 'message' => "[hub] section not found in $emonhub_config_file");
        }
        $body = $hub[1][0];
        $offset = $hub[1][1];
        $count = 0;
        $body = preg_replace('/^(\s*loglevel\s*=\s*)\w+/m', '${1}' . $level, $body, 1, $count);
        if ($count == 0) $body = "\n    loglevel = $level" . $body;
        $config = substr($config, 0, $offset) . $body . substr($config, $offset + strlen($hub[1][0]));

        if (file_put_contents($emonhub_config_file, $config) === false) {
            return array('success' => false, 'message' => "Could not write $emonhub_config_file");
        }

        require_once "Modules/admin/info/ServiceModel.php";
        $services = new ServiceModel($redis, $log, $settings);
        $result = $services->setService("emonhub.service", "restart");
        if (!$result['success']) return array('success' => false, 'message' => "Log level saved, restart failed: " . $result['message']);
        return array('success' => true, 'message' => "Log level set to $level, restarting emonhub", 'level' => $level);
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
