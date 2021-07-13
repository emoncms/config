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
  <div>
  <div class="box" v-for="interfacer,interfacer_name in interfacers" v-if="interfacer.Type=='EmonHubMqttInterfacer'">
    <h4>{{ interfacer_name }}</h4>

    <p><b>Init settings</b></p>

    <div class="input-prepend">
      <span class="add-on">Host</span>
      <input type="text" v-model="interfacers[interfacer_name].init_settings.mqtt_host" @change="update(interfacer_name)" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Port</span>
      <input type="text" v-model="interfacers[interfacer_name].init_settings.mqtt_port" @change="update(interfacer_name)" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">User</span>
      <input type="text" v-model="interfacers[interfacer_name].init_settings.mqtt_user" @change="update(interfacer_name)" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Password</span>
      <input type="text" v-model="interfacers[interfacer_name].init_settings.mqtt_passwd" @change="update(interfacer_name)" />
    </div><br>

    <p><b>Runtime settings</b></p>

    <div class="input-prepend">
      <span class="add-on">Node format enable</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.node_format_enable" @change="update(interfacer_name)" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Node format basetopic</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.node_format_basetopic" @change="update(interfacer_name)" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Node:var format enable</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.nodevar_format_enable" @change="update(interfacer_name)" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Node:var format basetopic</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.nodevar_format_basetopic" @change="update(interfacer_name)" />
    </div><br>

    <button v-if="show_apply_configuration[interfacer_name]" class="btn btn-warning" @click="apply(interfacer_name)">Apply configuration</button>
  </div>
  </div>
  <br>
  <div class="input-prepend input-append">
      <span class="add-on">Add new interfacer</span>
      <input type="text" v-model="new_interfacer_name" />
      <button class="btn" @click="add_new">Add</button>
  </div>
</div>
<!--
<br><br>
<pre id="conf"></pre>
-->
<script> 
var conf = <?php echo !empty($conf) ? $conf: "{}"; ?>;

var EmonHubMqttInterfacer_count = 0;
var show_apply_configuration = {};

// 1. Find EmonHubOEMInterfacer
for (var interfacer_name in conf.interfacers) {
    if (conf.interfacers[interfacer_name].Type=='EmonHubMqttInterfacer') {
        console.log("Found EmonHubMqttInterfacer: "+interfacer_name);
        EmonHubMqttInterfacer_count++;
        show_apply_configuration[interfacer_name] = false;
    }
}

var template = {};

$.getJSON( path+"Modules/config/mqtt/template.json?v=2", function( result ) {
    template = result
    
    // If no EmonHubOEMInterfacer found, add a default copy from templates
    if (EmonHubMqttInterfacer_count==0) {
        if (conf.interfacers.MQTT == undefined) {
            conf.interfacers.MQTT = JSON.parse(JSON.stringify(template));
            show_apply_configuration.MQTT = true;
            console.log("EmonHubMqttInterfacer not found, applying default from template");
        } else {
            alert("Error: An interfacer called MQTT already exists but is not type EmonHubMqttInterfacer");
        }
    }
    
    var app = new Vue({
        el: '#app',
        data: {
            interfacers: conf.interfacers,
            show_apply_configuration: show_apply_configuration,
            new_interfacer_name: new_interfacer_name_suggestion()
        },
        methods: {
            update: function(interfacer_name) {
                this.show_apply_configuration[interfacer_name] = true;
            },
            apply: function(interfacer_name) {
                conf.interfacers = this.interfacers;      
                // Save config
                $.post( path+"config/save", { conf: JSON.stringify(conf) })
                    .done(function(r) {
                        app.show_apply_configuration[interfacer_name] = false;
                    })
                    .fail(function(xhr, status, error) {
                        alert("There was an error applying configuration")
                    });            
            },
            add_new: function() {
                
                if (conf.interfacers[this.new_interfacer_name] == undefined) {
                    conf.interfacers[this.new_interfacer_name] = JSON.parse(JSON.stringify(template));
                    Vue.set(this.interfacers,this.new_interfacer_name,JSON.parse(JSON.stringify(template)));
                    Vue.set(this.show_apply_configuration,this.new_interfacer_name,true);
                } else {
                    alert("Interfacer "+this.new_interfacer_name+" already exists");
                }
                this.new_interfacer_name = new_interfacer_name_suggestion();          
            }
        }
    });
});

function new_interfacer_name_suggestion() {
    EmonHubMqttInterfacer_count = 0;
    for (var interfacer_name in conf.interfacers) {
        if (conf.interfacers[interfacer_name].Type=='EmonHubMqttInterfacer') {
            EmonHubMqttInterfacer_count++;
        }
    }
    return "MQTT"+(EmonHubMqttInterfacer_count+1);
}
</script>
