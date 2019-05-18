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
    global $route, $session, $redis, $homedir;
    $result = false;
    
    $emonhub_config_file = "$homedir/data/emonhub.conf";
    $emonhub_logfile = "/var/log/emonhub/emonhub.log";
    $restart_log= "$homedir/restart.log";
    
    if (!$session['write']) return false;
     
    if ($route->action == '') {
        $route->format = "html";
        $route->submenu = view("Modules/config/sidebar.php");
        return view("Modules/config/view.php");
    }

    // ---------------------------------------------------------

    if ($route->action == 'editor') {
        $route->format = "html";
        $conf = $redis->get("get:emonhubconf");
        return view("Modules/config/editor.php", array("conf"=>$conf));
    }
    
    else if ($route->action == 'get') {
        $route->format = "text";
        return file_get_contents($emonhub_config_file);
    }
    
    else if ($route->action == 'getemonhublog') {
        $route->format = "text";
        //ob_start();
        //passthru("journalctl -u emonhub -n 30 --no-pager");
        if (file_exists($emonhub_logfile)) {
          ob_start();
          $handle = fopen($emonhub_logfile, "r");
          $lines = 200;
          $linecounter = $lines;
          $pos = -2;
          $beginning = false;
          $text = array();
          while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
              if(!empty($handle) && fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
              }
              if(!empty($handle)) $t = fgetc($handle);
              $pos --;
            }
            $linecounter --;
            if ($beginning) {
              rewind($handle);
            }
            $text[$lines-$linecounter-1] = fgets($handle);
            if ($beginning) break;
          }
          foreach ($text as $line) {
            echo $line;
          }
          $result = trim(ob_get_clean());
        }
        //return trim(ob_get_clean());
        return array('content'=>$result, 'fullwidth'=>false);
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
          passthru("journalctl -u emonhub --no-pager");        }
        exit;
    }

    // emonHub restart requires added to /etc/sudoers:
    // www-data ALL=(ALL) NOPASSWD:service emonhub restart
    else if ($route->action == 'restart')
    {
        list($scriptPath) = get_included_files();
        $basedir = str_replace("/index.php","",$scriptPath);
        $restart_script = "$basedir/Modules/config/./restart.sh";
        if ($redis->rpush("service-runner","$restart_script>$restart_log")){
            $result= "service-runner trigger sent for $restart_script $homedir";
        } else {
            $result= "could not send trigger";
        }
        
    }

    return array('content'=>$result);
}
