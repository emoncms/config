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
  <div class="box">
    <h4>EmonPi</h4>
    
    <div class="input-prepend">
      <span class="add-on" style="width:150px">Custom name</span>
      <input type="text" value="EmonPi" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on" style="width:150px">Connected Hardware</span>
      <select v-model="devicetype">
        <option v-for="name in template_names">{{name}}</option>
      </select>
    </div>
    <h4>Serial port settings</h4>

    <div class="input-prepend">
      <span class="add-on">Port</span>
      <select v-model="templates[devicetype].init_settings.com_port" @change="update">
        <option>/dev/ttyAMA0</option>
      </select>
    </div>

    <div class="input-prepend">
      <span class="add-on">Baud rate</span>
      <select v-model="templates[devicetype].init_settings.com_baud" @change="update">
        <option>9600</option>
        <option>38400</option>
        <option>115200</option>
      </select>
    </div>

    <div v-if="templates[devicetype].runtimesettings.frequency">
      <h4>RFM Radio settings</h4>

      <div class="input-prepend">
        <span class="add-on">Frequency</span>
        <select v-model="templates[devicetype].runtimesettings.frequency" @change="update">
          <option value="4">433 MHz</option>
          <option value="8">868 MHz</option>
          <option value="9">915 MHz</option>
        </select>
      </div>

      <div class="input-prepend">
        <span class="add-on">Group</span>
        <input type="text" v-model:value="templates[devicetype].runtimesettings.group" @change="update" />
      </div>

      <div class="input-prepend">
        <span class="add-on">Base ID</span>
        <input type="text" v-model:value="templates[devicetype].runtimesettings.baseid" @change="update" />
      </div>
    </div>
    
    <div v-if="templates[devicetype].runtimesettings.vcal || templates[devicetype].runtimesettings.icalA">
      <h4>Sensor calibration</h4>

      <div class="input-prepend" v-if="templates[devicetype].runtimesettings.vcal">
        <span class="add-on">Voltage sensor</span>
        <input type="text" v-model:value="templates[devicetype].runtimesettings.vcal" @change="update" />
      </div>

      <table class="table" v-if="templates[devicetype].runtimesettings.icalA">
        <tr>
          <th>CT</th>
          <th>Amplitude calibration</th>
          <th>Phase shift</th>
        </tr>
        <tr v-for="(item,index) in templates[devicetype].runtimesettings.icalA">
          <td>{{index+1}}</td>
          <td><input type="text" v-model:value="templates[devicetype].runtimesettings.icalA[index]" @change="update" /></td>
          <td><input type="text" v-model:value="templates[devicetype].runtimesettings.icalP[index]" @change="update" /></td>
        </tr>
      </table>
    </div>
    <button v-if="show_apply_configuration" class="btn btn-warning" @click="apply()">Apply configuration</button>
  </div> <!-- end box -->
</div> <!-- end app -->

<!--
<br><br>
<pre id="conf"></pre>
-->

<script> 
var conf = <?php echo !empty($conf) ? $conf: "{}"; ?>;
// $("#conf").html(JSON.stringify(conf.interfacers.EmonPi, null, 2))

var templates = {};

$.getJSON( path+"Modules/config/OEM/templates.json?v=1", function( result ) {
    templates = result
    // $("#conf").html(JSON.stringify(templates, null, 2))
    
    var app = new Vue({
        el: '#app',
        data: {
            template_names: Object.keys(templates),
            templates: templates,
            devicetype: "EmonPi",
            show_apply_configuration: false
        }, 
        methods: {
            update: function() {
                if (JSON.stringify(this.templates["EmonPi"])!=JSON.stringify(conf.interfacers.EmonPi)) {
                    // Vue.set(this,show_apply_configuration,true)
                    this.show_apply_configuration = true;
                }
            },
            apply: function() {
                conf.interfacers.EmonPi = JSON.parse(JSON.stringify(this.templates["EmonPi"]));           
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
    
    if (JSON.stringify(app.templates["EmonPi"])!=JSON.stringify(conf.interfacers.EmonPi)) {
        app.show_apply_configuration = true;
    }
});


</script>
