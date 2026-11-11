<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Summary of emonhub.log: activity of each interfacer since the last start,
// and known problems matched against log_rules.json.
function emonhub_log_summary($logfile, $configfile, $max_bytes = 8388608)
{
    $conf = emonhub_conf_summary($configfile);
    $rules = emonhub_log_rules();

    $summary = array(
        'level' => $conf['loglevel'],
        'start' => "",
        'end' => "",
        'age' => null,
        'version' => "",
        'run_start' => "",
        'starts' => 0,
        'interfacers' => array(),
        'issues' => array()
    );
    if (!file_exists($logfile) || !($fh = fopen($logfile, 'r'))) return $summary;

    // Large logs: read the end only
    $size = filesize($logfile);
    if ($size > $max_bytes) {
        fseek($fh, $size - $max_bytes);
        fgets($fh);
    }

    $issues = array();
    $act = array();
    $clean_exit = true;

    while (($line = fgets($fh)) !== false) {
        // Traceback and other continuation lines are skipped
        if (!preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d),\d+ (DEBUG|INFO|WARNING|ERROR|CRITICAL)\s+(\S+)\s+(.*?)\s*$/', $line, $m)) continue;
        list(, $ts, $level, $thread, $msg) = $m;
        if ($summary['start'] === "") $summary['start'] = $ts;
        $summary['end'] = $ts;

        // New run: activity is counted from the latest start
        if ($thread == "MainThread" && preg_match('/^EmonHub v(\S+)/', $msg, $v)) {
            if ($summary['starts'] > 0 && !$clean_exit) {
                emonhub_log_issue($issues, "unclean", "warning", "Stopped without a clean exit",
                    "EmonHub stopped without logging 'Exiting hub', for example after a crash, power loss or a killed process. Look at the lines before each start.",
                    "", "EmonHub v", $ts);
            }
            $summary['starts']++;
            $summary['version'] = $v[1];
            $summary['run_start'] = $ts;
            $clean_exit = false;
            $act = array();
            foreach ($issues as &$issue) $issue['current'] = 0;
            unset($issue);
            continue;
        }
        if ($thread == "MainThread" && $msg == "Exiting hub...") $clean_exit = true;
        if ($thread == "MainThread" && preg_match('/^Logging level set to (\w+)/', $msg, $l) && $summary['level'] === "") {
            $summary['level'] = strtoupper($l[1]);
        }

        if ($thread != "MainThread") emonhub_log_activity($act, $thread, $ts, $msg);

        // First matching rule wins
        $matched = false;
        foreach ($rules as $rule) {
            if (!preg_match($rule['regex'], $msg, $r)) continue;
            $matched = true;
            if ($rule['severity'] == "ignore") break;
            $name = $thread != "MainThread" ? $thread : "";
            if (isset($rule['interfacer'])) $name = emonhub_log_subst($rule['interfacer'], $r);
            if ($name === "" && isset($rule['port'])) {
                $port = emonhub_log_subst($rule['port'], $r);
                foreach ($conf['interfacers'] as $n => $i) {
                    if ($i['com_port'] === $port) $name = $n;
                }
            }
            $title = emonhub_log_subst($rule['title'], $r);
            emonhub_log_issue($issues, $rule['id']."|".$title, $rule['severity'], $title,
                emonhub_log_subst($rule['help'], $r), $name, $r[0], $ts);
            break;
        }

        // Unknown warnings and errors, grouped by message with numbers removed
        if (!$matched && in_array($level, array("WARNING", "ERROR", "CRITICAL"))) {
            $severity = $level == "WARNING" ? "warning" : "error";
            $key = "other|$thread|".preg_replace('/\d+/', '#', substr($msg, 0, 200));
            // Filter on the text before the first number
            $filter = trim(preg_replace('/\d.*$/', '', $msg));
            if (strlen($filter) < 12) $filter = substr($msg, 0, 80);
            emonhub_log_issue($issues, $key, $severity, $msg, "", $thread != "MainThread" ? $thread : "", $filter, $ts);
        }
    }
    fclose($fh);

    // Seconds since the last log line, from the file time to avoid time zone differences
    $summary['age'] = max(0, time() - filemtime($logfile));
    $now = $summary['end'] !== "" ? strtotime($summary['end']) + $summary['age'] : time();

    // Config checks
    foreach ($conf['interfacers'] as $name => $i) {
        if ($i['type'] == "EmonHubEmoncmsHTTPInterfacer" && $i['senddata'] && !$i['apikey_valid']) {
            emonhub_log_issue($issues, "apikey|$name", "error", "$name apikey not set",
                "Data is discarded until a 32 character read and write apikey is set for $name.",
                $name, "", "");
            $issues["apikey|$name"]['config'] = true;
        }
    }

    // Interfacers in config order, then any others seen in the log
    $names = array_keys($conf['interfacers']);
    foreach (array_keys($act) as $name) {
        if (!in_array($name, $names)) $names[] = $name;
    }
    foreach ($names as $name) {
        $type = isset($conf['interfacers'][$name]) ? $conf['interfacers'][$name]['type'] : "";
        $senddata = isset($conf['interfacers'][$name]) ? $conf['interfacers'][$name]['senddata'] : true;
        $a = isset($act[$name]) ? $act[$name] : emonhub_log_activity_init();
        $summary['interfacers'][] = emonhub_log_interfacer_status($name, $type, $senddata, $a, $issues, $summary, $now);
    }

    // Current issues first, then by severity and most recent
    $rank = array("error" => 0, "warning" => 1, "info" => 2);
    $issues = array_values($issues);
    usort($issues, function($a, $b) use ($rank) {
        $ca = $a['current'] > 0 || !empty($a['config']);
        $cb = $b['current'] > 0 || !empty($b['config']);
        if ($ca != $cb) return $ca ? -1 : 1;
        if ($rank[$a['severity']] != $rank[$b['severity']]) return $rank[$a['severity']] - $rank[$b['severity']];
        return strcmp($b['last'], $a['last']);
    });
    $summary['issues'] = $issues;
    return $summary;
}

