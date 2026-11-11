<?php
    defined('EMONCMS_EXEC') or die('Restricted access');
    load_js("Lib/js/vue.global.prod-3.5.22.min.js");
    load_js("Modules/config/Views/emonhub_conf.js");
    load_css("Modules/config/Views/config_ui.css");
?>

<div id="config-ui" class="cfg" v-cloak>
    <div v-if="!root" class="panel"><div class="panel-empty">Loading config...</div></div>
    <template v-else>

        <ul class="nav nav-tabs cfg-tabs">
            <li class="nav-item"><a href="#" class="nav-link" :class="{active: tab=='interfacers'}" @click.prevent="tab='interfacers'">Interfacers <span class="panel-badge">{{ interfacers.children.length }}</span></a></li>
            <li class="nav-item"><a href="#" class="nav-link" :class="{active: tab=='nodes'}" @click.prevent="tab='nodes'">Nodes <span class="panel-badge">{{ nodes ? nodes.children.length : 0 }}</span></a></li>
            <li class="nav-item"><a href="#" class="nav-link" :class="{active: tab=='hub'}" @click.prevent="tab='hub'">Hub</a></li>
            <li class="nav-item ms-auto"><a href="#" class="nav-link cfg-text-link" @click.prevent="open_text_editor">Edit as text</a></li>
        </ul>

        <div v-if="other_sections.length" class="alert alert-info">
            Sections not shown here: <b>{{ other_sections.join(", ") }}</b>. Use Edit as text to change them.
        </div>

        <!-- Hub -->
        <div v-show="tab=='hub'">
            <div class="panel">
                <div class="panel-header panel-header-static">
                    <span class="panel-accent"></span>
                    <span class="panel-name">Hub settings</span>
                </div>
                <settings-table :section="hub" :types="types" :schema="hub_schema"></settings-table>
            </div>
        </div>

        <!-- Interfacers -->
        <div v-show="tab=='interfacers'">
            <p class="cfg-lead">Interfacers read data from hardware and services, and send it on to emoncms and other destinations.</p>

            <interfacer-card v-for="section in interfacers.children" :key="section.id" :section="section" :parent="interfacers" :types="types"></interfacer-card>

            <div class="panel cfg-add-panel">
                <div class="panel-header panel-header-static">
                    <span class="svg-icon-plus cfg-add-icon"></span>
                    <span class="panel-name">Add interfacer</span>
                </div>
                <div class="cfg-add-form">
                    <div class="cfg-add-field">
                        <label class="form-label" for="cfg-new-template">Start from</label>
                        <select id="cfg-new-template" class="form-select" v-model="new_interfacer.template" @change="template_selected">
                            <option value="">Blank interfacer</option>
                            <option v-for="(example, index) in examples" :value="index">{{ example.name }}</option>
                        </select>
                    </div>
                    <div class="cfg-add-field">
                        <label class="form-label" for="cfg-new-name">Name</label>
                        <input id="cfg-new-name" class="form-control" v-model.trim="new_interfacer.name" placeholder="e.g. MQTT" @keyup.enter="add_interfacer">
                    </div>
                    <button class="btn btn-default" @click="add_interfacer" :disabled="!new_interfacer.name">Add interfacer</button>
                </div>
                <div v-if="new_interfacer.error" class="cfg-error">{{ new_interfacer.error }}</div>
                <div v-if="new_interfacer.notes.length" class="cfg-notes">
                    <div v-for="note in new_interfacer.notes">{{ note }}</div>
                </div>
            </div>
        </div>

        <!-- Nodes -->
        <div v-show="tab=='nodes'">
            <p class="cfg-lead">
                Nodes name and decode the values that interfacers receive.
                <span v-if="autoconf">Autoconf is on, so emonhub adds known nodes when they first send data.</span>
            </p>
            <div v-if="!nodes" class="alert alert-warning">No [nodes] section in config.</div>
            <template v-else>
                <node-card v-for="section in nodes.children" :key="section.id" :section="section" :parent="nodes"></node-card>
            </template>

            <div class="panel cfg-add-panel">
                <div class="panel-header panel-header-static">
                    <span class="svg-icon-plus cfg-add-icon"></span>
                    <span class="panel-name">Add node</span>
                </div>
                <div class="cfg-add-form">
                    <div class="cfg-add-field cfg-add-narrow">
                        <label class="form-label" for="cfg-new-node-id">Node ID</label>
                        <input id="cfg-new-node-id" class="form-control" v-model.trim="new_node.id" placeholder="e.g. 17" inputmode="numeric">
                    </div>
                    <div class="cfg-add-field">
                        <label class="form-label" for="cfg-new-node-name">Node name</label>
                        <input id="cfg-new-node-name" class="form-control" v-model.trim="new_node.name" placeholder="e.g. emonTx4_17" @keyup.enter="add_node">
                    </div>
                    <button class="btn btn-default" @click="add_node" :disabled="!new_node.id">Add node</button>
                </div>
                <div v-if="new_node.error" class="cfg-error">{{ new_node.error }}</div>
            </div>
        </div>

        <!-- Save bar -->
        <div class="cfg-savebar" :class="{dirty: dirty}">
            <span class="cfg-save-state">
                <span class="cfg-save-dot"></span>
                <template v-if="dirty"><b>Unsaved changes</b> <a href="#" @click.prevent="show_diff=!show_diff">{{ show_diff ? 'Hide' : 'Review' }} changes</a></template>
                <template v-else>{{ message || 'No unsaved changes' }}</template>
            </span>
            <span v-if="error" class="cfg-save-error">{{ error }}</span>
            <button class="btn btn-default" @click="discard" :disabled="!dirty || saving">Discard</button>
            <button class="btn btn-primary" @click="save" :disabled="!dirty || saving">{{ saving ? 'Saving...' : 'Save config' }}</button>
        </div>
        <div v-if="errors.length" class="panel cfg-errors-panel" ref="notice">
            <div class="panel-header panel-header-static">
                <span class="panel-accent panel-accent-danger"></span>
                <span class="panel-name">emonhub.conf was not saved because of these errors</span>
            </div>
            <ul class="cfg-error-list">
                <li v-for="e in errors">{{ e }}</li>
            </ul>
            <div class="cfg-conflict-text">Line numbers refer to the text shown by Edit as text.</div>
        </div>

        <div v-if="conflict" class="panel cfg-conflict-panel" ref="notice">
            <div class="panel-header panel-header-static">
                <span class="panel-accent cfg-accent-warning"></span>
                <span class="panel-name">emonhub.conf changed while you were editing</span>
            </div>
            <div class="cfg-conflict-text">
                <p v-if="conflict.new_nodes.length">
                    Autoconf added
                    <span v-for="(node, i) in conflict.new_nodes"><template v-if="i">, </template><b>node {{ node.name }}</b><template v-if="node_name(node)"> ({{ node_name(node) }})</template></span>.
                    Saving your version as it is would remove {{ conflict.new_nodes.length > 1 ? 'them' : 'it' }}.
                </p>
                <p v-if="conflict.other">Other settings in the file were also changed outside this page. Review the changes before choosing.</p>
                <p><a href="#" @click.prevent="conflict.show_diff = !conflict.show_diff">{{ conflict.show_diff ? 'Hide' : 'Review' }} changes made to the file</a></p>
            </div>
            <pre v-if="conflict.show_diff" class="cfg-diff"><span v-for="line in conflict_diff" :class="'diff-' + line.type">{{ line.prefix }} {{ line.text }}
