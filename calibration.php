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
<?php if(!empty($tabs)) echo $tabs ?>

  <div id="conf">
    <transition name="fade">
        <div id="status" v-if="status!=''"><strong>{{status}}</strong></div>
    </transition>
    <h2>Calibration</h2>
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
            <select :data-index="index" :id="['input',node.nodename,index].join('_')" 
              @change="passOnSelection($event, node, 'vcal')">
              <option v-for="device in preset_vcals" :value="device.vcal">
                {{device.name}}
              </option>
            </select>
            <td><div class="input-prepend input-append">
              <button class="btn" 
                @mousedown="waitForLongPress(node, false, index, 'vcal')" 
                @mouseup="stopLongPress" 
                @mouseleave="stopLongPress">-</button>
              <input class="span4" v-model="node.rx.vcal" type="text">
              <button class="btn" 
                @mousedown="waitForLongPress(node, true, index, 'vcal')" 
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
            <select :data-index="index" :id="['input',node.nodename,index].join('_')" 
              @change="passOnSelection($event, node, 'icals')">
              <option v-for="device in preset_icals" :value="device.ical">{{device.name}}</option>
            </select>
            <td>
              <div class="input-prepend input-append">
                <button class="btn" type="button"
                    @mousedown="waitForLongPress(node, false, index, 'icals')" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">-</button>
                <input class="span4" type="text"  v-model="node.rx.icals[index]">
                <button class="btn" type="button"
                    @mousedown="waitForLongPress(node, true, index, 'icals')" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">+</button>
              </div>
            </td>
            <td><div class="input-prepend input-append">
            <button class="btn" type="button"
                    @mousedown="waitForLongPress(node, false, index, 'phase_shifts')" 
                    @mouseup="stopLongPress" 
                    @mouseleave="stopLongPress">-</button>
              <input type="text" class="span4" v-model="node.rx.phase_shifts[index]">
              <button class="btn" type="button"
                    @mousedown="waitForLongPress(node, true, index, 'phase_shifts')" 
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
Vue.config.productionTip = false;

var app = new Vue({
  el: '#conf',
  data: { 
    conf: conf,
    live: "hello",
    increment: .1,
    pressTimer: null,
    holdTimer: null,
    save_timeout: void 0,
    decimals: 2,
    longPressWait: 600,
    longPressDelay: 50,
    ajaxSaveWait: 700,
    statusMessageDelay: 3000,
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
      return value.toFixed(this.decimals);
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
    set: function(node, value, index, key) {
        if (key==='vcal') {
            node.rx[key] = Number(value).toFixed(this.decimals);
        } else {
            var values = node.rx[key];
            values[index] = Number(value).toFixed(this.decimals);
            node.rx[key] = Object.assign([], values);
        }
        this.check_select(node, index, key);
    },
    step: function(node, isIncrement, index, key) {
        var _step = Math.abs(this.increment);
        var step = isIncrement ? _step: 0 - _step;
        var value;
        if (typeof node.rx[key] === 'string') {
            value = parseFloat(node.rx[key]);
        } else {
            value = parseFloat(node.rx[key][index]);
        }
        var newValue = Number(value + step).toFixed(this.decimals);
        this.set(node, newValue , index, key);
    },
    waitForLongPress: function(node, isIncrement, index, key) {
        var vm = this;
        this.step(node, isIncrement, index, key);
        this.pressTimer = setTimeout(function(){
            vm.startLongPress(node, isIncrement, index, key);
        }, this.longPressWait);
    },
    startLongPress: function(node, isIncrement, index, key) {
        var vm = this;
        this.holdTimer = setInterval(function(){
            vm.step(node, isIncrement, index, key);
        }, this.longPressDelay);
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
        for (var j = 0; j < node.rx.unitless.length; j++) {
            var key = node.rx.unitless[j] === 'v' ? 'vcal': 'icals';
            this.check_select(node, j, key);
        }
      }
    },
    check_select: function(node, index, key) {
      var select = document.querySelector('#'+['input',node.nodename, index].join('_'));
      var values = node.rx[key];
      if (select) {
        for(var i = 0; i < select.options.length; i++){
            var value;
            if (typeof values === 'string') {
                value = values;
            } else {
                value = values[index];
            }
            value = Number(value).toFixed(this.decimals)
            var option = select.options[i];
            var option_value = Number(option.value).toFixed(this.decimals);
            if (option_value == value) {
                select.selectedIndex = i;
                break;
            } else {
                select.selectedIndex = 0;
            }
        }
      }
    },
    passOnSelection: function(event, node, key) {
        var select = event.target;
        var index = select.dataset.index;
        var value = select.options[select.selectedIndex].value || 0;
        this.set(node, value, index, key);
    },
    debounced_save: function(data) {
        var vm = this;
        clearTimeout(this.timeout);
        this.timeout = setTimeout(function () {
            vm.save(data);
         }, this.ajaxSaveWait);
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
        }, this.statusMessageDelay)
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