function emonhub_log_issue(&$issues, $key, $severity, $title, $help, $interfacer, $filter, $ts)
{
    if (!isset($issues[$key])) {
        $issues[$key] = array(
            'severity' => $severity, 'title' => $title, 'help' => $help,
            'interfacer' => $interfacer, 'filter' => $filter,
            'count' => 0, 'current' => 0, 'first' => $ts, 'last' => $ts
        );
    }
    if ($ts === "") return;
    $issues[$key]['count']++;
    $issues[$key]['current']++;
    $issues[$key]['last'] = $ts;
    // Latest wording, numbers may differ
    if (strpos($key, "other|") === 0) $issues[$key]['title'] = $title;
}

function emonhub_log_activity_init()
{
    return array(
        'last' => "", 'frames' => 0, 'first_frame' => "", 'last_frame' => "",
        'nodes' => array(), 'node' => "",
        'sends' => 0, 'acks' => 0, 'last_ack' => "", 'buffer' => null,
        'connected' => null, 'publishes' => 0
    );
}

// Signs of normal operation, most only logged at DEBUG
function emonhub_log_activity(&$act, $name, $ts, $msg)
{
    if (!isset($act[$name])) $act[$name] = emonhub_log_activity_init();
    $a = &$act[$name];
    $a['last'] = $ts;

    if (preg_match('/^\d+ NEW FRAME/', $msg)) {
        $a['frames']++;
        if ($a['first_frame'] === "") $a['first_frame'] = $ts;
        $a['last_frame'] = $ts;
    }
    else if (preg_match('/^\d+ From Node : (.+)$/', $msg, $n)) {
        $node = $n[1];
        if (!isset($a['nodes'][$node])) $a['nodes'][$node] = array('frames' => 0, 'last' => "", 'rssi_min' => null, 'rssi_max' => null);
        $a['nodes'][$node]['frames']++;
        $a['nodes'][$node]['last'] = $ts;
        $a['node'] = $node;
    }
    else if (preg_match('/^\d+\s+RSSI : (-?\d+)/', $msg, $n) && isset($a['nodes'][$a['node']])) {
        $node = &$a['nodes'][$a['node']];
        $rssi = (int) $n[1];
        if ($node['rssi_min'] === null || $rssi < $node['rssi_min']) $node['rssi_min'] = $rssi;
        if ($node['rssi_max'] === null || $rssi > $node['rssi_max']) $node['rssi_max'] = $rssi;
    }
    else if (strpos($msg, "sending: ") === 0) $a['sends']++;
    else if (strpos($msg, "acknowledged receipt") === 0) {
        $a['acks']++;
        $a['last_ack'] = $ts;
    }
    else if (preg_match('/^Buffer size: (\d+)/', $msg, $n)) $a['buffer'] = (int) $n[1];
    else if (strpos($msg, "connection status: Connection successful") === 0) $a['connected'] = true;
    else if (preg_match('/^(Unexpected disconnection|Connection refused|Could not connect)/', $msg)) $a['connected'] = false;
    else if (strpos($msg, "Publishing: ") === 0) $a['publishes']++;
}

