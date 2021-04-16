<?php global $path; $v=1; ?>
<link rel="stylesheet" href="<?php echo $path; ?>Modules/config/log/log.css?v=<?php echo $v ?>">
<h3>Log view</h3>

<div style="float: right;">
  <a href="<?php echo $path; ?>config/downloadlog" class="btn btn-info">Download Log</a>
</div>

<div class="input-prepend input-append">
    <span class="add-on">Auto update log view</span>
    <button class="btn autoupdate-toggle">ON</button>
    <button class="btn btn-warning" id="restart">Restart EmonHub</button>
</div>

<section>
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

<script>
// return object of gettext translated strings
function getTranslations(){
    return {
        'Log level: %s': "<?php echo _('Log level: %s') ?>",
        'Error sending data': "<?php echo _('Error sending data') ?>"
    }
}
</script>
<script src="<?php echo $path; ?>Modules/config/log/log.js?v=<?php echo $v ?>"></script>
