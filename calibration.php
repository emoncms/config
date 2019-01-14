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

.fade-enter-active, .fade-leave-active {
  transition: all .5s;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}
.fade-enter-to, .fade-leave {
  opacity: 1;
}
#status{
    position: fixed;
    top: 0;
    left: 0;
    height: 2.5rem;
    overflow: hidden;
    z-index: 1030;
    width: 100%;
    text-align: center;
    color: white;
    line-height: 2.5rem;
}
#status.fade-enter-to, #status.fade-leave {
    height: 2.5rem;
}
#status.fade-enter, #status.fade-to {
    height: 0;
}
</style>

<div id="wrapper">
  <?php include "Modules/config/sidebar.php"; ?>

  <div id="conf">
    <transition name="fade">
        <div id="status" v-if="status!=''"><strong>{{status}}</strong></div>
    </transition>
    <h2 style="padding-top:2.5rem;margin-top:0">Calibration</h2>
    <p>Adjust calibration for nodes running unitless firmware.</p>
    <div class='section' v-for="(node,nodeid) in conf.nodes">
      <div class='section-heading' :data-name='nodeid' @click="toggle">
      <strong>{{ nodeid }}:{{ node.nodename }}</strong>
      </div>
      <div style="padding:5px">
        <table class='section-content' :data-name='nodeid'>
          <tr>
            <th>Name</th>
            <th></th>
            <th>Calibration</th>
            <th>Phase Shift</th>
            <th>Value</th>
            <th>Time</th>
          </tr>
          <tr v-for="(input,index) in node.rx.unitless" v-if="input=='v'">
            <td>Voltage calibration:</td>
            <td>
            <select :id="['input',node.nodename,index].join('_')" 
              @change="passOnSelection_vcal($event, node)">
              <option v-for="device in preset_vcals" :value="device.vcal">
                {{device.name}}
              </option>
            </select>
            <td><div class="input-prepend input-append">
              <button class="btn" 
                @mousedown="waitForLongPress_vcal(node, false)" 
                @mouseup="stopLongPress" 
                @mouseleave="stopLongPress">-</button>
              <input class="span4" v-model="node.rx.vcal" type="text">
              <button class="btn" 
                @mousedown="waitForLongPress_vcal(node, true)" 
                @mouseup="stopLongPress" 
                @mouseleave="stopLongPress">+</button>
            </div></td>
            <td></td>
            <td><span v-if="typeof live[node.nodename]!=='undefined'" v-html="list_format_value(live[node.nodename][node.rx.names[index]].value)"></span>{{ node.rx.units[index] }}</td>
            <td><span v-if="typeof live[node.nodename]!=='undefined'" v-html="list_format_updated(live[node.nodename][node.rx.names[index]].time)"></span></td>
          </tr>
          <tr v-for="(input,index) in node.rx.unitless" v-if="input=='rp'">
            <td>{{ node.rx.names[index] }}</td>
            <td>
            <select :id="['input',node.nodename,index].join('_')" 
              @change="passOnSelection_ical($event, node, index)">
              <option v-for="device in preset_icals" :value="device.ical">{{device.name}}</option>
            </select>
            <td>
              <div class="input-prepend input-append">
                <button class="btn" type="button"
                    @mousedown="waitForLongPress_ical(node, false, index)" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">-</button>
                <input class="span4" type="text"  v-model="node.rx.icals[index]">
                <button class="btn" type="button"
                    @mousedown="waitForLongPress_ical(node, true, index)" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">+</button>
              </div>
            </td>
            <td><div class="input-prepend input-append">
            <button class="btn" type="button"
                    @mousedown="waitForLongPress_phase(node, false, index)" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">-</button>
              <input type="text" class="span4" v-model="node.rx.phase_shifts[index]">
              <button class="btn" type="button"
                    @mousedown="waitForLongPress_phase(node, true, index)" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">+</button>
            </div></td>
            <td><span v-if="typeof live[node.nodename]!=='undefined'" v-html="list_format_value(live[node.nodename][node.rx.names[index]].value)"></span>{{ node.rx.units[index] }}</td>
            <td><span v-if="typeof live[node.nodename]!=='undefined'" v-html="list_format_updated(live[node.nodename][node.rx.names[index]].time)"></span></td>
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
var conf = <?php echo !empty($conf) ? $conf: '{}'; ?>;

var tmp = {};
for (var n in conf.nodes) {
    if (conf.nodes[n].rx.unitless!=undefined) {
        tmp[n] = conf.nodes[n];
    }
}
conf.nodes = tmp;
// console.log(JSON.parse(JSON.stringify(conf)));

