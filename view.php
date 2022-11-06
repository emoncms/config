<?php 
global $path; 

$level = "DEBUG";
$log_levels = array(
    1=>"DEBUG",
    2=>"INFO",
    3=>"WARNING",
    4=>"ERROR",
    5=>"CRITICAL"
);
?>
<style>
section {
    position: relative;
}
.dropdown-menu-right {
    right: 0 !important;
    left: initial;
}
.log { padding:20px}

</style>
  <?php if(!empty($tabs)) echo $tabs ?>

  <h2>EmonHub</h2>
  Emonhub provides the interface between hardware and emoncms inputs. Read from serial, spi, modbus, mbus and more.
  <br><br>
  <div class="input-prepend input-append" style="float:right">
      <button class="btn btn-info" id="show-emonhublogview">View log</button>
      <button class="btn btn-danger" id="show-editor">Edit config</button>
      <button class="btn btn-warning" id="restart">Restart</button>
  </div>

  <div id="editor">
      <h4>Config:</h4>
      <textarea id="configtextarea" style="width:100%; height:400px"></textarea><br>
      <button class="btn btn-warning" id="save">Save</button><br><br>
      <a href="https://github.com/openenergymonitor/emonhub/blob/emon-pi/configuration.md" target="_blank">EmonHub Config Documentation</a>
  </div>

  <div id="emonhublogview" style="display:none">
      <div class="input-prepend input-append">
          <span class="add-on">Auto update</span>
          <button class="btn auto-update-toggle btn-success">ON</button>
      </div>
      <div class="input-prepend input-append">
          <span class="add-on">Auto scroll</span>
          <button class="btn auto-scroll-toggle btn-success">ON</button>
      </div>
      <section>
        <h4>Log:</h4>
        <pre id="emonhub-console-log" class="log" style="height:600px"></pre>
        <div id="log-level" class="dropup dropdown">
            <a class="btn btn-small dropdown-toggle btn-inverse text-uppercase" data-toggle="dropdown" href="#" title="Change the logging level">
            <span class="log-level-name">Log Level: <?php echo $level ?></span>
            <span class="caret"></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-right">
            <?php
            if(!empty($log_levels)): foreach($log_levels as $_level=>$name):
                $active = $level == $name ? ' active': '';
                printf('<li><a href="#" data-key="%s" class="btn%s">%s</a></li>', $_level, $active, $name);
            endforeach; endif;
            ?>
            </ul>
        </div>
      </section>
      <br>
      <div style="float: right;">
      <a href="<?php echo $path; ?>config/downloadlog" class="btn btn-info">Download Log</a>
      </div>
      <a href="https://github.com/openenergymonitor/emonhub" target="_blank">EmonHub Documentation</a>
  </div>
<script>

var config = "";

var emonhublog_updater = false;
var logfile_position = 0;
const logdiv = document.getElementById("emonhub-console-log")

var autoupdate = true;
var auto_scroll = true;

var log_level = "DEBUG";
var log_levels = <?php echo json_encode($log_levels); ?>;

var downloaded_log = "";

$("#emonhublogview").show();
$("#editor").hide();
emonhublog_refresh();
enable_autoupdate();

$.ajax({
    url: path+"config/get",
    dataType: 'text', async: false,
    success: function(data) {
        config = data;
    }
});

$("#configtextarea").val(config);

$("#save").click(function(){
    config = $("#configtextarea").val();
    $.ajax({ type: "POST", url: path+"config/set", data: "config="+config, async: false, success: function(data){
      console.log(data);
      alert(data);
    }});
});

$("#restart").click(function(){
  alert("Restarting EmonHub...");
  $.ajax({ url: path+"admin/service/restart?name=emonhub", dataType: 'text', async: false});
});

$(".auto-update-toggle").click(function(){
    if (autoupdate==true) {
        disable_autoupdate();
    } else {
        enable_autoupdate();
    }
    auto_update_button(autoupdate);
});

$(".auto-scroll-toggle").click(function(){
    if (auto_scroll==true) {
        auto_scroll = false;
    } else {
        auto_scroll = true;
    }
    auto_scroll_button(auto_scroll);
});

$("#show-editor").click(function(){
    $("#editor").show();
    $("#emonhublogview").hide();
    disable_autoupdate();
});