</span></pre>
            <div class="cfg-card-footer">
                <button v-if="conflict.new_nodes.length" class="btn btn-primary" @click="merge_new_nodes" :disabled="saving">Add new {{ conflict.new_nodes.length > 1 ? 'nodes' : 'node' }} and save</button>
                <button class="btn btn-default" @click="reload_current" :disabled="saving">Reload file, discard my changes</button>
                <button class="btn btn-default cfg-delete" @click="save_anyway" :disabled="saving">Save my version anyway</button>
            </div>
        </div>

        <div v-if="dirty && show_diff" class="panel cfg-diff-panel">
            <div class="panel-header panel-header-static">
                <span class="panel-accent"></span>
                <span class="panel-name">Changes to emonhub.conf</span>
            </div>
            <pre class="cfg-diff"><span v-for="line in diff" :class="'diff-' + line.type">{{ line.prefix }} {{ line.text }}
</span></pre>
        </div>
    </template>
</div>

<!-- Interfacer -->
<script type="text/x-template" id="interfacer-card-template">
    <div class="panel cfg-card" :class="{open: open}">
        <div class="panel-header" @click="open=!open">
            <span class="svg-icon-chevron-down cfg-chevron"></span>
            <span class="panel-accent"></span>
            <span class="cfg-card-name">{{ section.name }}</span>
            <span class="badge px-2 cfg-type" :class="type_class">{{ short_type || 'No type' }}</span>
            <span class="cfg-summary">{{ summary }}</span>
            <span v-if="missing_required.length" class="badge px-2 bg-warning-subtle text-warning-emphasis" :title="'Missing: ' + missing_required.join(', ')">Needs {{ missing_required.join(", ") }}</span>
            <span class="cfg-channels">
                <span v-for="c in subchannels" class="cfg-channel" :class="{'cfg-channel-unused': channel_unused.sub}" :title="channel_unused.sub ? 'subchannels not used by ' + type : 'Takes data from ' + c"><span class="cfg-dir">in</span>{{ c }}</span>
                <span v-for="c in pubchannels" class="cfg-channel" :class="{'cfg-channel-unused': channel_unused.pub}" :title="channel_unused.pub ? 'pubchannels not used by ' + type : 'Sends data to ' + c"><span class="cfg-dir">out</span>{{ c }}</span>
            </span>
        </div>
        <div v-if="open">
            <div class="panel-row">
                <div class="row-key"><label :for="'name' + section.id">Name</label></div>
                <div class="row-value">
                    <input :id="'name' + section.id" class="form-control cfg-name-input" :value="section.name" @change="rename($event.target)">
                    <div v-if="rename_error" class="cfg-error-inline">{{ rename_error }}</div>
                </div>
                <span class="cfg-row-spacer"></span>
            </div>
            <div class="panel-row">
                <div class="row-key"><label :for="'type' + section.id">Type</label></div>
                <div class="row-value">
                    <select :id="'type' + section.id" class="form-select" :value="type" @change="set_type($event.target.value)">
                        <option v-if="type && types.indexOf(type)==-1" :value="type">{{ type }}</option>
                        <option v-for="t in types" :value="t">{{ t }}</option>
                    </select>
                    <div v-if="settings_unknown" class="row-note-block">Settings for {{ type }} are not known, so setting names are not checked.</div>
                </div>
                <span class="cfg-row-spacer"></span>
            </div>

            <settings-table :section="section" :exclude="['Type']" :types="types" :schema="section_schema" v-if="has_other_settings"></settings-table>

            <div class="cfg-subhead" v-if="show_init">
                <span>Init settings</span>
                <span class="cfg-subhead-note">Changing these restarts the interfacer</span>
            </div>
            <settings-table v-if="init_settings && show_init" :section="init_settings" :types="types" :schema="init_schema" :unknown-label="'Not an init setting of ' + type"></settings-table>

            <div class="cfg-subhead">
                <span>Runtime settings</span>
                <span class="cfg-subhead-note">Applied while running</span>
            </div>
            <settings-table v-if="runtimesettings" :section="runtimesettings" :types="types" :schema="runtime_schema" :unknown-label="'Not used by ' + type"></settings-table>
            <div v-if="nested.length" class="cfg-nested">Subsections not shown here: <b>{{ nested.join(", ") }}</b>. Use Edit as text to change them.</div>

            <div class="cfg-card-footer">
                <button class="btn btn-default btn-sm cfg-delete" @click="remove"><span class="svg-icon-trash"></span> Delete interfacer</button>
            </div>
        </div>
    </div>
