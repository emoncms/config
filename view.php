<?php
global $path;
load_css("Modules/config/Views/config_view.css");
?>
<?php if(!empty($tabs)) echo $tabs ?>

<div class="panel-page cfg-page">
    <div class="page-header">
        <h3>EmonHub</h3>
        <div class="cfg-page-actions">
            <div class="btn-group cfg-views" role="group" aria-label="View">
                <button class="btn btn-default" id="show-emonhublogview"><span class="svg-icon-list"></span> Log</button>
                <?php if ($admin) { ?><button class="btn btn-default" id="show-configview"><span class="svg-icon-cog"></span> Configure</button><?php } ?>
            </div>
            <button class="btn btn-warning" id="restart">Restart</button>
        </div>
    </div>
    <p class="page-lead">EmonHub reads data from serial, SPI, Modbus, M-Bus and other sources, and passes it to emoncms inputs.</p>

    <?php if ($admin) { ?>
    <div id="configview" style="display:none">
        <?php include "Modules/config/Views/config_ui.php"; ?>
        <p class="cfg-doc-link"><a href="https://docs.openenergymonitor.org/emonhub/configuration.html" target="_blank">EmonHub configuration documentation</a></p>
    </div>

    <div id="editor" style="display:none">
        <div class="panel">
            <div class="panel-header panel-header-static">
                <span class="panel-accent"></span>
                <span class="panel-name">emonhub.conf</span>
                <button class="btn btn-default btn-sm" id="show-form">Back to form</button>
            </div>
            <textarea id="configtextarea" class="form-control cfg-textarea" spellcheck="false"></textarea>
            <div class="cfg-panel-footer">
                <a href="https://docs.openenergymonitor.org/emonhub/configuration.html" target="_blank">EmonHub configuration documentation</a>
                <button class="btn btn-primary" id="save">Save config</button>
            </div>
        </div>
    </div>
    <?php } ?>

    <div id="emonhublogview" style="display:none">
        <div class="panel">
            <div class="panel-header panel-header-static">
                <span class="panel-accent"></span>
                <span class="panel-name">emonhub.log</span>
                <input type="search" class="form-control form-control-sm log-filter" placeholder="Filter whole log" aria-label="Filter log">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Auto update</span>
                    <button class="btn btn-sm auto-update-toggle btn-success">ON</button>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Auto scroll</span>
                    <button class="btn btn-sm auto-scroll-toggle btn-success">ON</button>
                </div>
                <button class="btn btn-default btn-sm log-expand-toggle" title="Expand"><span class="svg-icon-expand"></span></button>
            </div>
            <section class="log-section">
                <pre id="emonhub-console-log" class="log" data-empty="No matching lines"></pre>
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
            <div class="cfg-panel-footer">
                <a href="https://github.com/openenergymonitor/emonhub" target="_blank">EmonHub documentation</a>
                <a href="<?php echo $path; ?>config/downloadlog" class="btn btn-primary"><span class="svg-icon-download"></span> Download log<span class="log-info"></span></a>
            </div>
        </div>
    </div>
</div>
<script>

var emonhublog_updater = false;
var logfile_position = 0;
const logdiv = document.getElementById("emonhub-console-log")

var autoupdate = true;
var auto_scroll = true;
var log_filter = "";
var log_filter_timer = false;
var loginfo_updater = false;

// Configure views are for admin users only
var has_config = typeof config_ui !== "undefined";

show_view("log");

// Log, configure and text editor views
function show_view(name) {
    if (!has_config) name = "log";
    // Text editor and configure UI share unsaved edits
    if ($("#editor").is(":visible") && name != "editor") {
        var text = $("#configtextarea").val();
        if (text != config_ui.text) config_ui.load(text, false);
    }
    if (name == "editor") $("#configtextarea").val(config_ui.text);

    $(".cfg-views .btn").removeClass("active");
    // Text editor is part of Configure
    $(name == "log" ? "#show-emonhublogview" : "#show-configview").addClass("active");

    $("#emonhublogview").toggle(name == "log");
    $("#configview").toggle(name == "config");
    $("#editor").toggle(name == "editor");
    // Log view uses the full page width
    $("body").toggleClass("log-view", name == "log");
    if (name == "log") {
        enable_autoupdate();
        emonhublog_refresh();
    } else {
        disable_autoupdate();
    }
}

$("#save").click(function(){
    var text = $("#configtextarea").val();
    $.ajax({ type: "POST", url: path+"config/set", data: { config: text, hash: config_ui.hash || "" }, dataType: 'json', success: function(result){
        if (result.success) {
            config_ui.load(text, true, result.hash);
            set_log_level_button(text);
            alert(result.message);
        } else if (result.conflict) {
            // Resolve in the form, which keeps these edits
            config_ui.load(text, false);
            show_view("config");
            config_ui.on_conflict(result.current, result.hash);
        } else {
            alert(result.message + (result.errors ? ":\n\n" + result.errors.join("\n") : ""));
        }
    }});
});

