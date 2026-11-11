<?php
global $path;
?>
<style>
section {
    position: relative;
}
.log { padding:20px; box-sizing:content-box }
.log-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; background-color:#adb5bd }
.log-dot.level-debug { background-color:#198754 }
.log-dot.level-info { background-color:#0d6efd }
.log-dot.level-warning { background-color:#ffc107 }
.log-dot.level-error { background-color:#dc3545 }
.log-dot.level-critical { background-color:#842029 }
#log-level .dropdown-menu a { text-align:left }
#log-level { right:12px; bottom:12px }
#log-level > .btn { border:1px solid #6c757d; box-shadow:0 2px 6px rgba(0,0,0,0.4) }
#log-level > .btn .log-dot { width:12px; height:12px; vertical-align:-1px }

</style>
  <?php if(!empty($tabs)) echo $tabs ?>

  <div class="page-header">
    <h3>EmonHub</h3>
  </div>
  Emonhub provides the interface between hardware and emoncms inputs. Read from serial, spi, modbus, mbus and more.
  <br><br>
  <div class="input-group" style="float:right">
      <button class="btn btn-info" id="show-emonhublogview">View log</button>
      <button class="btn btn-danger" id="show-editor">Edit config</button>
      <button class="btn btn-warning" id="restart">Restart</button>
  </div>

  <div id="editor">
      <h4>Config:</h4>
      <textarea id="configtextarea" class="form-control mb-2" style="height:400px"></textarea><br>
      <button class="btn btn-warning" id="save">Save</button><br><br>
      <a href="https://github.com/openenergymonitor/emonhub/blob/emon-pi/configuration.md" target="_blank">EmonHub Config Documentation</a>
  </div>

  <div id="emonhublogview" style="display:none">
      <div class="input-group">
          <span class="input-group-text">Auto update</span>
          <button class="btn auto-update-toggle btn-success">ON</button>
      </div>
      <div class="input-group">
          <span class="input-group-text">Auto scroll</span>
          <button class="btn auto-scroll-toggle btn-success">ON</button>
      </div>
      <section>
        <h4>Log:</h4>
        <pre id="emonhub-console-log" class="log" style="height:600px"></pre>
        <div id="log-level" class="dropup dropdown">
            <a class="btn dropdown-toggle btn-dark text-uppercase" data-bs-toggle="dropdown" href="#" title="Change the logging level">
            <span class="log-dot level-<?php echo strtolower($level) ?>"></span>Log Level: <strong class="log-level-name"><?php echo $level ? $level : "UNKNOWN" ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
            <?php
            foreach ($log_levels as $name) {
                $active = $level == $name ? ' active' : '';
                printf('<li><a href="#" data-level="%s" class="btn btn-default w-100%s"><span class="log-dot level-%s"></span>%s</a></li>', $name, $active, strtolower($name), $name);
            }
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

function emonhublog_refresh()
{
    $.ajax({
        url: path+"config/getemonhublog?pos="+logfile_position,
        dataType: 'text', async: true,
        success: function(data) {
            var firstnewline = data.indexOf("\n");
            logfile_position = parseInt(data.substr(0, firstnewline));
            data = data.substr(firstnewline+1,data.length);
            logdiv.textContent += data;
            
            if (auto_scroll) {
                logdiv.scrollTop = logdiv.scrollHeight;
                last_set_height = logdiv.scrollTop;
            }
        }
    });
}


$('#log-level ul li a').click(function(event){
    event.preventDefault();
    var $btn = $(this);
    var level = $btn.data('level');
    $.ajax({ type: "POST", url: path+"config/setloglevel", data: { level: level }, dataType: 'json', success: function(result) {
        if (!result.success) {
            alert(result.message);
            return;
        }
        $('#log-level .log-level-name').text(level);
        $('#log-level > a .log-dot').attr('class', 'log-dot level-'+level.toLowerCase());
        $('#log-level ul li a').removeClass('active');
        $btn.addClass('active');
        // Keep editor in sync with the updated config
        $.ajax({ url: path+"config/get", dataType: 'text', success: function(data) {
            config = data;
            $("#configtextarea").val(config);
        }});
    }});
});

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
