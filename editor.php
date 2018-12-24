<?php
global $path;
?>

<link rel="stylesheet" type="text/css" href="style.css" />

<div id="wrapper">
  <div class="sidenav">
    <div class="sidenav-inner">
      <ul class="sidenav-menu">
          <li><a href="<?php echo $path; ?>config#log">View Log</a></li>
          <li><a href="<?php echo $path; ?>config#edit">Edit Config</a></li>
          <li><a href="<?php echo $path; ?>config/editor">EmonHub.Conf Editor</a></li>
      </ul>
    </div>
  </div>

  <div style="height:20px"></div>

  <div id="conf">
    <h2>Hub</h2>
    <div class='section'>
      <div class='section-heading' name="hub"><b>Hub</b></div>
      <table class='section-content' name="hub" style="display:none">
        <tr v-for="(value,setting) in conf.hub">
          <td style='width:50%'>{{ setting }}</td>
          <td><input v-model="conf.hub[setting]" type='text'/></td>
        </tr>
      </table>
    </div>
    
    <h2>Interfacers</h2>
    <div class='section' v-for="(interfacer,name) in conf.interfacers">
      <div class='section-heading' v-bind:name='name'><b>{{ name }}</b></div>
      <table class='section-content' v-bind:name='name' style="display:none">
        <tr><td><b>Init Settings:</b></td><td></td></tr>
        <tr v-for="(value,setting) in interfacer.init_settings">
          <td style='width:50%'>{{ setting }}</td>
          <td><input v-model="interfacer.init_settings[setting]" type='text'/></td>
        </tr>
        <tr><td><b>Run Time Settings:</b></td><td></td></tr>
        <tr v-for="(value,setting) in interfacer.runtimesettings">
          <td style='width:50%'>{{ setting }}</td>
          <td><input v-model="interfacer.runtimesettings[setting]" type='text'/></td>
        </tr>
      </table>
    </div>

    <h2>Nodes</h2>
    <div class='section' v-for="(node,nodeid) in conf.nodes">
      <div class='section-heading' v-bind:name='nodeid'><b>{{ nodeid }}:{{ node.nodename }}</b></div>
        <table class='section-content' v-bind:name='nodeid' style="display:none">
        <tr><td><b>RX:</b></td><td></td></tr>
        <tr v-for="(input,index) in node.rx">
          <td>{{ index }}</td>
          <td><input setting='name' type='text' v-model="input.name"/></td>
          <td><input setting='datacode' type='text' v-model="input.datacode"/></td>
          <td><input setting='scale' type='text' v-model="input.scale"/></td>
          <td><input setting='unit' type='text' v-model="input.unit"/></td>
        </tr>
        <tr><td><b>TX:</b></td><td></td></tr>
        <tr v-for="(input,index) in node.tx">
          <td>{{ index }}</td>
          <td><input setting='name' type='text' v-model="input.name"/></td>
          <td><input setting='datacode' type='text' v-model="input.datacode"/></td>
          <td><input setting='scale' type='text' v-model="input.scale"/></td>
          <td><input setting='unit' type='text' v-model="input.unit"/></td>
        </tr>
      </table>
    </div>
  </div>
</div>

<script type="text/javascript" src="<?php echo $path; ?>Lib/misc/sidebar.js"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Lib/misc/sidebar.css">
<link rel="stylesheet" href="<?php echo $path; ?>Modules/config/style.css">
<script src="<?php echo $path; ?>Modules/config/vue.js"></script>

<script>

var path = "<?php echo $path; ?>";

init_sidebar({menu_element:"#config_menu"});

var conf = <?php echo $conf; ?>;
conf.nodes = nodes_hr(conf.nodes);

var app = new Vue({
  el: '#conf',
  data: { conf: conf }
});

$("#conf").on('click',".section-heading",function(){
   var name = $(this).attr("name");
   $(".section-content[name='"+name+"']").toggle(); 
});

$("#conf").on("change","input",function() {
    var appconf = JSON.parse(JSON.stringify(app.$data.conf));
    appconf.nodes = nodes_vt(appconf.nodes);
    console.log("change");
    $.ajax({ type: "POST", url: path+"config/setemonhub", data: "config="+JSON.stringify(appconf), async: false, success: function(data){ 
    // --- 
    }});
});

function nodes_hr(vt) {
    var hr = {};
    for (var z in vt) {
        hr[z] = {rx:[],tx:[],nodename:vt[z].nodename}
        for (var x in {rx:0,tx:0}) {
            if (vt[z][x]!=undefined) {
                for (var i=0; i<100; i++) {
                    var input = { name:"", datacode:"h", scale:1, unit:""};
                    var present = false;
                    
                    if (vt[z][x].names!=undefined && i<vt[z][x].names.length) { 
                        input.name = vt[z][x].names[i]; present = true;
                    }
                    
                    if (vt[z][x].datacode!=undefined) input.datacode = vt[z][x].datacode;
                    if (vt[z][x].datacodes!=undefined && i<vt[z][x].datacodes.length) { 
                        input.datacode = vt[z][x].datacodes[i]; present = true;
                    }
                    
                    if (vt[z][x].scale!=undefined) input.scale = vt[z][x].scale;
                    if (vt[z][x].scales!=undefined && i<vt[z][x].scales.length) {
                        input.scale = vt[z][x].scales[i]; present = true;
                    }
                    
                    if (vt[z][x].unit!=undefined) input.unit = vt[z][x].unit;
                    if (vt[z][x].units!=undefined && i<vt[z][x].units.length) {
                        input.unit = vt[z][x].units[i]; present = true;
                    }
                                
                    if (!present) break;
                    hr[z][x].push(input);
                }
            }
        }
    }
    return hr;
}

function nodes_vt(hr) {
    var vt = {};
    for (var z in hr) {
        vt[z] = {nodename:hr[z].nodename,rx:{},tx:{}}
        for (var x in {rx:0,tx:0}) {
            if (hr[z][x]!=undefined) {
                var names = [];
                var datacodes = [];
                var scales = [];
                var units = [];
            
                for (var i in hr[z][x]) {
                    names.push(hr[z][x][i].name);
                    datacodes.push(hr[z][x][i].datacode);
                    scales.push(hr[z][x][i].scale);
                    units.push(hr[z][x][i].unit);
                }
                
                if (names.length>0) vt[z][x].names = names;
                if (datacodes.length>0) vt[z][x].datacodes = datacodes;
                if (scales.length>0) vt[z][x].scales = scales;
                if (units.length>0) vt[z][x].units = units;
            }
        }
    }
    return vt;
}
</script>
