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

<h3>EmonHub Config</h3>
<br>
<a href="https://github.com/openenergymonitor/emonhub/blob/emon-pi/configuration.md">Documentation</a>

<div class="input-prepend input-append" style="float:right">
    <button class="btn" id="show-emonhublogview">View log</button>
    <button class="btn" id="download-log">Download log</button>
    <button class="btn" id="show-editor">Edit config</button>
</div>

<div id="editor">
    <textarea id="configtextarea" style="width:100%; height:600px"></textarea>
    <button class="save">Save</button>
</div>

<div id="emonhublogview" style="display:none">

    <div class="input-prepend input-append">
        <span class="add-on">Auto update log view</span>
        <button class="btn autoupdate-toggle">ON</button>
    </div>
    
    <pre id="emonhublogviewpre"><div id="emonhub-console-log"></div></pre><br>

</div>

<script>

var path = "<?php echo $path; ?>";

var config = "";

var emonhublog_updater = false;
var autoupdate = false;

$.ajax({
    url: path+"config/get",
    dataType: 'text', async: false,
    success: function(data) {
        config = data;
    }
});

$("#configtextarea").val(config);

$(".save").click(function(){
    config = $("#configtextarea").val();
    $.ajax({ type: "POST", url: path+"config/set", data: "config="+config, async: false, success: function(data){console.log(data);} });
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
    $("#emoncmslogview").hide();
    disable_autoupdate();
});

$("#show-emonhublogview").click(function(){
    if (!autoupdate) enable_autoupdate();
    emonhublog_refresh();
    $("#emonhublogview").show();
    $("#editor").hide();
});

$("#download-log").click(function(){
    $("#emonhublogview").show();
    $("#editor").hide();
    $.ajax({
        url: path+"config/downloadlog",
        dataType: 'text', async: false,
    })
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