</script>

<!-- Node -->
<script type="text/x-template" id="node-card-template">
    <div class="panel cfg-card" :class="{open: open}">
        <div class="panel-header" @click="open=!open">
            <span class="svg-icon-chevron-down cfg-chevron"></span>
            <span class="panel-accent"></span>
            <span class="badge px-2 bg-primary-subtle text-primary-emphasis cfg-node-id">{{ section.name }}</span>
            <span class="cfg-card-name">{{ nodename || 'Unnamed node' }}</span>
            <span class="cfg-summary">{{ summary }}</span>
            <span v-if="has_warning" class="badge px-2 bg-warning-subtle text-warning-emphasis" title="Input lists have different lengths">Check inputs</span>
            <span v-if="rx" class="panel-badge">{{ rx_count }} inputs</span>
        </div>
        <div v-if="open">
            <div class="panel-row">
                <div class="row-key"><label :for="'nodeid' + section.id">Node ID</label></div>
                <div class="row-value">
                    <input :id="'nodeid' + section.id" class="form-control input-105" :value="section.name" @change="rename($event.target)" inputmode="numeric">
                    <div v-if="rename_error" class="cfg-error-inline">{{ rename_error }}</div>
                </div>
                <span class="cfg-row-spacer"></span>
            </div>
            <div class="panel-row">
                <div class="row-key"><label :for="'nodename' + section.id">Node name</label></div>
                <div class="row-value">
                    <input :id="'nodename' + section.id" class="form-control cfg-name-input" :value="nodename" @input="set_nodename($event.target.value)">
                </div>
                <span class="cfg-row-spacer"></span>
            </div>

            <settings-table :section="section" :exclude="['nodename']" :schema="node_schema"></settings-table>

            <template v-for="sub in section.children" :key="sub.id">
                <div class="cfg-subhead">
                    <span>{{ sub.name == 'rx' ? 'Received inputs' : sub.name == 'tx' ? 'Transmitted values' : sub.name }}</span>
                    <span class="cfg-subhead-note">[[[{{ sub.name }}]]]</span>
                </div>
                <inputs-table :section="sub"></inputs-table>
            </template>

            <div class="cfg-card-footer">
                <button v-if="!rx" class="btn btn-default btn-sm" @click="add_sub('rx')"><span class="svg-icon-plus"></span> Add received inputs</button>
                <button v-if="!tx" class="btn btn-default btn-sm" @click="add_sub('tx')"><span class="svg-icon-plus"></span> Add transmitted values</button>
                <button class="btn btn-default btn-sm cfg-delete" @click="remove"><span class="svg-icon-trash"></span> Delete node</button>
            </div>
        </div>
    </div>
