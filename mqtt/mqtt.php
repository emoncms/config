<?php global $path; $v=1; ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<style>
.content-container {
  max-width:1150px;
}
input[type=text] {
  width:120px;
}
select {
  width:120px;
}
.box {
  padding: 15px;
  border-top: 1px solid #ccc;
  border-left: 1px solid #ccc;
  border-right: 1px solid #ccc; 
}

.add-on {
  width:200px !important;
}

.box:last-child {
  border-bottom: 1px solid #ccc;
}
</style>

<h3>MQTT Connect</h3>

<p>Send data over MQTT from EmonHub</p>

<div id="app">
  <div class="box">
    <h4>Local</h4>

    <p><b>Init settings</b></p>

    <div class="input-prepend">
      <span class="add-on">Host</span>
      <input type="text" v-model="mqtt_template.init_settings.mqtt_host" @change="update" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Port</span>
      <input type="text" v-model="mqtt_template.init_settings.mqtt_port" @change="update" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">User</span>
      <input type="text" v-model="mqtt_template.init_settings.mqtt_user" @change="update" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Password</span>
      <input type="text" v-model="mqtt_template.init_settings.mqtt_passwd" @change="update" />
    </div><br>

    <p><b>Runtime settings</b></p>

    <div class="input-prepend">
      <span class="add-on">Node format enable</span>
      <input type="text" v-model="mqtt_template.runtimesettings.node_format_enable" @change="update" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Node format basetopic</span>
      <input type="text" v-model="mqtt_template.runtimesettings.node_format_basetopic" @change="update" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Node:var format enable</span>
      <input type="text" v-model="mqtt_template.runtimesettings.nodevar_format_enable" @change="update" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Node:var format basetopic</span>
      <input type="text" v-model="mqtt_template.runtimesettings.nodevar_format_basetopic" @change="update" />
    </div><br>

    <button v-if="show_apply_configuration" class="btn btn-warning" @click="apply()">Apply configuration</button>
  </div>
</div>
<!--
<br><br>
<pre id="conf"></pre>
-->
<script> 
var conf = <?php echo !empty($conf) ? $conf: "{}"; ?>;
// $("#conf").html(JSON.stringify(conf.interfacers.MQTT, null, 2));

var mqtt_template = {};

$.getJSON( path+"Modules/config/mqtt/template.json?v=1", function( result ) {
    mqtt_template = result
    $("#conf").html(JSON.stringify(mqtt_template, null, 2));
    
    var app = new Vue({
        el: '#app',
        data: {
            mqtt_template: mqtt_template,
            show_apply_configuration: false
        },
        methods: {
            update: function() {
                if (JSON.stringify(this.mqtt_template)!=JSON.stringify(conf.interfacers.MQTT)) {
                    this.show_apply_configuration = true;
                }
            },
            apply: function() {
                conf.interfacers.MQTT = JSON.parse(JSON.stringify(this.mqtt_template));           
                // Save config
                $.post( path+"config/save", { conf: JSON.stringify(conf) })
                    .done(function(r) {
                        app.show_apply_configuration = false
                    })
                    .fail(function(xhr, status, error) {
                        alert("There was an error applying configuration")
                    });            
            }
        }
    });
    
    if (JSON.stringify(app.mqtt_template)!=JSON.stringify(conf.interfacers.MQTT)) {
        app.show_apply_configuration = true;
    }
});
</script>
