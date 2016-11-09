<?php global $path; ?>

<style>
pre {
    width:100%;
    height:600px;
    
    
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

<br>
<h3>EmonHub Config Editor</h3>

<div class="input-prepend input-append" style="float:right">
    <button class="btn" id="show-editor">Editor</button>
    <button class="btn" id="show-emonhublogview">emonhub.log view</button>
    <button class="btn" id="show-emoncmslogview">emoncms.log view</button>
    <button class="btn" id="download-log">Download log</button>
</div>

<div id="editor">
    <button class="save">Save</button><br><br>
    <textarea id="configtextarea" style="width:100%; height:600px"></textarea>
    <button class="save">Save</button>
</div>

<div id="emoncmslogview" style="display:none">

    <div class="input-prepend input-append">
        <span class="add-on">Auto update</span>
        <button class="btn autoupdate-toggle">ON</button>
    </div>
    
    <pre id="emoncmslogviewpre"><div id="emoncms-console-log"></div></pre><br>

</div>

<div id="emonhublogview" style="display:none">

    <div class="input-prepend input-append">
        <span class="add-on">Auto update</span>
        <button class="btn autoupdate-toggle">ON</button>
    </div>
    
    <pre id="emonhublogviewpre"><div id="emonhub-console-log"></div></pre><br>

</div>

<script>

var path = "<?php echo $path; ?>";

var config = "";

var emonhublog_updater = false;
var emoncmslog_updater = false;
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
    $("#emoncmslogview").hide();
    $("#editor").hide();
});

$("#show-emoncmslogview").click(function(){
    if (!autoupdate) enable_autoupdate();
    emoncmslog_refresh();
    $("#emonhublogview").hide();
    $("#emoncmslogview").show();
    $("#editor").hide();
});

function enable_autoupdate() {
    autoupdate = true;
    $(".autoupdate-toggle").html("ON");
    emonhublog_updater = setInterval(emonhublog_refresh,1000);
    emoncmslog_updater = setInterval(emoncmslog_refresh,1000);
}

function disable_autoupdate() {
    autoupdate = false;
    $(".autoupdate-toggle").html("OFF");
    clearInterval(emonhublog_updater);
    clearInterval(emoncmslog_updater);
}

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

function emoncmslog_refresh()
{
    $.ajax({
        url: path+"config/getemoncmslog",
        dataType: 'text', async: true,
        success: function(data) {
            $("#emoncms-console-log").html(data+"\n\n");
        }
    });
}


</script>