</script>

<!-- Node inputs: names, datacodes, scales, units as a table -->
<script type="text/x-template" id="inputs-table-template">
    <div>
        <div v-if="mismatch" class="cfg-mismatch">
            Input lists have different lengths:
            <span v-for="c in columns" class="badge px-2 bg-warning-subtle text-warning-emphasis">{{ labels[c] || c }} {{ lists[c].length }}</span>
        </div>
        <div class="panel-table">
            <table class="cfg-inputs">
                <thead>
                    <tr>
                        <th class="cfg-col-index">#</th>
                        <th v-for="c in columns" :class="'cfg-col-' + c" :title="col_desc[c]">{{ labels[c] || c }}</th>
                        <th class="cfg-col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in row_count">
                        <td class="cfg-index">{{ i + 1 }}</td>
                        <td v-for="c in columns">
                            <select v-if="c=='datacodes'" class="form-select cfg-datacode" :class="datacode_class(lists[c][i])" :value="lists[c][i]" @change="set_cell(c, i, $event.target.value)">
                                <option v-if="lists[c][i] !== undefined && !datacodes[lists[c][i]]" :value="lists[c][i]">{{ lists[c][i] }}</option>
                                <option v-for="(label, code) in datacodes" :value="code">{{ code }} · {{ label }}</option>
                            </select>
                            <input v-else class="form-control" :class="'cfg-cell-' + c" :value="lists[c][i]" @change="set_cell(c, i, $event.target.value)">
                        </td>
                        <td class="cfg-actions">
                            <span class="row-action svg-icon-arrow-up" :class="{disabled: i==0}" @click="i > 0 && move(i, -1)" title="Move up"></span>
                            <span class="row-action svg-icon-arrow-down" :class="{disabled: i==row_count-1}" @click="i < row_count-1 && move(i, 1)" title="Move down"></span>
                            <span class="row-action svg-icon-trash" @click="remove_row(i)" title="Remove input"></span>
                        </td>
                    </tr>
                    <tr v-if="row_count == 0">
                        <td :colspan="columns.length + 2" class="cfg-empty">No inputs yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="cfg-table-footer">
            <button class="btn btn-default btn-sm" @click="add_row"><span class="svg-icon-plus"></span> Add input</button>
        </div>
        <settings-table :section="section" :exclude="columns" :schema="schema"></settings-table>
    </div>
</script>