// Update log level button from config text
function set_log_level_button(text) {
    // loglevel in the [hub] section, before the next section
    var hub = text.match(/^\s*\[hub\][^\n]*\n([\s\S]*?)(?=^\s*\[|$(?![\s\S]))/m);
    var m = hub && hub[1].match(/^\s*loglevel\s*=\s*(\w+)/m);
    var level = m ? m[1].toUpperCase() : "";
    if (!level) return;
    $('#log-level .log-level-name').text(level);
    $('#log-level > a .log-dot').attr('class', 'log-dot level-'+level.toLowerCase());
    $('#log-level ul li a').removeClass('active');
    $('#log-level ul li a[data-level="'+level+'"]').addClass('active');
}

window.addEventListener("emonhub-config-saved", function(e) {
    set_log_level_button(e.detail);
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

$("#show-form").click(function(){ show_view("config"); });
$("#show-configview").click(function(){ show_view("config"); });
$("#show-emonhublogview").click(function(){ show_view("log"); });

// Server filters the whole log file, then new lines as they arrive
$(".log-filter").on("input", function() {
    clearTimeout(log_filter_timer);
    var filter = $(this).val().trim();
    log_filter_timer = setTimeout(function() {
        if (filter == log_filter) return;
        log_filter = filter;
        logfile_position = 0;
        logdiv.textContent = "";
        $(logdiv).toggleClass("log-filtered", filter != "");
        emonhublog_refresh();
    }, 300);
});

// Log card uses the full width and height of the window
var log_expanded = false;

function set_log_expanded(on) {
    log_expanded = on;
    $("body").toggleClass("log-expanded", on);
    $(".log-expand-toggle").attr("title", on ? "Collapse" : "Expand")
        .find("span").attr("class", on ? "svg-icon-contract" : "svg-icon-expand");
    size_log();
    if (auto_scroll) {
        logdiv.scrollTop = logdiv.scrollHeight;
        last_set_height = logdiv.scrollTop;
    }
}

// Height that fits the log and footer in the window
function size_log() {
    if (!log_expanded) {
        logdiv.style.height = "";
        return;
    }
    var top = logdiv.getBoundingClientRect().top + window.scrollY;
    var footer = $("#emonhublogview .cfg-panel-footer").outerHeight();
    logdiv.style.height = Math.max(300, window.innerHeight - top - footer - 16) + "px";
}

$(".log-expand-toggle").click(function() { set_log_expanded(!log_expanded); });
$(window).resize(size_log);

$("#emonhub-console-log").scroll(function() {

    if (auto_scroll) {
        if (last_set_height!=logdiv.scrollTop) {
            auto_scroll = false;
            auto_scroll_button(auto_scroll);
        }
    } else {
        if ((logdiv.scrollHeight-logdiv.scrollTop-logdiv.clientHeight)<20) {
            auto_scroll = true;
            auto_scroll_button(auto_scroll);
        }
    }

});

function enable_autoupdate() {
    autoupdate = true;
    clearInterval(emonhublog_updater);
    clearInterval(loginfo_updater);
    emonhublog_updater = setInterval(emonhublog_refresh,1000);
    loginfo_updater = setInterval(loginfo_refresh,10000);
    loginfo_refresh();
}

function disable_autoupdate() {
    autoupdate = false;
    clearInterval(emonhublog_updater);
    clearInterval(loginfo_updater);
}

// Log file size and time range
function loginfo_refresh() {
    $.ajax({ url: path+"config/loginfo", dataType: 'json', success: function(info) {
        var text = format_size(info.size);
        if (info.start && info.end) {
            var days = (new Date(info.end.replace(" ","T")) - new Date(info.start.replace(" ","T"))) / 86400000;
            text += ", "+days.toFixed(1)+" days";
        }
        $(".log-info").text(" ("+text+")");
    }});
}

function format_size(bytes) {
    if (bytes < 1024) return bytes+" B";
    if (bytes < 1048576) return (bytes/1024).toFixed(0)+" KB";
    return (bytes/1048576).toFixed(1)+" MB";
}

function emonhublog_refresh()
{
    var filter = log_filter;
    $.ajax({
        url: path+"config/getemonhublog?pos="+logfile_position+"&filter="+encodeURIComponent(filter),
        dataType: 'text', async: true,
        success: function(data) {
            // Filter changed while this request was running
            if (filter != log_filter) return;
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
    var level = $(this).data('level');
    $.ajax({ type: "POST", url: path+"config/setloglevel", data: { level: level }, dataType: 'json', success: function(result) {
        if (!result.success) {
            alert(result.message);
            return;
        }
        set_log_level_button("[hub]\nloglevel = " + level);
        if (!has_config) return;
        // Keep config UI in sync, unsaved edits are kept
        $.ajax({ url: path+"config/get", dataType: 'text', success: function(data, status, xhr) {
            config_ui.external_change(data, function(root) {
                EmonHubConf.set(EmonHubConf.child(root, "hub"), "loglevel", level);
            }, xhr.getResponseHeader("X-Config-Hash"));
            if ($("#editor").is(":visible")) $("#configtextarea").val(config_ui.text);
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
