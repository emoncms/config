<?php global $path; ?>

<style>
.section {
  border-top:1px solid #ccc;
  border-left:1px solid #ccc;
  border-right:1px solid #ccc;
  text-align:left;
}

.section-heading {
  background-color:#eee;
  padding:10px;
  text-align:left;
  cursor:pointer;
}

#conf table {
  width:100%;
  border-collapse: collapse;
  background-color:#fcfcfc;
  text-align:left;
  border: 1px solid #ccc;
}

#conf th {
  text-align:left;
  border: 1px solid #ccc;
  padding:10px;
}

#conf td {
  text-align:left;
  border: 1px solid #ccc;
  padding:10px;
}

.input-prepend { margin:0px }
select { margin:0px; width:300px; }

</style>

<div id="wrapper">
  <?php include "Modules/config/sidebar.php"; ?>

  <div style="height:20px"></div>

  <div id="conf">
    <h2>Calibration</h2>
    <p>Adjust calibration for nodes running unitless firmware.</p>
    <div class='section' v-for="(node,nodeid) in conf.nodes">
      <div class='section-heading' v-bind:name='nodeid'><b>{{ nodeid }}:{{ node.nodename }}</b></div>
      <div style="padding:5px">
        <table class='section-content' v-bind:name='nodeid'>
          <tr>
            <th>Name</th>
            <th></th>
            <th>Calibration</th>
            <th>Phase Shift</th>
            <th>Value</th>
          </tr>

          <tr v-for="(input,index) in node.rx.unitless" v-if="input=='v'">
            <td>Voltage calibration:</td>
            <td><select><option>ACAC Ideal Power</option></select></td>
            <td><div class="input-prepend input-append">
              <button class="btn">-</button>
              <input type="text" style="width:70px" v-model="node.rx.vcal" />
              <button class="btn">+</button>
            </div></td>
            <td></td>
            <td><div style="color:#00aa00">245V</div></td>
          </tr>
          
          <tr v-for="(input,index) in conf.nodes[nodeid].rx.unitless" v-if="input=='rp'">
            <td>{{ conf.nodes[nodeid].rx.names[index] }}</td>
            <td><select><option>SCT-013-000: 2000 turns, 22R burden</option></select></td>
            <td><div class="input-prepend input-append">
              <button class="btn">-</button>
              <input type="text" style="width:70px" v-model="node.rx.icals[index]" />
              <button class="btn">+</button>
            </div></td>
            <td><div class="input-prepend input-append">
              <button class="btn">-</button>
              <input type="text" style="width:70px" v-model="node.rx.phase_shifts[index]" />
              <button class="btn">+</button>
            </div></td>
            <td><div style="color:#00aa00">100W</div></td>
          </tr>       
        </table>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript" src="<?php echo $path; ?>Lib/misc/sidebar.js"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Lib/misc/sidebar.css">
<script src="<?php echo $path; ?>Modules/config/vue.js"></script>

<script>
init_sidebar({menu_element:"#config_menu"});

var path = "<?php echo $path; ?>";
var conf = <?php echo $conf; ?>;

var tmp = {};
for (var n in conf.nodes) {
    if (conf.nodes[n].rx.unitless!=undefined) {
        tmp[n] = conf.nodes[n];
    }
}
conf.nodes = tmp;
console.log(JSON.parse(JSON.stringify(conf)));

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
  console.log("change");
  $.ajax({ type: "POST", url: path+"config/setemonhub", data: "config="+JSON.stringify(appconf), async: false, success: function(data){ 
  // --- 
  }});
});

</script>