<!-- Key and value settings -->
<script type="text/x-template" id="settings-table-template">
    <div class="cfg-settings">
        <div v-for="row in rows" :key="row.item.id" class="panel-row" :class="{'cfg-unknown': row.unknown}">
            <div class="row-key">
                <span class="cfg-key" :title="row.unknown ? unknownLabel : row.item.key">{{ row.item.key }}</span>
                <span v-if="row.unknown" class="cfg-unknown-note">{{ unknownLabel }}</span>
            </div>
            <div class="row-value">
                <value-editor :item="row.item" :types="types" :default-value="row.default"></value-editor>
                <div v-if="row.desc" class="row-note-block" :class="{'cfg-no-effect': row.no_effect}">{{ row.desc }}</div>
                <div v-if="row.help" class="cfg-comment" title="Comment in emonhub.conf"># {{ row.help }}</div>
            </div>
            <span class="row-action svg-icon-trash" @click="remove_key(row.item)" :title="'Remove ' + row.item.key"></span>
        </div>

        <div v-if="optional.length" class="panel-row cfg-optional">
            <div class="row-key">Optional</div>
            <div class="row-value">
                <button v-for="opt in optional" class="cfg-chip" @click="enable_optional(opt)" :title="'Add ' + opt.key + ' = ' + opt.value">+ {{ opt.key }}</button>
            </div>
            <span class="cfg-row-spacer"></span>
        </div>

        <div class="panel-row cfg-add-row" v-if="!schema || available.length">
            <div class="row-key">
                <select v-if="schema" class="form-select cfg-key-select" v-model="new_key" @change="key_selected" :aria-label="'Add setting'">
                    <option value="">Add setting...</option>
                    <optgroup v-for="group in available" :label="group.label">
                        <option v-for="key in group.keys" :value="key" :title="describe(key)">{{ key }}</option>
                    </optgroup>
                </select>
                <input v-else class="form-control cfg-key-select" v-model.trim="new_key" placeholder="Setting name" @keyup.enter="add_key">
            </div>
            <div class="row-value">
                <input class="form-control" v-model="new_value" :placeholder="new_key ? 'Value' : ''" :disabled="!new_key" @keyup.enter="add_key" aria-label="Value">
                <div v-if="new_key && describe(new_key)" class="row-note-block">{{ describe(new_key) }}</div>
            </div>
            <button class="btn btn-default btn-sm" @click="add_key" :disabled="!new_key">Add</button>
        </div>
        <div v-if="error" class="cfg-error">{{ error }}</div>
    </div>
</script>

<!-- Value input, chosen by setting key and value -->
<script type="text/x-template" id="value-editor-template">
    <div class="cfg-value">
        <select v-if="kind=='loglevel'" class="form-select input-165" v-model="text">
            <option v-for="l in ['DEBUG','INFO','WARNING','ERROR','CRITICAL']" :value="l">{{ l }}</option>
        </select>

        <select v-else-if="kind=='type'" class="form-select" v-model="text">
            <option v-if="types.indexOf(text)==-1" :value="text">{{ text }}</option>
            <option v-for="t in types" :value="t">{{ t }}</option>
        </select>

        <div v-else-if="kind=='bool'" class="form-check form-switch cfg-switch">
            <input class="form-check-input" type="checkbox" role="switch" :id="'sw' + item.id" v-model="bool">
            <label class="form-check-label" :for="'sw' + item.id" :class="bool ? 'cfg-on' : 'cfg-off'">{{ bool ? 'On' : 'Off' }}</label>
        </div>

        <div v-else-if="kind=='channels'" class="cfg-chips">
            <button v-for="c in channel_options" type="button" class="cfg-chip" :class="{on: channels.indexOf(c)!=-1}" @click="toggle_channel(c)" :aria-pressed="channels.indexOf(c)!=-1">{{ c }}</button>
        </div>

        <div v-else-if="kind=='password'" class="input-group d-flex w-100">
            <input :type="reveal ? 'text' : 'password'" class="form-control cfg-mono" v-model="text" autocomplete="off">
            <button class="btn btn-default" @click="reveal=!reveal" type="button" :title="reveal ? 'Hide' : 'Show'"><span class="svg-icon-eye"></span></button>
        </div>

        <textarea v-else-if="item.multiline" class="form-control cfg-mono" rows="4" v-model="text"></textarea>

        <template v-else>
            <input class="form-control" :class="{'cfg-number': is_number}" v-model="text" :list="suggestions.length ? 'list' + item.id : null">
            <datalist v-if="suggestions.length" :id="'list' + item.id">
                <option v-for="s in suggestions" :value="s"></option>
            </datalist>
        </template>
    </div>
</script>

<?php load_js("Modules/config/Views/config_ui.js"); ?>
