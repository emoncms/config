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
  <div class="box">
    <h4>Emoncms.org</h4>

    <p><b>Runtime settings</b></p>

    <div class="input-prepend">
      <span class="add-on">Emoncms Host URL</span>
      <input type="text" v-model="http_template.runtimesettings.url" @change="update" />
    </div><br>
    
    <p style="font-size:14px">Enter username, password and click connect to fetch emoncms account apikey</p>

    <div class="input-prepend">
      <span class="add-on">Username</span>
      <input type="text" v-model="emoncms_username" @change="update" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Password</span>
      <input type="text" v-model="emoncms_password" @change="update" />
    </div><br>
    
    <button class="btn btn-warning" @click="connect()">Connect</button><br><br>

    <div class="input-prepend">
      <span class="add-on">Write API key</span>
      <input type="text" v-model="http_template.runtimesettings.apikey" @change="update" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Send data</span>
      <input type="text" v-model="http_template.runtimesettings.senddata" @change="update" />
    </div><br>
    
    <div class="input-prepend">
      <span class="add-on">Send status</span>
      <input type="text" v-model="http_template.runtimesettings.sendstatus" @change="update" />
    </div><br>

    <div class="input-prepend">
      <span class="add-on">Send interval (seconds)</span>
      <input type="text" v-model="http_template.runtimesettings.sendinterval" @change="update" />
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
// $("#conf").html(JSON.stringify(conf.interfacers.emoncmsorg, null, 2));

var http_template = {};

$.getJSON( path+"Modules/config/http/template.json?v=1", function( result ) {
    http_template = result
    $("#conf").html(JSON.stringify(http_template, null, 2));
    
    var app = new Vue({
        el: '#app',
        data: {
            http_template: http_template,
            show_apply_configuration: false,
            emoncms_username: "",
            emoncms_password: ""
        },
        methods: {
            update: function() {
                if (JSON.stringify(this.http_template)!=JSON.stringify(conf.interfacers.emoncmsorg)) {
                    this.show_apply_configuration = true;
                }
            },
            apply: function() {
                conf.interfacers.emoncmsorg = JSON.parse(JSON.stringify(this.http_template));           
                // Save config
                $.post( path+"config/save", { conf: JSON.stringify(conf) })
                    .done(function(r) {
                        app.show_apply_configuration = false
                    })
                    .fail(function(xhr, status, error) {
                        alert("There was an error applying configuration")
                    });            
            },
            connect: function() {
                $.ajax({ type: "POST", url: path+"config/remoteauth", data: "host="+app.http_template.runtimesettings.url+"&username="+app.emoncms_username+"&password="+app.emoncms_username, async: true, success: function(result){ 
                    if (result.success!=undefined && result.success) {
                        alert(result.apikey_write);
                        app.http_template.runtimesettings.apikey = result.apikey_write
                    } else {
                        alert("Emoncms account does not exist");
                    }
                }});  
            }
        }
    });
    
    if (JSON.stringify(app.http_template)!=JSON.stringify(conf.interfacers.emoncmsorg)) {
        app.show_apply_configuration = true;
    }
});
</script>
