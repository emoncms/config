<?php global $path; ?>

<br>
<h3>EmonHub Config Editor</h3>
<button id="show-emoncmslogview" style="float:right">emoncms.log view</button>
<button id="show-emonhublogview" style="float:right">emonhub.log view</button>
<button id="show-editor" style="float:right">Editor</button>

<div id="editor">
    <button class="save">Save</button><br><br>
    <textarea id="configtextarea" style="width:100%; height:600px"></textarea>
    <button class="save">Save</button>
</div>

<div id="emoncmslogview" style="display:none">
    <br><br>
    <pre id="emoncmslogviewpre"></pre>
    <button class="emoncmslogrefresh">Refresh</button>
</div>

<div id="emonhublogview" style="display:none">
    <br><br>
    <pre id="emonhublogviewpre"></pre>
    <button class="emonhublogrefresh">Refresh</button>
</div>

<script>

var path = "<?php echo $path; ?>";

var config = "";

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

$(".emonhublogrefresh").click(function(){
    emonhublog_refresh();
});

$(".emoncmslogrefresh").click(function(){
    emoncmslog_refresh();
});

$("#show-editor").click(function(){
    $("#editor").show();
    $("#emonhublogview").hide();
    $("#emoncmslogview").hide();
});

$("#show-emonhublogview").click(function(){
    emonhublog_refresh();
    $("#emonhublogview").show();
    $("#emoncmslogview").hide();
    $("#editor").hide();
});

$("#show-emoncmslogview").click(function(){
    emoncmslog_refresh();
    $("#emonhublogview").hide();
    $("#emoncmslogview").show();
    $("#editor").hide();
});

function emonhublog_refresh()
{
    $.ajax({ 
        url: path+"config/getemonhublog", 
        dataType: 'text', async: false, 
        success: function(data) {
            $("#emonhublogviewpre").html(data);
        } 
    });
}

function emoncmslog_refresh()
{
    $.ajax({ 
        url: path+"config/getemoncmslog", 
        dataType: 'text', async: false, 
        success: function(data) {
            $("#emoncmslogviewpre").html(data);
        } 
    });
}


</script>