// Status: ok, warning, error, idle or unknown, with a short description
function emonhub_log_interfacer_status($name, $type, $senddata, $a, $issues, $summary, $now)
{
    $debug = $summary['level'] == "DEBUG";
    $run_time = $summary['run_start'] !== "" ? $now - strtotime($summary['run_start']) : 0;
    $status = "unknown";
    $text = "";
    $nodes = array();

    if ($type == "EmonHubEmoncmsHTTPInterfacer") {
        if (!$senddata) {
            $status = "idle";
            $text = "Sending disabled (senddata = 0)";
        } else if ($a['acks'] > 0) {
            $status = "ok";
            $text = $a['acks']." batches sent, last ".emonhub_log_ago($now - strtotime($a['last_ack']));
        } else if ($a['sends'] > 0) {
            $status = "warning";
            $text = $a['sends']." send attempts, none acknowledged";
        } else if ($debug && $a['buffer'] !== null) {
            $status = "warning";
            $text = "No data sent since start";
        } else if (!$debug) {
            $text = "Sending is only confirmed at DEBUG log level";
        }
        if ($a['buffer'] !== null) $text .= ($text ? ", " : "")."buffer ".$a['buffer'];
    }
    else if ($type == "EmonHubMqttInterfacer" || $a['connected'] !== null) {
        if ($a['connected'] === true) {
            $status = "ok";
            $text = "Connected";
            if ($a['publishes']) $text .= ", ".$a['publishes']." values published";
        } else if ($a['connected'] === false) {
            $status = "error";
            $text = "Not connected";
        } else {
            $text = "Connection state not logged since start";
        }
    }
    else if ($a['frames'] > 0) {
        $status = "ok";
        $since = $now - strtotime($a['last_frame']);
        $text = $a['frames']." frames, last ".emonhub_log_ago($since);
        if ($a['frames'] > 1) {
            $interval = (strtotime($a['last_frame']) - strtotime($a['first_frame'])) / ($a['frames'] - 1);
            $text .= ", every ".emonhub_log_duration($interval);
            if ($since > max(5 * $interval, 300)) {
                $status = "warning";
                $text = "No frames for ".emonhub_log_duration($since).", ".$a['frames']." before that";
            }
        }
        foreach ($a['nodes'] as $node => $n) {
            if ($node === $name) continue;
            $rssi = $n['rssi_min'] === null ? "" : ($n['rssi_min'] == $n['rssi_max'] ? "RSSI ".$n['rssi_min'] : "RSSI ".$n['rssi_min']." to ".$n['rssi_max']);
            $nodes[] = array('name' => (string) $node, 'frames' => $n['frames'], 'last' => emonhub_log_ago($now - strtotime($n['last'])), 'rssi' => $rssi);
        }
    }
    else if ($debug && $run_time > 300) {
        $status = "idle";
        $text = "No data since start";
    }
    else if (!$debug) {
        $text = "Data is only logged at DEBUG log level";
    }

    // Current problems for this interfacer override a good status
    $rank = array("ok" => 0, "unknown" => 0, "idle" => 1, "warning" => 2, "error" => 3);
    foreach ($issues as $issue) {
        if ($issue['interfacer'] !== $name || $issue['severity'] == "info") continue;
        if (!$issue['current'] && empty($issue['config'])) continue;
        if ($rank[$issue['severity']] > $rank[$status]) $status = $issue['severity'];
    }

    return array('name' => $name, 'type' => preg_replace('/^EmonHub|^EmonModbus|Interfacer$/', '', $type), 'status' => $status, 'text' => $text, 'nodes' => $nodes);
}

