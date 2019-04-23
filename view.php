<?php global $path; ?>
<style>
pre {
    width:100%;
    height:400px;
    
    margin:0px;
    padding:0px;
    font-size:16px;
    color:#fff;
    background-color:#300a24;
    overflow: scroll;
    overflow-x: hidden;
    
    font-size:16px;
}
#emoncms-console-log {
    padding-left:20px;
    padding-top:20px;
}
#emonhub-console-log {
    padding-left:20px;
    padding-top:20px;
}

</style>
<div id="wrapper">

  <h2>EmonHub</h2>
  Decodes data received from RFM69Pi / emonPi and post to MQTT + Emoncms
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
      <a href="https://github.com/openenergymonitor/emonhub/blob/emon-pi/configuration.md">EmonHub Config Documentation</a>
  </div>

  <div id="emonhublogview" style="display:none">

      <div class="input-prepend input-append">
          <span class="add-on">Auto update log view</span>
          <button class="btn autoupdate-toggle">ON</button>
      </div>
      <h4>Log:</h4>
      <pre id="emonhublogviewpre"><div id="emonhub-console-log"></div></pre><br>
      <div style="float: right;">
      <a href="<?php echo $path; ?>config/downloadlog" class="btn btn-info">Download Log</a>
      </div>
      <a href="https://github.com/openenergymonitor/emonhub">EmonHub Documentation</a>
  </div>
  
</div>

<script type="text/javascript" src="<?php echo $path; ?>Lib/misc/sidebar.js"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Lib/misc/sidebar.css">

<script>
init_sidebar({menu_element:"#config_menu"});

var path = "<?php echo $path; ?>";

var config = "";

var emonhublog_updater = false;
var autoupdate = false;
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
  $.ajax({ url: path+"config/restart", dataType: 'text', async: false});
});

$(".autoupdate-toggle").click(function(){
    if (autoupdate==true) {
        autoupdate = false;
        disable_autoupdate();
    } else {
        autoupdate = true;
        enable_autoupdate();
    }
});

$("#show-editor").click(function(){
    $("#editor").show();
    $("#emonhublogview").hide();
    disable_autoupdate();
});

$("#show-emonhublogview").click(function(){
    if (!autoupdate) enable_autoupdate();
    emonhublog_refresh();
    $("#emonhublogview").show();
    $("#editor").hide();
});


function enable_autoupdate() {
    autoupdate = true;
    $(".autoupdate-toggle").html("ON");
    emonhublog_updater = setInterval(emonhublog_refresh,1000);
}

function disable_autoupdate() {
    autoupdate = false;
    $(".autoupdate-toggle").html("OFF");
    clearInterval(emonhublog_updater);}

function emonhublog_refresh()
{
    $.ajax({
        url: path+"config/getemonhublog",
        dataType: 'text', async: true,
        success: function(data) {
            $("#emonhub-console-log").html(data+"\n\n");
        }
    });
}



</script>
