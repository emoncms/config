<?php global $path; $v=1; ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<style>
.content-container {
  max-width:1150px;
}
input[type=text] {
  width:180px;
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

<h3>HTTP Connect</h3>

<p>Configure emonhub to send data to remote emoncms account (e.g emoncms.org)<p>

<div id="app">
  <div>
  <div class="box" v-for="interfacer,interfacer_name in interfacers" v-if="interfacer.Type=='EmonHubEmoncmsHTTPInterfacer'">
    <h4>{{ interfacer_name }}</h4>

    <p><b>Runtime settings</b></p>

    <div class="input-prepend">
      <span class="add-on">Emoncms Host URL</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.url" @change="update(interfacer_name)" />
    </div><br>
    
    <p style="font-size:14px">Enter username, password and click connect to fetch emoncms account apikey</p>

    <div class="input-prepend">
      <span class="add-on">Username</span>
      <input type="text" v-model="emoncms_username" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Password</span>
      <input type="text" v-model="emoncms_password" />
    </div><br>
    
    <button class="btn btn-primary" @click="connect(interfacer_name)">Fetch API Key</button><br><br>

    <div class="input-prepend">
      <span class="add-on">Write API key</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.apikey" @change="update(interfacer_name)" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Send data</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.senddata" @change="update(interfacer_name)" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Send status</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.sendstatus" @change="update(interfacer_name)" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Send interval (seconds)</span>
      <input type="text" v-model="interfacers[interfacer_name].runtimesettings.sendinterval" @change="update(interfacer_name)" />
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

var EmonHubEmoncmsHTTPInterfacer_count = 0;
var show_apply_configuration = {};

// 1. Find EmonHubOEMInterfacer
for (var interfacer_name in conf.interfacers) {
    if (conf.interfacers[interfacer_name].Type=='EmonHubEmoncmsHTTPInterfacer') {
        console.log("Found EmonHubEmoncmsHTTPInterfacer: "+interfacer_name);
        EmonHubEmoncmsHTTPInterfacer_count++;
        show_apply_configuration[interfacer_name] = false;
    }
}

var template = {};

$.getJSON( path+"Modules/config/http/template.json?v=2", function( result ) {
    template = result

    // If no EmonHubOEMInterfacer found, add a default copy from templates
    if (EmonHubEmoncmsHTTPInterfacer_count==0) {
        if (conf.interfacers.Emoncms == undefined) {
            conf.interfacers.Emoncms = JSON.parse(JSON.stringify(template));
            show_apply_configuration.Emoncms = false;
            console.log("EmonHubEmoncmsHTTPInterfacer not found, applying default from template");
        } else {
            alert("Error: An interfacer called Emoncms already exists but is not type EmonHubEmoncmsHTTPInterfacer");
        }
    }
    
    var app = new Vue({
        el: '#app',
        data: {
            interfacers: conf.interfacers,
            show_apply_configuration: show_apply_configuration,
            emoncms_username: "",
            emoncms_password: "",
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
            connect: function(interfacer_name) {
                $.post( path+"config/remoteauth", { 
                        host: this.interfacers[interfacer_name].runtimesettings.url,
                        username: app.emoncms_username,
                        password: app.emoncms_password
                    })
                    .done(function(result) {
                        if (result.success!=undefined) {
                            if (result.success) {
                                alert("Authentication successful, API key copied");
                                app.interfacers[interfacer_name].runtimesettings.apikey = result.apikey_write
                                Vue.set(app.show_apply_configuration,interfacer_name,true);
                            } else {
                                console.log("here");
                                alert(result.message);
                            }
                        } else {
                            alert(result);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        alert("Authentication error")
                        console.log(status)
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
    EmonHubEmoncmsHTTPInterfacer_count = 0;
    for (var interfacer_name in conf.interfacers) {
        if (conf.interfacers[interfacer_name].Type=='EmonHubEmoncmsHTTPInterfacer') {
            EmonHubEmoncmsHTTPInterfacer_count++;
        }
    }
    return "Emoncms"+(EmonHubEmoncmsHTTPInterfacer_count+1);
}
</script>