function emonhub_log_rules()
{
    $rules = json_decode(@file_get_contents(__DIR__."/log_rules.json"), true);
    if (!is_array($rules)) return array();
    foreach ($rules as $i => &$rule) {
        $rule['id'] = "r$i";
        $rule['regex'] = "~".$rule['match']."~";
        if (!isset($rule['title'])) $rule['title'] = "";
        if (!isset($rule['help'])) $rule['help'] = "";
    }
    return $rules;
}

// Replace $1..$9 with regex captures
function emonhub_log_subst($text, $captures)
{
    return preg_replace_callback('/\$(\d)/', function($m) use ($captures) {
        return isset($captures[$m[1]]) ? $captures[$m[1]] : "";
    }, $text);
}

function emonhub_log_duration($s)
{
    $s = (int) round($s);
    if ($s < 120) return $s." s";
    if ($s < 7200) return round($s / 60)." min";
    if ($s < 172800) return round($s / 3600)." h";
    return round($s / 86400)." days";
}

function emonhub_log_ago($s)
{
    return $s < 5 ? "just now" : emonhub_log_duration($s)." ago";
}

// Interfacers and loglevel from emonhub.conf. Apikey is checked for format only.
function emonhub_conf_summary($file)
{
    $conf = array('loglevel' => "", 'interfacers' => array());
    $text = @file_get_contents($file);
    if ($text === false) return $conf;

    $section = "";
    $name = "";
    foreach (explode("\n", $text) as $line) {
        $line = trim(preg_replace('/#.*$/', '', $line));
        if ($line === "") continue;
        if (preg_match('/^\[([^\[\]]+)\]$/', $line, $m)) {
            $section = $m[1];
            $name = "";
        }
        else if (preg_match('/^\[\[([^\[\]]+)\]\]$/', $line, $m)) {
            $name = $section == "interfacers" ? $m[1] : "";
            if ($name !== "") $conf['interfacers'][$name] = array('type' => "", 'com_port' => "", 'senddata' => true, 'apikey_valid' => false);
        }
        else if (preg_match('/^(\w+)\s*=\s*(.*)$/', $line, $m)) {
            $key = $m[1];
            $value = trim($m[2]);
            if ($section == "hub" && $key == "loglevel") $conf['loglevel'] = strtoupper($value);
            if ($name === "") continue;
            $i = &$conf['interfacers'][$name];
            if ($key == "Type") $i['type'] = $value;
            if ($key == "com_port") $i['com_port'] = $value;
            if ($key == "senddata") $i['senddata'] = $value !== "0";
            if ($key == "apikey") $i['apikey_valid'] = strlen($value) == 32 && strtolower($value) !== str_repeat("x", 32);
            unset($i);
        }
    }
    return $conf;
}