var app = new Vue({
  el: '#conf',
  data: { 
    conf: conf,
    live: "hello",
    step: .1,
    pressTimer: null,
    holdTimer: null,
    save_timeout: void 0,
    status: '', 
    preset_vcals: [
      {id: 'custom', name: 'Custom'},
      {id: '77DB-06-09-UK', name: 'Ideal Power 77DB-06-09 (UK Plug type)', vcal: 268.97},
      {id: '77DB-06-09-EU', name: 'Ideal Power 77DE-06-09 (EURO Plug type)', vcal: 260.0},
      {id: '77DB-06-09-US', name: 'Ideal Power 77DA-10-09 (US Plug type)', vcal: 130.0}
    ],
    preset_icals: [
      {id: 'custom', name: 'Custom'},
      {id: 'SCT-013-000-22R',  ical: 90.1, name: 'SCT-013-000: 2000 turns, 22R burden'},
      {id: 'SCT-013-000-100R', ical: 90.3, name: 'SCT-013-000: 2000 turns, 100R burden'},
    ]
  },
  filters: {
    dp2: function(value) {
      return value.toFixed(2);
    }
  },
  methods: {
    list_format_updated: function(value) {
      return list_format_updated(value)
    },
    list_format_value: function(value) {
      return list_format_value(value)
    },
    increase: function(input) {
      this.step_vcal(input, true);
    },
    decrease: function(input) {
      this.step_vcal(input, false);
    },
    set_vcal: function(node, value) {
      if(node.rx.vcal) {
        node.rx.vcal = Number(value).toFixed(2);
        this.check_vcal_select(node);
      }
    },
    set_ical: function(node, value, index) {
      // icals not vuejs reactive ? not sure why - fixed by assiging a new array
        var icals = node.rx.icals;
        icals[index] = Number(value).toFixed(2);
        node.rx.icals = Object.assign([], node.rx.icals, icals);
        // check dropdown for matching value.
        this.check_ical_select(node, index);
    },
    set_phase: function(node, value, index) {
        var phase_shifts = node.rx.phase_shifts;
        phase_shifts[index] = Number(value).toFixed(2);
        node.rx.phase_shifts = Object.assign([], node.rx.phase_shifts, phase_shifts);
    },
    step_vcal: function(node, isIncrement) {
      var _step = Math.abs(this.step);
      var step = isIncrement ? _step: 0 - _step;
      var offset = parseFloat(node.rx.vcal);
      this.set_vcal(node, offset + step);
    },
    step_ical: function(node, isIncrement, index) {
      var _step = Math.abs(this.step);
      var step = isIncrement ? _step: 0 - _step;
      var value = parseFloat(node.rx.icals[index]);
      this.set_ical(node, value + step, index);
    },
    step_phase: function(node, isIncrement, index) {
      var _step = Math.abs(this.step);
      var step = isIncrement ? _step: 0 - _step;
      var value = parseFloat(node.rx.phase_shifts[index]);
      this.set_phase(node, value + step, index);
    },
    waitForLongPress_vcal: function(node, isIncrement){
      var vm = this;
      this.step_vcal(node, isIncrement);
      this.pressTimer = setTimeout(function(){
        vm.startLongPress_vcal(node, isIncrement);
      }, 600);
    },
    startLongPress_vcal: function(node, isIncrement, index){
      var vm = this;
      this.holdTimer = setInterval(function(){
        vm.step_vcal(node, isIncrement, index)
      }, 50);
    },
    waitForLongPress_ical: function(node, isIncrement, index){
      var vm = this;
      this.step_ical(node, isIncrement, index);
      this.pressTimer = setTimeout(function(){
        vm.startLongPress_ical(node, isIncrement, index);
      }, 600);
    },
    startLongPress_ical: function(node, isIncrement, index){
      var vm = this;
      this.holdTimer = setInterval(function(){
        vm.step_ical(node, isIncrement, index);
      }, 50);
    },
    waitForLongPress_phase: function(node, isIncrement, index){
      var vm = this;
      this.step_phase(node, isIncrement, index);
      this.pressTimer = setTimeout(function(){
        vm.startLongPress_phase(node, isIncrement, index);
      }, 600);
    },
    startLongPress_phase: function(node, isIncrement, index){
      var vm = this;
      this.holdTimer = setInterval(function(){
        vm.step_phase(node, isIncrement, index);
      }, 50);
    },
    stopLongPress: function(){
      clearTimeout(this.pressTimer);
      clearInterval(this.holdTimer);
      this.pressTimer = null;
      this.holdTimer = null;
    },
    checkAllDropdowns: function(){
      for (i in this.conf.nodes) {
        var node = this.conf.nodes[i];
        this.check_vcal_select(node);
        for (var j = 0; j < node.rx.icals.length; j++) {
            this.check_ical_select(node, j);
        }
      }
    },
    check_vcal_select: function(node) {
      var index = node.rx.unitless.indexOf('v');
      var select = document.querySelector('#' + ['input', node.nodename, index].join('_'));
      if (select) {
        var val = node.rx.vcal ? Number(node.rx.vcal).toFixed(2): 0;
        for(i = 0; i < select.options.length; i++){
            let option = select.options[i];
            let option_value = Number(option.value).toFixed(2);
            if (option_value == val) {
                select.selectedIndex = i;
                break;
            } else {
                select.selectedIndex = 0;
            }
        }
      }
    },
    check_ical_select: function(node, index) {
      var select = document.querySelector('#'+['input',node.nodename, index].join('_'));
      var icals = node.rx.icals;
      if (select) {
        for(var i = 0; i < select.options.length; i++){
            var val = icals[index] ? Number(icals[index]).toFixed(2): 0;
            var option = select.options[i];
            var option_value = Number(option.value).toFixed(2);
            if (option_value == val) {
                select.selectedIndex = i;
                break;
            } else {
                select.selectedIndex = 0;
            }
        }
      }
    },
    passOnSelection_vcal: function(event, node) {
      var select = event.target;
      var value = select.options[select.selectedIndex].value
      this.set_vcal(node, value)
    },
    passOnSelection_ical: function(event, node, index) {
      var select = event.target;
      var value = select.options[select.selectedIndex].value
      this.set_ical(node, value, index)
    },
    debounced_save: function(data) {
        var wait = 700;
        var vm = this;
        clearTimeout(this.timeout);
        this.timeout = setTimeout(function () {
            vm.save(data);
         }, wait);
    },
    save: function(data){
        var vm = this;
        $.post(path+"config/setemonhub", {config: JSON.stringify(data)}, function(response){ 
            if(response==='ok') {
                // all good - data saved
                vm.updateStatus('Changes Saved');
            }
        });
    },
    updateStatus: function(status){
        this.status = 'Changes Saved';
        var vm = this;
        setTimeout(function () {
            vm.status = ''
        }, 3000)
    },
    toggle: function(event) {
        let section = document.querySelector('.section-content[data-name="'+event.target.dataset.name+'"]')
        $(section).toggle();
    },
    list_format_updated: function(time) {
        time = time * 1000;
        var servertime = new Date().getTime(); // - table.timeServerLocalOffset;
        var update = new Date(time).getTime();
        
        var secs = (servertime - update) / 1000;
        var mins = secs / 60;
        var hour = secs / 3600;
        var day = hour / 24;
        
        var updated = secs.toFixed(0) + "s";
        if (update == 0 || !$.isNumeric(secs)) updated = "n/a";
        else if (secs < 0) updated = secs.toFixed(0) + "s";
        // update time ahead of server date is signal of slow network
        else if (secs.toFixed(0) == 0) updated = "now";
        else if (day > 7) updated = "inactive";
        else if (day > 2) updated = day.toFixed(1) + " days";
        else if (hour > 2) updated = hour.toFixed(0) + " hrs";
        else if (secs > 180) updated = mins.toFixed(0) + " mins";
        
        secs = Math.abs(secs);
        var color = "rgb(255,0,0)";
        if (secs < 25) color = "rgb(50,200,50)";
        else if (secs < 60) color = "rgb(240,180,20)";
        else if (secs < 3600 * 2) color = "rgb(255,125,20)";
        
        return "<span style='color:" + color + ";'>" + updated + "</span>";
    },
    list_format_value: function(value) {
        if (value == null) return "NULL";
        value = parseFloat(value);
        if (value >= 1000) value = parseFloat(value.toFixed(0));
        else if (value >= 100) value = parseFloat(value.toFixed(1));
        else if (value >= 10) value = parseFloat(value.toFixed(2));
        else if (value <= -1000) value = parseFloat(value.toFixed(0));
        else if (value <= -100) value = parseFloat(value.toFixed(1));
        else if (value < 10) value = parseFloat(value.toFixed(2));
        return value;
    }

  },
  mounted: function(){
    this.checkAllDropdowns();
    update();
    setInterval(update,5000);
    function update(){
        $.ajax({ type: "GET", url: path+"input/get", success: function(result){ 
            app.live = result;
        }});
    }
  },
  watch: {
      // watch for changes in the conf obj
      conf: {
          handler: function(newVal, oldVal){
            this.debounced_save(newVal);
          },
          deep: true
      }
  }
});

</script>
