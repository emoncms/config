<?php global $path; $v=1; ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<style>
.content-container {
  max-width:1150px;
}
input[type=text] {
  width:100px;
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

.box:last-child {
  border-bottom: 1px solid #ccc;
}
</style>

<h3>OpenEnergyMonitor Hardware Interfacer</h3>

<p>Configure OpenEnergyMonitor hardware connected via serial or a USB uart interface.</p>

<div id="app">
  <div>
  <div class="box" v-for="interfacer,interfacer_name in interfacers" v-if="interfacer.Type=='EmonHubOEMInterfacer'">
    <h4>{{ interfacer_name }}</h4>

    <div class="input-prepend">
      <span class="add-on" style="width:150px">Connected Hardware</span>
      <select v-model="interfacers[interfacer_name].Template" @change="change_template(interfacer_name)">
        <option>Custom</option>
        <option v-for="name in template_names">{{name}}</option>
      </select>
    </div>
    <h4>Serial port settings</h4>

    <div class="input-prepend">
      <span class="add-on">Port</span>
      <select v-model="interfacers[interfacer_name].init_settings.com_port" @change="update(interfacer_name)">
        <option>/dev/ttyAMA0</option>
        <option>/dev/ttyUSB0</option>
      </select>
    </div>

    <div class="input-prepend">
      <span class="add-on">Baud rate</span>
      <select v-model="interfacers[interfacer_name].init_settings.com_baud" @change="update(interfacer_name)">
        <option>9600</option>
        <option>38400</option>
        <option>115200</option>
      </select>
    </div>

    <div v-if="interfacers[interfacer_name].runtimesettings.frequency">
      <h4>RFM Radio settings</h4>

      <div class="input-prepend">
        <span class="add-on">Frequency</span>
        <select v-model="interfacers[interfacer_name].runtimesettings.frequency" @change="update(interfacer_name)">
          <option value="4">433 MHz</option>
          <option value="8">868 MHz</option>
          <option value="9">915 MHz</option>
        </select>
      </div>

      <div class="input-prepend">
        <span class="add-on">Group</span>
        <input type="text" v-model:value="interfacers[interfacer_name].runtimesettings.group" @change="update(interfacer_name)" />
      </div>

      <div class="input-prepend">
        <span class="add-on">Base ID</span>
        <input type="text" v-model:value="interfacers[interfacer_name].runtimesettings.baseid" @change="update(interfacer_name)" />
      </div>
    </div>
    
    <div v-if="interfacers[interfacer_name].runtimesettings.vcal || interfacers[interfacer_name].runtimesettings.icalA">
      <h4>Sensor calibration</h4>

      <div class="input-prepend" v-if="interfacers[interfacer_name].runtimesettings.vcal">
        <span class="add-on">Voltage sensor</span>
        <input type="text" v-model:value="interfacers[interfacer_name].runtimesettings.vcal" @change="update(interfacer_name)" />
      </div>

      <table class="table" v-if="interfacers[interfacer_name].runtimesettings.icalA">
        <tr>
          <th>CT</th>
          <th>Amplitude calibration</th>
          <th>Phase shift</th>
        </tr>
        <tr v-for="(item,index) in interfacers[interfacer_name].runtimesettings.icalA">
          <td>{{index+1}}</td>
          <td><input type="text" v-model:value="interfacers[interfacer_name].runtimesettings.icalA[index]" @change="update(interfacer_name)" /></td>
          <td><input type="text" v-model:value="interfacers[interfacer_name].runtimesettings.icalP[index]" @change="update(interfacer_name)" /></td>
        </tr>
      </table>
    </div>
    <button v-if="show_apply_configuration[interfacer_name]" class="btn btn-warning" @click="apply(interfacer_name)">Apply configuration</button>
  </div> <!-- end box -->
  </div>
  <br>
  <div class="input-prepend input-append">
      <span class="add-on">Add new interfacer</span>
      <input type="text" v-model="new_interfacer_name" />
      <button class="btn" @click="add_new">Add</button>
  </div>
</div> <!-- end app -->





<script> 
var conf = <?php echo !empty($conf) ? $conf: "{}"; ?>;
var devicetype = "EmonPi";
var templates = {};
var app = false;

var EmonHubOEMInterfacer_count = 0;
var selected_template = {};
var show_apply_configuration = {};

// 1. Find EmonHubOEMInterfacer
for (var interfacer_name in conf.interfacers) {
    if (conf.interfacers[interfacer_name].Type=='EmonHubOEMInterfacer') {
        console.log("Found EmonHubOEMInterfacer: "+interfacer_name);
        EmonHubOEMInterfacer_count++;
    }
}

$.getJSON( path+"Modules/config/OEM/templates.json?v=1", function( result ) {
    templates = result
    
    // If no EmonHubOEMInterfacer found, add a default copy from templates
    if (EmonHubOEMInterfacer_count==0) {
        if (conf.interfacers.Emon == undefined) {
            conf.interfacers.Emon = JSON.parse(JSON.stringify(templates["EmonPi"]));
            selected_template.Emon = "EmonPi";
            console.log("EmonHubOEMInterfacer not found, applying default from template");
        } else {
            alert("Error: An interfacer called Emon already exists but is not type EmonHubOEMInterfacer");
        }
    }
    
    // 2. Check if template has been set
    for (var interfacer_name in conf.interfacers) {
        if (conf.interfacers[interfacer_name].Type=='EmonHubOEMInterfacer') {
            if (conf.interfacers[interfacer_name].Template==undefined) {
                conf.interfacers[interfacer_name].Template = 'Custom';
            }
        }
    }

    $.getJSON( path+"config/runtimeinfo", function( result ) {
        // 3. Detect hardware/firmware type from runtimeinfo
        for (var interfacer_name in conf.interfacers) {
            if (conf.interfacers[interfacer_name].Type=='EmonHubOEMInterfacer') {
                if (result[interfacer_name]!=undefined && result[interfacer_name].firmware_name!=undefined) {
                    console.log("Found firmware name: "+result[interfacer_name].firmware_name)
                    // if (result[interfacer_name].firmware_name=="RFM69Pi_n") {
                    //     devicetype = "RFM69Pi"
                    // }
                }
            }
        }
        load_app();
    });
});

function load_app() {

    for (var interfacer_name in conf.interfacers) {
        if (conf.interfacers[interfacer_name].Type=='EmonHubOEMInterfacer') {
            show_apply_configuration[interfacer_name] = false;
        }
    }

    app = new Vue({
        el: '#app',
        data: {
            interfacers: conf.interfacers,
            template_names: Object.keys(templates),
            show_apply_configuration: show_apply_configuration,
            new_interfacer_name: new_interfacer_name_suggestion()
        }, 
        methods: {
            change_template: function(interfacer_name) {
                var template_name = conf.interfacers[interfacer_name].Template;
                if (templates[template_name]!=undefined) {
                    console.log("Interfacer "+interfacer_name+" template changed to: "+template_name);
                    var interfacer_copy = JSON.parse(JSON.stringify(conf.interfacers[interfacer_name]));
                    conf.interfacers[interfacer_name] = JSON.parse(JSON.stringify(templates[template_name]));
                    
                    // Apply old serial settings
                    if (interfacer_copy.init_settings!=undefined) {
                        for (var z in conf.interfacers[interfacer_name].init_settings) {
                            if (interfacer_copy.init_settings[z]!=undefined) {
                                conf.interfacers[interfacer_name].init_settings[z] = interfacer_copy.init_settings[z];
                            }
                        }
                    }
                    this.interfacers = conf.interfacers;
                }
                this.show_apply_configuration[interfacer_name] = true;
            },
            update: function(interfacer_name) {
                this.show_apply_configuration[interfacer_name] = true;
            },
            apply: function(interfacer_name) {
                conf.interfacers = this.interfacers;
                // Save config
                $.post( path+"config/save", { conf: JSON.stringify(conf) })
                    .done(function(r) {
                        console.log("config/save "+r);
                        alert(r);
                        app.show_apply_configuration[interfacer_name] = false;
                    })
                    .fail(function(xhr, status, error) {
                        alert("There was an error applying configuration")
                    });            
            },
            add_new: function() {
                
                if (conf.interfacers[this.new_interfacer_name] == undefined) {
                    conf.interfacers[this.new_interfacer_name] = JSON.parse(JSON.stringify(templates["EmonPi"]));
                    this.interfacers = conf.interfacers;
                    Vue.set(this.show_apply_configuration,this.new_interfacer_name,true)
                } else {
                    alert("Interfacer "+this.new_interfacer_name+" already exists");
                }
                this.new_interfacer_name = new_interfacer_name_suggestion();          
            }
        }
    });
}

function new_interfacer_name_suggestion() {
    EmonHubOEMInterfacer_count = 0;
    for (var interfacer_name in conf.interfacers) {
        if (conf.interfacers[interfacer_name].Type=='EmonHubOEMInterfacer') {
            EmonHubOEMInterfacer_count++;
        }
    }
    return "Emon"+(EmonHubOEMInterfacer_count+1);
}


</script>
