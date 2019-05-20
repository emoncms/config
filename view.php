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
#log-level-dropdown {
    position: absolute;
    right: .2rem;
    bottom: 0;
}
#log-level-dropdown .dropdown-menu {
    background: 0;
    background: transparent;
    border-radius: 0;
    border: 0;
    box-shadow: none;
    margin: 0;
}
#log-level-dropdown .dropdown-menu .active {
    font-weight: bold;
}
#log-level-dropdown .dropdown-menu .btn {
    border: 0;
    border-radius: 0;
    background-image: none;
}
#log-level-dropdown li:first-child .btn {
    border-radius: .3em .3em 0 0;
}
#log-level-dropdown li:last-child .btn {
    border-radius: 0 0 .3em .3em;
}
section {
    position: relative;
}
.dropdown-menu-right {
    right: 0 !important;
    left: initial;
}
#snackbar {
    visibility: hidden;
    min-width: 250px;
    margin-left: -125px;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 2px;
    padding: 16px;
    position: fixed;
    z-index: 1;
    left: 50%;
    bottom: 30px;
    font-size: 17px;
}
#snackbar.show {
    visibility: visible;
    animation: fadein 0.5s, fadeout 0.5s 2.5s;
}
</style>
  <?php if(!empty($tabs)) echo $tabs ?>

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
      <section>
        <h4>Log:</h4>
        <pre id="emonhublogviewpre"><div id="emonhub-console-log"></div></pre>
        <div id="log-level-dropdown" class="dropup dropdown">
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
      <a href="https://github.com/openenergymonitor/emonhub">EmonHub Documentation</a>
  </div>

<div id="snackbar"></div>
<script>

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


$(function(){
    $('#log-level-dropdown ul li a').click(function(event){
        event.preventDefault();
        var $btn = $(this);
        var $toggle = $btn.parents('ul').prev('.btn');
        var key = $btn.data('key');
        var data = {level:key};
        $.post( path+"config/loglevel", data)
        .done(function(response) {
            // make the dropdown toggle show the new setting
            if(response.hasOwnProperty('success') && response.success!==false) {
                $toggle.find('.log-level-name').text(gettext('log level: %s').replace('%s',response['log-level']));
                // highlight the current dropdown element as active
                $btn.addClass('active');
                $btn.parents('li').siblings().find('a').removeClass('active');
                notify(gettext('Log level set to: %s').replace('%s',response['log-level']),'success');
            } else {
                notify(gettext('Log level not set'), 'error', response.hasOwnProperty('message') ? response.message: '');
            }
        })
        .error(function(xhr,error,message){
            notify(gettext('Error sending data'));
        });
    })
})
function snackbar(text) {
    var snackbar = document.getElementById("snackbar");
    snackbar.innerHTML = text;
    snackbar.className = "show";
    setTimeout(function () {
        snackbar.className = snackbar.className.replace("show", "");
    }, 3000);
}

function notify(message, css_class, more_info) {
    // @todo: show more information in the user notifications
    snackbar(message);
}
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
</script>