$("#show-emonhublogview").click(function(){
    enable_autoupdate();
    emonhublog_refresh();
    $("#emonhublogview").show();
    $("#editor").hide();
});

$("#emonhub-console-log").scroll(function() {

    if (auto_scroll) {
        if (last_set_height!=logdiv.scrollTop) {
            auto_scroll = false;
            auto_scroll_button(auto_scroll);
        }
    } else {
        if (((logdiv.scrollHeight-logdiv.scrollTop)-640)<20) {
            auto_scroll = true;
            auto_scroll_button(auto_scroll);
        }
    }

});

function enable_autoupdate() {
    autoupdate = true;
    clearInterval(emonhublog_updater);
    emonhublog_updater = setInterval(emonhublog_refresh,1000);
}

function disable_autoupdate() {
    autoupdate = false;
    clearInterval(emonhublog_updater);
}

function filter_log_level(data_in, level) {
  if (level=="DEBUG") return data_in;
  var filter = [];
  if (level=="INFO") filter = ["INFO","WARNING","ERROR","CRITICAL"];
  if (level=="WARNING") filter = ["WARNING","ERROR","CRITICAL"];
  if (level=="ERROR") filter = ["ERROR","CRITICAL"];
  if (level=="CRITICAL") filter = ["CRITICAL"];
  
  var lines_in = data_in.split("\n");
  
  if (lines_in.length==1 && lines_in[0]=="") return "";
  
  var lines_out = [];
  for (var z=0; z<lines_in.length; z++) {
    for (var f in filter) {
      if (lines_in[z].indexOf(filter[f])!=-1) {
        lines_out.push(lines_in[z]);
      }
    }
  }
  if (lines_out.length==0) return "";
  return lines_out.join("\n")+"\n";
}

function emonhublog_refresh()
{
    $.ajax({
        url: path+"config/getemonhublog?pos="+logfile_position,
        dataType: 'text', async: true,
        success: function(data) {
            var firstnewline = data.indexOf("\n");
            logfile_position = parseInt(data.substr(0, firstnewline));
            data = data.substr(firstnewline+1,data.length);
            
            downloaded_log += data;
            logdiv.textContent += filter_log_level(data,log_level);
            
            if (auto_scroll) {
                logdiv.scrollTop = logdiv.scrollHeight;
                last_set_height = logdiv.scrollTop;
            }
        }
    });
}


$(function(){
    $('#log-level ul li a').click(function(event){
        event.preventDefault();
        var $btn = $(this);
        var $toggle = $btn.parents('ul').prev('.btn');
        var key = $btn.data('key');
       
        $toggle.find('.log-level-name').text(gettext('log level: %s').replace('%s',log_levels[""+key]));
        $btn.addClass('active');
        $btn.parents('li').siblings().find('a').removeClass('active');
        
        log_level = log_levels[""+key];
        
        logdiv.textContent = filter_log_level(downloaded_log,log_level);
        
        if (auto_scroll) {
            logdiv.scrollTop = logdiv.scrollHeight;
            last_set_height = logdiv.scrollTop;
        }
    })
})

/**
 * emulate the php gettext function for replacing php strings in js
 */
function gettext(property) {
        _strings = typeof translations === 'undefined' ? getTranslations() : translations;
    if (_strings.hasOwnProperty(property)) {
        return _strings[property];
    } else {
        return property;
    }
}
/**
 * return object of gettext translated strings
 *
 * @return object
 */
function getTranslations(){
    return {
        'Log level: %s': "<?php echo _('Log level: %s') ?>",
        'Error sending data': "<?php echo _('Error sending data') ?>"
    }
}

function auto_update_button(state) {
    if (state==true) {
        $(".auto-update-toggle").html("ON").removeClass('btn-warning').addClass('btn-success');
    } else {
        $(".auto-update-toggle").html("OFF").removeClass('btn-success').addClass('btn-warning');
    }
}

function auto_scroll_button(state) {
    if (state==true) {
        $(".auto-scroll-toggle").html("ON").removeClass('btn-warning').addClass('btn-success');
    } else {
        $(".auto-scroll-toggle").html("OFF").removeClass('btn-success').addClass('btn-warning');
    }
}
</script>
