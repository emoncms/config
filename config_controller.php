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
        return view("Modules/config/view.php", array("log_levels" => $log_levels, "level" => $level, "admin" => !empty($session['admin'])));
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
        
    // Config holds passwords and API keys, so reading and saving need admin
    else if ($route->action == 'get') {
        if (empty($session['admin'])) return false;
        $route->format = "text";
        $config = file_get_contents($emonhub_config_file);
        // Fingerprint for the conflict check in set
        header("X-Config-Hash: " . md5($config));
        return $config;
    }
    
    // Interfacer types, settings and example configs from the emonhub install
    else if ($route->action == 'interfacers') {
        $route->format = "json";
        $emonhub_dir = $settings['openenergymonitor_dir'] . "/emonhub";

        $types = array();
        $init = @file_get_contents("$emonhub_dir/src/interfacers/__init__.py");
        if ($init !== false && preg_match_all('/^\s*"(\w+)"/m', $init, $m)) {
            $types = $m[1];
        }

        $examples = array();
        $files = glob("$emonhub_dir/conf/interfacer_examples/*/*.conf");
        if ($files) {
            foreach ($files as $file) {
                $examples[] = array(
                    'name' => basename(dirname($file)) . "/" . basename($file, ".emonhub.conf"),
                    'content' => file_get_contents($file)
                );
            }
        }

        return array('types' => $types, 'settings' => emonhub_interfacer_settings("$emonhub_dir/src"), 'examples' => $examples);
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
        $route->format = "json";
        if (empty($session['admin'])) return array('success' => false, 'message' => "Admin access required");
        $config = $_POST['config'];

        // Refuse if the file changed since it was loaded, e.g. autoconf added a node
        $current = @file_get_contents($emonhub_config_file);
        if ($current === false) return array('success' => false, 'message' => "Could not read $emonhub_config_file");
        if (isset($_POST['hash']) && $_POST['hash'] !== md5($current)) {
            return array(
                'success' => false,
                'conflict' => true,
                'message' => "emonhub.conf has changed since it was loaded",
                'current' => $current,
                'hash' => md5($current)
            );
        }

        $check = emonhub_validate_config($config);
        if ($check !== true) {
            return array('success' => false, 'message' => "Config not saved, it has errors", 'errors' => $check);
        }

        if (file_put_contents($emonhub_config_file, $config) === false) {
            return array('success' => false, 'message' => "Could not write $emonhub_config_file");
        }
        return array('success' => true, 'message' => "Config saved", 'hash' => md5($config));
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

// Check config text parses as emonhub reads it.
// Returns true, or a list of errors. Saving goes ahead if the check cannot run.
function emonhub_validate_config($config)
{
    global $log;

    $script = __DIR__ . "/validate_config.py";
    if (!function_exists('proc_open')) return true;
    $proc = @proc_open("python3 " . escapeshellarg($script), array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w")), $pipes);
    if (!is_resource($proc)) {
        if ($log) $log->warn("emonhub config check could not run");
        return true;
    }
    fwrite($pipes[0], $config);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    $result = json_decode($output, true);
    if (!is_array($result) || !isset($result['valid'])) {
        if ($log) $log->warn("emonhub config check failed to run: " . substr($output, 0, 200));
        return true;
    }
    if ($result['valid']) return true;
    return isset($result['errors']) ? $result['errors'] : array("Unknown error");
}

// Settings used by each interfacer type, read from the emonhub source.
// Cached in redis until a source file changes.
function emonhub_interfacer_settings($src)
{
    global $redis;

    $script = __DIR__ . "/interfacer_settings.py";
    $files = glob("$src/interfacers/*.py");
    if (!$files) return null;
    $files[] = "$src/emonhub_interfacer.py";
    $files[] = $script;
    $signature = md5(implode(",", array_map('filemtime', $files)));

    if ($redis && $cached = $redis->get("emonhub:interfacer_settings")) {
        $cached = json_decode($cached, true);
        if (isset($cached['signature']) && $cached['signature'] == $signature) return $cached['settings'];
    }

    if (!function_exists('exec')) return null;
    exec("python3 " . escapeshellarg($script) . " " . escapeshellarg($src) . " 2>/dev/null", $output, $result);
    if ($result !== 0) return null;
    $settings = json_decode(implode("\n", $output), true);
    if (!$settings) return null;

    if ($redis) $redis->set("emonhub:interfacer_settings", json_encode(array('signature' => $signature, 'settings' => $settings)));
    return $settings;
}
