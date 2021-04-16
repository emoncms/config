var height = $("#wrap").height()-140;
$("pre").height(height)

var emonhublog_updater = false;
var autoupdate = false;
emonhublog_refresh();
enable_autoupdate();

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
                let message = response.hasOwnProperty('message') ? response.message: '';
                notify(message, 'error');
            }
        })
        .error(function(xhr,error,message){
            notify(gettext('Error sending data'));
        });
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
