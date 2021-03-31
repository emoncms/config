<?php global $path; $v=5; ?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="<?php echo $path; ?>Modules/config/nodes/format_time.js?v=<?php echo $v ?>"></script>
<script src="<?php echo $path; ?>Modules/config/nodes/decode.js?v=<?php echo $v ?>"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Modules/config/nodes/nodes.css?v=<?php echo $v ?>">

<h2 style="margin-top:20px">Configure RFM Nodes</h2>

<div id="app">
  <div v-for="(node,nodeid) in nodes" class="box">
    <div class="updated" style="color:#888">Last updated: <span :style="{color:node.time_color}">{{ node.time_value }}</span></div>
    <div><b>Node: {{ nodeid }}</b></div>
    <div class="rawdata"><b>Raw:</b> {{ node.data }}</div>
    
    <button class="btn" style="float:right">Remove configuration</button>
    <div class="input-append input-append" v-if="node.options.length>0">
      <span class="add-on">Select configuration from template</span>
      <select v-model="node.selected" @change="selectConfiguration(nodeid)">
        <option v-for="item in node.options">{{ item }}</option>
        <option v-if="node.selected=='custom'" value="custom">Custom</option>
      </select>
    </div>
      
    <table v-if="conf_nodes[nodeid]" class="table table-bordered" style="font-size:14px; margin-bottom:10px">
      <tr>
        <th>Name</th>
        <th>Datacode</th>
        <th>Scale</th>
        <th>Value</th>
        <th>Unit</th>
      </tr>
      <tr v-for="(item,index) in conf_nodes[nodeid].rx.names">
        <td width="20%"><input type="text" v-model="conf_nodes[nodeid].rx.names[index]" @change="update(nodeid)"></td>
        <td width="20%">
        <select v-model="conf_nodes[nodeid].rx.datacodes[index]" style="width:160px" @change="update(nodeid)">
          <option value='h'>h: Integer</option>
          <option value='l'>l: Long</option>
          <option value='L'>L: Unsigned Long</option>
        </select>
        </td>
        <td width="20%"><input type="text" v-model:value="conf_nodes[nodeid].rx.scales[index]" style="width:60px" @change="update(nodeid)"></td>
        <td width="20%">{{node.values[index]}}</td>
        <td width="20%"><input type="text" v-model:value="conf_nodes[nodeid].rx.units[index]" style="width:60px" @change="update(nodeid)"></td>
      </tr>
    </table>
    <div class="input-prepend input-append" v-if="node.bytes_available>=2">
      <span class="add-on" style="width:220px">{{ node.bytes_available }} bytes available</span>
      <select style="width:160px" v-model="add_datacode_select">
        <option v-if="node.bytes_available>=2" value='h'>h: Integer</option>
        <option v-if="node.bytes_available>=4" value='l'>l: Long</option>
        <option v-if="node.bytes_available>=4" value='L'>L: Unsigned Long</option>
      </select>      
      <button class="btn" @click="addDatacode(nodeid)">Add</button>
    </div>
    <button class="btn" @click="removeDatacode(nodeid)" v-if="conf_nodes[nodeid] && conf_nodes[nodeid].rx.datacodes.length>0"><i class="icon-trash"></i> Remove last</button>
    <button v-if="show_apply_configuration[nodeid]" class="btn btn-warning" style="float:right" @click="apply(nodeid)">Apply configuration</button>
    <div style="clear:both"></div>
  </div>
</div>

<script>var conf = <?php echo !empty($conf) ? $conf: "{}"; ?>;</script>
<script src="<?php echo $path; ?>Modules/config/nodes/nodes.js?v=<?php echo $v ?>"></script>
