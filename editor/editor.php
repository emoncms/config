<?php global $path; $v=1; ?>
<style>
#configtextarea {
  background-color:#2e3436;
  color:#ced3cb;
}
.content-container {
  max-width:1150px;
}
</style>
<h3>Configuration editor</h3>
<div style="float:right"><a href="https://github.com/openenergymonitor/emonhub/blob/emon-pi/configuration.md" target="_blank">EmonHub Config Documentation</a></div>
<p>EmonHub configuration file editor: <b>/etc/emonhub/emonhub.conf</b></p>
<div id="editor">
    <textarea id="configtextarea" style="width:100%; height:400px;"></textarea><br>
    <button class="btn btn-warning" id="save">Save</button>
</div>
<script src="<?php echo $path; ?>Modules/config/editor/editor.js?v=<?php echo $v ?>"></script>
