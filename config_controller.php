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
    require "Modules/config/config_model.php";
    $config = new Config();
    
    // $tabs = $config->sub_nav_tabs();
    // @todo: not sure if tabs should be used? Routes not complete for each tab in config_menu.php
    $tabs = ''; // override with blank string
    
    $log_levels = $config->log_levels;
    $emonhub_config_file = $config->config_file;
    $emonhub_logfile = $config->logfile;
    $restart_log = "/var/log/emoncms/".$config->restart_log_name;

    if (!$session['write']) return false;
    
    if ($route->action == 'log') {
        $route->format = "html";
        // $route->submenu = view("Modules/config/sidebar.php");
        return view("Modules/config/view.php", array('log_levels'=>$log_levels,'tabs'=>$tabs, 'level'=> $config->get_log_level()));
    }
    
    if ($route->action == 'nodes') {
        $route->format = "html";
        $conf = file_get_contents("http://localhost:8000/config");
        return view("Modules/config/nodes/nodes.php", array("conf"=>$conf));
    }
    
    if ($route->action == 'getnodes') {   
        $route->format = "json";  
        return json_decode(file_get_contents("http://localhost:8000/nodes"));
    }
    
    if ($route->action == 'available') {   
        $route->format = "json";  
        return json_decode(file_get_contents("http://localhost:8000/available"));
    }
    
    if ($route->action == 'save') {
        $route->format = "text";  
        $conf = json_decode(post("conf"));
        http_request("POST","http://localhost:8000/config", json_encode($conf));
        return "saved";
    }
    // ---------------------------------------------------------

    if ($route->action == 'editor') {
        $route->format = "html";
        $conf = file_get_contents("http://localhost:8000/config");
        return view("Modules/config/editor.php", array("conf"=>$conf,'tabs'=>$tabs));
    }
    if ($route->action == 'calibration') {
        $route->format = "html";
        $conf = file_get_contents("http://localhost:8000/config");
        return view("Modules/config/calibration.php", array("conf"=>$conf,'tabs'=>$tabs));
    }
    if ($route->action == 'connect') {
        $route->format = "html";
        $conf = file_get_contents("http://localhost:8000/config");
        return view("Modules/config/connect.php", array("conf"=>$conf,'tabs'=>$tabs));
    }
    
    if ($route->action == 'remoteauth') {
        $route->format = "text";  
        if (!isset($_POST['host'])) return "host missing";
        if (!isset($_POST['username'])) return "username missing";    
        if (!isset($_POST['password'])) return "password missing";
        
        $host = $_POST['host'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $route->format = "json"; 
        $result = json_decode(http_request("POST","https://emoncms.org/user/auth.json",array("username"=>$username,"password"=>$password)));
        
        $conf = json_decode(file_get_contents("http://localhost:8000/config"));
        $conf->interfacers->emoncmsorg->runtimesettings->apikey = $result->apikey_write;
        http_request("POST","http://localhost:8000/config", json_encode($conf));
        
        return $result;
    }
    
    else if ($route->action == 'get') {
        $route->format = "text";
        return file_get_contents($emonhub_config_file);
    }
    
    else if ($route->action == 'getemonhublog') {
        $route->format = "text";
        ob_start();
        if (file_exists($emonhub_logfile)) {
            passthru("tail -30 $emonhub_logfile");
        } else {
            passthru("journalctl -u emonhub -n 30 --no-pager");
        }   
        $result = trim(ob_get_clean());
    }
    
    
    else if ($route->action == 'set' && isset($_POST['config'])) {
        $route->format = "text";
        $config = $_POST['config'];
        $fh = fopen($emonhub_config_file,"w");
        fwrite($fh,$config);
        fclose($fh);
        return "Config Saved";
    }
    
    else if ($route->action == 'loglevel') {
        $route->format = "json";
        $success = true;
        $message = '';

        // load the settings.php as text file
        $file = file_get_contents($emonhub_config_file);
        $matches = array();

        if($route->method === 'POST') {
            if(!empty(post('level'))) {
                $level = post('level');
                if (is_file($emonhub_config_file) && is_writable($emonhub_config_file)) {
                    $log_level = intval(post('level'));
                    if(array_key_exists($log_level, $log_levels)) {
                        // replace the value of the `loglevel` variable
                        preg_match('/^\s*loglevel = (.*)$/m', $file, $matches);
                        if(!empty($matches)) {
                            $file = str_replace($matches[1], $log_levels[$log_level], $file);
                            $bytes = file_put_contents($emonhub_config_file, $file);
                            $success = $bytes && $bytes > 0;
                            // $log->error("EmonHub log level changed: $log_level");
                            $message = _('Changes Saved');
                        } else {
                            $message = sprintf(_('"loglevel" not found in: %s'), $emonhub_config_file);
                        }
                    } else {
                        $message = sprintf(_('New log level out of range. must be one of %s'), implode(', ', array_keys($log_levels)));
                    }
                } else {
                    $log_level = null;
                    $success = false;
                    $message = sprintf(_('Not able to write to: %s'), $emonhub_config_file);
                }
            } else {
                $message = _('No new log level supplied');
            }
        } else {
            // was not a POST return current level
            $file_log_level = $config->get_log_level();
            if(!empty($file_log_level)) {
                $log_level = array_search($file_log_level, $log_levels);
                if($log_level === false) {
                    $message = sprintf(_('"loglevel" in %s not in list'), $emonhub_config_file);
                } else {
                    $success = true;
                }
            } else {
                $success = false;
                $log_level = null;
                $message = sprintf(_('"loglevel" not found in: %s'), $emonhub_config_file);
            }
            $log_level = intval($log_level);
        }
        $result = array (
            'success' => $success,
            'log-level' => !empty($log_levels[$log_level]) ? $log_levels[$log_level]: 'NULL',
            'log-level-id' =>  $log_level,
            'message' => $message
        );
        return $result;
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
          passthru("journalctl -u emonhub --no-pager");        }
        exit;
    }

    // emonHub restart requires added to /etc/sudoers:
    // www-data ALL=(ALL) NOPASSWD:service emonhub restart
    else if ($route->action == 'restart')
    {
        $route->format = "text";
        list($scriptPath) = get_included_files();
        $basedir = str_replace("/index.php","",$scriptPath);
        $restart_script = "$basedir/Modules/config/restart.sh";
        if ($redis->rpush("service-runner","$restart_script>$restart_log")){
            $result= "service-runner trigger sent for $restart_script>$restart_log";
        } else {
            $result= "could not send trigger";
        }
        return $result;
        
    }

    return array('content'=>$result);
}
