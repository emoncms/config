/*
  EmonHub configuration UI

  Edits a parsed copy of emonhub.conf (see emonhub_conf.js) and saves the
  serialised text back through config/set. EmonHub reloads the file on change.
*/

var Conf = EmonHubConf;

var CHANNELS = ["ToEmonCMS", "ToRFM12"];
var BOOL_KEY = /enable|^send(data|names)$|^autoconf$|^use_|insecure/i;
var BOOL_PAIRS = { "1": "0", "true": "false", "True": "False", "yes": "no", "on": "off" };
var PASSWORD_KEY = /passw|apikey|password|token|secret/i;
var SUGGESTIONS = {
    com_port: ["/dev/ttyAMA0", "/dev/ttyUSB0", "/dev/ttyUSB1", "/dev/ttyACM0", "/dev/serial0"],
    com_baud: ["9600", "38400", "115200"],
    pause: ["off", "in", "out", "all"],
    mqtt_host: ["127.0.0.1", "localhost"],
    url: ["https://emoncms.org"]
};
var INPUT_COLUMNS = ["names", "datacodes", "scales", "units"];
// Tag colour by interfacer family, from the theme pastel pairs
var TYPE_FAMILIES = [
    { cls: "bg-info-subtle text-info-emphasis", match: /OEM|RFM69|RF69|Jee|Serial|Tx3e|Sunamp|VEDirect/ },
    { cls: "bg-purple-subtle text-purple-emphasis", match: /Mqtt|EmoncmsHTTP|Influx|Graphite|Redis|Socket|PacketGen/ },
    { cls: "bg-orange-subtle text-orange-emphasis", match: /DS18B20|Pulse|DigitalInput|SDM120|MBUS|Modbus|SDS011|Ble/ },
    { cls: "bg-success-subtle text-success-emphasis", match: /BMW|Jaguar|Tesla|GoodWe|SMA|Shelly|Econet|Econext|Template/ }
];

// Settings shown in the interfacer header, first match wins
var SUMMARY_KEYS = ["com_port", "mqtt_host", "url", "device", "pulse_pin", "host", "device_addr", "redis_host", "influx_host"];

// Settings read by emonhub outside interfacers
var HUB_SETTINGS = { loglevel: "WARNING", autoconf: 1, use_syslog: "yes", log_backup_count: 1, log_max_bytes: 5120000 };
var NODE_SETTINGS = { nodename: "" };
// Descriptions of settings that emonhub accepts but does not act on
var NO_EFFECT = /^(Currently has no effect|Set from init_settings, changing it here has no effect|Only used in autoconf templates)/;
// Descriptions for emonhub versions without settings_info in the source
var FALLBACK_DESC = typeof SETTING_DESCRIPTIONS !== "undefined" ? SETTING_DESCRIPTIONS : { common: {}, hub: {}, node: {}, rx: {}, tx: {}, types: {} };

// Descriptions of a group: common, hub, node, rx or tx
function desc_group(name) {
    var d = store.settings && store.settings.descriptions;
    if (d && d[name] && Object.keys(d[name]).length) return d[name];
    return FALLBACK_DESC[name] || {};
}

// Descriptions of an interfacer type, split into init and runtime
function desc_type(type, type_settings) {
    var d = type_settings && type_settings.descriptions;
    if (d && (Object.keys(d.init || {}).length || Object.keys(d.runtime || {}).length)) return d;
    return FALLBACK_DESC.types[type] || { init: {}, runtime: {} };
}
var RX_SETTINGS = { names: "", datacodes: "", datacode: "h", scales: "", scale: "1", units: "", whitening: "" };
var TX_SETTINGS = { names: "", datacodes: "", datacode: "h", scales: "", scale: "1", units: "" };

// Interfacer types, settings and examples from config/interfacers
var store = Vue.reactive({ loaded: false, types: [], settings: null, examples: [] });

var DATACODES = {
    b: "int8", B: "uint8", h: "int16", H: "uint16", i: "int32", I: "uint32",
    l: "int32", L: "uint32", q: "int64", Q: "uint64", f: "float", d: "double"
};

// Plain text for a default value from the emonhub source
function format_default(value) {
    if (value === null || value === undefined) return "";
    if (typeof value === "boolean") return value ? "true" : "false";
    if (Array.isArray(value)) return Conf.format_list(value.map(String));
    return String(value);
}

function update(item, value) {
    if (item.value === value) return;
    item.value = value;
    item.dirty = true;
    item.multiline = false;
}

// Plain text from a stored value
function display(item) {
    if (item.multiline) return item.value;
    if (/^(["']).*\1$/.test(item.value) && item.value.indexOf(",") == -1) return Conf.unquote(item.value);
    return item.value;
}

// Stored value from plain text
function encode(text) {
    if (/#/.test(text) || /^\s|\s$/.test(text)) return Conf.quote(text);
    return text;
}

// General settings a type uses, with defaults. All of them if not known.
function used_common(type) {
    var out = {};
    for (var key in store.settings.common) {
        if (!type.common || type.common.indexOf(key) != -1) out[key] = store.settings.common[key];
    }
    return out;
}

// Clean an interfacer added from an example: remove settings the type does
// not use, and trailing commas that turn a single value into a list.
// Returns notes on what changed.
function tidy_interfacer(section) {
    var notes = [];
    var type = store.settings && store.settings.types[Conf.get(section, "Type")];
    if (!type) return notes;

    var parts = [
        { section: Conf.child(section, "init_settings"), known: type.init },
        { section: Conf.child(section, "runtimesettings"), known: Object.assign(used_common(type), type.runtime) }
    ];
    var removed = [], commas = [];
    parts.forEach(function (part) {
        if (!part.section) return;
        Conf.settings(part.section).forEach(function (item) {
            if (!(item.key in part.known)) {
                Conf.remove(part.section, item.key);
                removed.push(item.key);
                return;
            }
            var m = item.value.match(/^([^,]+?)\s*,\s*$/);
            if (m && !Array.isArray(part.known[item.key])) {
                update(item, m[1]);
                commas.push(item.key);
            }
        });
    });
    if (removed.length) notes.push("Removed " + removed.join(", ") + ", not used by " + Conf.get(section, "Type") + ".");
    if (commas.length) notes.push("Removed trailing commas from " + commas.join(", ") + ".");
    return notes;
}

// Settings of a parsed config as "section/key = value" lines, without comments
// or formatting, for comparing two versions. Sections named in skip are left out.
function config_values(root, skip) {
    var out = [];
    var walk = function (section, path) {
        Conf.settings(section).forEach(function (item) {
            var value = item.value.replace(/\s*,\s*/g, ",").replace(/["']/g, "").replace(/,$/, "");
            out.push(path + item.key + " = " + value);
        });
        section.children.forEach(function (child) {
            if (section.depth == 0 && skip.indexOf(child.name) != -1) return;
            walk(child, path + child.name + "/");
        });
    };
    walk(root, "");
    return out.sort().join("\n");
}

// Compare the loaded config with the file now on disk.
// new_nodes: nodes added to the file that the edits do not have.
// other: the file changed in some other way.
function analyse_conflict(base_text, current_text, mine_root) {
    var base = Conf.parse(base_text);
    var current = Conf.parse(current_text);
    var base_nodes = Conf.child(base, "nodes");
    var current_nodes = Conf.child(current, "nodes");
    var mine_nodes = Conf.child(mine_root, "nodes");

    var new_nodes = [];
    var nodes_changed = false;
    if (current_nodes) {
        current_nodes.children.forEach(function (node) {
            var before = Conf.child(base_nodes, node.name);
            if (!before) {
                if (!Conf.child(mine_nodes, node.name)) new_nodes.push(node);
            } else if (config_values(before, []) != config_values(node, [])) {
                nodes_changed = true;
            }
        });
    }
    if (base_nodes) {
        base_nodes.children.forEach(function (node) {
            if (!Conf.child(current_nodes, node.name)) nodes_changed = true;
        });
    }
    var other = nodes_changed || config_values(base, ["nodes"]) != config_values(current, ["nodes"]);
    return { new_nodes: new_nodes, other: other };
}

function valid_section_name(name, parent, section) {
    if (name === "") return "Name is required";
    if (/[\[\]]/.test(name)) return "Name cannot contain [ or ]";
    for (var i = 0; i < parent.children.length; i++) {
        if (parent.children[i] !== section && parent.children[i].name == name) return "Name '" + name + "' is already in use";
    }
    return null;
}

// Line diff for change review
function line_diff(a, b) {
    a = a.split("\n");
    b = b.split("\n");
    var n = a.length, m = b.length;
    var lcs = [];
    for (var i = 0; i <= n; i++) lcs.push(new Uint16Array(m + 1));
    for (var i = n - 1; i >= 0; i--) {
        for (var j = m - 1; j >= 0; j--) {
            lcs[i][j] = a[i] === b[j] ? lcs[i + 1][j + 1] + 1 : Math.max(lcs[i + 1][j], lcs[i][j + 1]);
        }
    }
    var out = [];
    var i = 0, j = 0;
    while (i < n || j < m) {
        if (i < n && j < m && a[i] === b[j]) { out.push({ type: "same", prefix: " ", text: a[i] }); i++; j++; }
        else if (i < n && (j == m || lcs[i + 1][j] >= lcs[i][j + 1])) { out.push({ type: "del", prefix: "-", text: a[i] }); i++; }
        else { out.push({ type: "add", prefix: "+", text: b[j] }); j++; }
    }

    // Keep 2 lines of context around changes
    var keep = out.map(function () { return false; });
    out.forEach(function (line, k) {
        if (line.type != "same") {
            for (var c = Math.max(0, k - 2); c <= Math.min(out.length - 1, k + 2); c++) keep[c] = true;
        }
    });
    var result = [];
    var skipped = false;
    out.forEach(function (line, k) {
        if (keep[k]) {
            result.push(line);
            skipped = false;
        } else if (!skipped) {
            result.push({ type: "skip", prefix: " ", text: "..." });
            skipped = true;
        }
    });
    return result;
}

// -------------------------------------------------------------------------
// Value editor
// -------------------------------------------------------------------------

var ValueEditor = {
    template: "#value-editor-template",
    props: { item: Object, types: { type: Array, default: function () { return []; } }, defaultValue: null },
    data: function () { return { reveal: false }; },
    computed: {
        kind: function () {
            var key = this.item.key;
            var value = this.item.value.toLowerCase();
            if (key == "loglevel") return "loglevel";
            if (key == "Type") return "type";
            if (key == "pubchannels" || key == "subchannels") return "channels";
            if (PASSWORD_KEY.test(key)) return "password";
            var bool_value = ["0", "1", "true", "false", "yes", "no", "on", "off"].indexOf(value) != -1;
            if (bool_value && (BOOL_KEY.test(key) || typeof this.defaultValue === "boolean")) return "bool";
            return "text";
        },
        text: {
            get: function () { return display(this.item); },
            set: function (text) { update(this.item, this.item.multiline ? text : encode(text)); }
        },
        bool: {
            get: function () { return ["1", "true", "yes", "on"].indexOf(this.item.value.toLowerCase()) != -1; },
            set: function (on) {
                var value = this.item.value;
                for (var t in BOOL_PAIRS) {
                    var f = BOOL_PAIRS[t];
                    if (value == t || value == f) {
                        update(this.item, on ? t : f);
                        return;
                    }
                }
                update(this.item, on ? "1" : "0");
            }
        },
        channels: function () { return Conf.parse_list(this.item.value); },
        channel_options: function () {
            var options = CHANNELS.slice();
            this.channels.forEach(function (c) { if (options.indexOf(c) == -1) options.push(c); });
            return options;
        },
        suggestions: function () { return SUGGESTIONS[this.item.key] || []; },
        is_number: function () { return /^-?\d+(\.\d+)?$/.test(this.item.value); }
    },
    methods: {
        toggle_channel: function (c) {
            var list = this.channels.slice();
            var pos = list.indexOf(c);
            if (pos == -1) list.push(c); else list.splice(pos, 1);
            update(this.item, Conf.format_list(list));
        }
    }
};

// -------------------------------------------------------------------------
// Generic settings table
// -------------------------------------------------------------------------

var SettingsTable = {
    template: "#settings-table-template",
    props: {
        section: Object,
        exclude: { type: Array, default: function () { return []; } },
        types: { type: Array, default: function () { return []; } },
        // [{label, settings: {key: default}}], null allows any setting name
        schema: { type: Array, default: null },
        unknownLabel: { type: String, default: "Not used by emonhub" },
        // Descriptions when there is no schema
        descriptions: { type: Object, default: null }
    },
    data: function () { return { new_key: "", new_value: "", error: null }; },
    computed: {
        known: function () {
            if (!this.schema) return null;
            var known = {};
            this.schema.forEach(function (group) {
                for (var key in group.settings) {
                    if (!(key in known)) known[key] = group.settings[key];
                }
            });
            return known;
        },
        // Settings with help text from inline and preceding comments
        rows: function () {
            var rows = [];
            var items = this.section.items;
            var known = this.known;
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                if (item.type != "kv" || this.exclude.indexOf(item.key) != -1) continue;

                var help = [];
                for (var j = i - 1; j >= 0 && items[j].type == "line"; j--) {
                    var line = items[j].raw.trim();
                    if (line == "" || line[0] != "#") break;
                    var text = line.replace(/^#+\s*/, "");
                    if (text == "" || /^#+$/.test(line) || /^[A-Za-z_]\w*\s*=/.test(text)) break;
                    help.unshift(text);
                }
                if (item.comment) help.push(item.comment.replace(/^\s*#+\s*/, ""));
                var desc = this.describe(item.key);
                rows.push({
                    item: item,
                    desc: desc,
                    no_effect: NO_EFFECT.test(desc),
                    help: help.join(" ") == desc ? "" : help.join(" "),
                    unknown: known !== null && !(item.key in known),
                    default: known && item.key in known ? known[item.key] : null
                });
            }
            return rows;
        },
        // Unused settings by group
        available: function () {
            if (!this.schema) return [];
            var section = this.section;
            var exclude = this.exclude;
            var seen = {};
            var self = this;
            return this.schema.map(function (group) {
                var keys = Object.keys(group.settings).filter(function (key) {
                    if (seen[key] || exclude.indexOf(key) != -1 || Conf.find(section, key)) return false;
                    if (NO_EFFECT.test(self.describe(key))) return false;
                    seen[key] = true;
                    return true;
                });
                return { label: group.label, keys: keys.sort() };
            }).filter(function (group) { return group.keys.length; });
        },
        // Commented out settings that can be enabled
        optional: function () {
            var section = this.section;
            var known = this.known;
            var found = [];
            section.items.forEach(function (item) {
                if (item.type != "line") return;
                var m = item.raw.match(/^\s*#\s*([A-Za-z_]\w*)\s*=\s*(.*?)\s*$/);
                if (!m || Conf.find(section, m[1]) || (known && !(m[1] in known))) return;
                if (!found.some(function (o) { return o.key == m[1]; })) {
                    found.push({ key: m[1], value: Conf.split_comment(m[2]).value, line: item });
                }
            });
            return found;
        }
    },
    methods: {
        // Description from the schema group that holds the key
        describe: function (key) {
            var groups = this.schema || this.descriptions && [{ descriptions: this.descriptions }] || [];
            for (var i = 0; i < groups.length; i++) {
                var d = groups[i].descriptions;
                if (d && d[key]) return d[key];
            }
            return "";
        },
        remove_key: function (item) {
            this.section.items = this.section.items.filter(function (i) { return i !== item; });
        },
        key_selected: function () {
            if (this.new_key && this.known) this.new_value = format_default(this.known[this.new_key]);
        },
        add_key: function () {
            this.error = null;
            if (!this.new_key) return;
            if (!/^[A-Za-z_][\w.\-]*$/.test(this.new_key)) { this.error = "Invalid setting name '" + this.new_key + "'"; return; }
            if (this.known && !(this.new_key in this.known)) { this.error = "Unknown setting '" + this.new_key + "'"; return; }
            if (Conf.find(this.section, this.new_key)) { this.error = "Setting '" + this.new_key + "' already exists"; return; }
            Conf.set(this.section, this.new_key, encode(this.new_value));
            this.new_key = "";
            this.new_value = "";
        },
        enable_optional: function (opt) {
            Conf.replace_line(this.section, opt.line, opt.key, opt.value);
        }
    }
};

// -------------------------------------------------------------------------
// Interfacer
// -------------------------------------------------------------------------

var InterfacerCard = {
    template: "#interfacer-card-template",
    props: { section: Object, parent: Object, types: Array },
    data: function () { return { open: false, rename_error: null }; },
    computed: {
        type: function () { return Conf.get(this.section, "Type", ""); },
        short_type: function () { return this.type.replace(/^EmonHub/, "").replace(/Interfacer$/, ""); },
        type_settings: function () { return store.settings && store.settings.types[this.type] || null; },
        // Subsections such as MBUS meters are not edited here
        nested: function () {
            var names = [];
            [this.init_settings, this.runtimesettings].forEach(function (s) {
                if (s) s.children.forEach(function (c) { names.push(c.name); });
            });
            return names;
        },
        settings_unknown: function () { return store.loaded && this.type && !this.type_settings; },
        type_class: function () {
            var type = this.type;
            if (!type) return "bg-secondary-subtle text-secondary-emphasis";
            for (var i = 0; i < TYPE_FAMILIES.length; i++) {
                if (TYPE_FAMILIES[i].match.test(type)) return TYPE_FAMILIES[i].cls;
            }
            return "bg-secondary-subtle text-secondary-emphasis";
        },
        summary: function () {
            var init = this.init_settings;
            if (!init) return "";
            for (var i = 0; i < SUMMARY_KEYS.length; i++) {
                var value = Conf.get(init, SUMMARY_KEYS[i]);
                if (value === null) value = Conf.get(this.runtimesettings, SUMMARY_KEYS[i]);
                if (value) return display({ value: value });
            }
            return "";
        },
        section_schema: function () { return store.settings ? [] : null; },
        // Hide init settings for types that take none
        show_init: function () {
            if (!this.type_settings || !this.init_settings) return true;
            return Object.keys(this.type_settings.init).length > 0 || Conf.settings(this.init_settings).length > 0;
        },
        type_desc: function () { return desc_type(this.type, this.type_settings); },
        init_schema: function () {
            return this.type_settings ? [{ label: this.short_type, settings: this.type_settings.init, descriptions: this.type_desc.init }] : null;
        },
        runtime_schema: function () {
            if (!this.type_settings) return null;
            var common = used_common(this.type_settings);
            return [
                { label: this.short_type, settings: this.type_settings.runtime, descriptions: Object.assign({}, desc_group("common"), this.type_desc.runtime) },
                { label: "General", settings: common, descriptions: desc_group("common") }
            ];
        },
        missing_required: function () {
            var init = this.init_settings;
            if (!this.type_settings || !init) return [];
            return this.type_settings.required.filter(function (key) { return !Conf.find(init, key); });
        },
        init_settings: function () { return Conf.child(this.section, "init_settings"); },
        runtimesettings: function () { return Conf.child(this.section, "runtimesettings"); },
        pubchannels: function () { return Conf.parse_list(Conf.get(this.runtimesettings, "pubchannels")); },
        subchannels: function () { return Conf.parse_list(Conf.get(this.runtimesettings, "subchannels")); },
        // Channel setting not used by this type
        channel_unused: function () {
            var used = this.type_settings && this.type_settings.common;
            return {
                sub: !!used && used.indexOf("subchannels") == -1,
                pub: !!used && used.indexOf("pubchannels") == -1
            };
        },
        has_other_settings: function () {
            return Conf.settings(this.section).some(function (item) { return item.key != "Type"; });
        }
    },
    created: function () {
        // EmonHub needs both subsections to load an interfacer
        if (!this.init_settings) Conf.add_section(this.section, "init_settings");
        if (!this.runtimesettings) Conf.add_section(this.section, "runtimesettings");
    },
    methods: {
        set_type: function (type) { Conf.set(this.section, "Type", type); },
        rename: function (input) {
            var name = input.value.trim();
            this.rename_error = valid_section_name(name, this.parent, this.section);
            if (this.rename_error) {
                input.value = this.section.name;
                return;
            }
            Conf.rename_section(this.section, name);
        },
        remove: function () {
            if (confirm("Delete interfacer '" + this.section.name + "'?")) Conf.remove_section(this.parent, this.section);
        }
    }
};

// -------------------------------------------------------------------------
// Node inputs table
// -------------------------------------------------------------------------

var InputsTable = {
    template: "#inputs-table-template",
    props: { section: Object },
    data: function () {
        return { labels: { names: "Name", datacodes: "Datacode", scales: "Scale", units: "Unit" }, datacodes: DATACODES };
    },
    computed: {
        col_desc: function () { return desc_group(this.section.name); },
        schema: function () {
            if (this.section.name == "rx") return [{ label: "rx", settings: RX_SETTINGS, descriptions: desc_group("rx") }];
            if (this.section.name == "tx") return [{ label: "tx", settings: TX_SETTINGS, descriptions: desc_group("tx") }];
            return null;
        },
        columns: function () {
            var section = this.section;
            var present = INPUT_COLUMNS.filter(function (c) { return Conf.find(section, c); });
            if (present.length) return present;
            // Single datacode applies to all inputs
            if (Conf.find(section, "datacode")) return ["names", "scales", "units"];
            return INPUT_COLUMNS;
        },
        lists: function () {
            var section = this.section;
            var lists = {};
            this.columns.forEach(function (c) { lists[c] = Conf.parse_list(Conf.get(section, c)); });
            return lists;
        },
        row_count: function () {
            var lists = this.lists;
            return Math.max.apply(null, this.columns.map(function (c) { return lists[c].length; }));
        },
        mismatch: function () {
            var lists = this.lists;
            var lengths = this.columns.map(function (c) { return lists[c].length; });
            return lengths.some(function (l) { return l != lengths[0]; });
        }
    },
    methods: {
        // Colour by number type: signed, unsigned, float
        datacode_class: function (code) {
            if (/^[bhilq]$/.test(code)) return "cfg-dc-int";
            if (/^[BHILQ]$/.test(code)) return "cfg-dc-uint";
            if (/^[fd]$/.test(code)) return "cfg-dc-float";
            return "";
        },
        default_value: function (column, index) {
            if (column == "names") return "input" + (index + 1);
            if (column == "datacodes") return "h";
            if (column == "scales") return "1";
            return "";
        },
        write: function (lists) {
            var section = this.section;
            var empty = this.columns.every(function (c) { return lists[c].length == 0; });
            this.columns.forEach(function (c) {
                if (empty) Conf.remove(section, c);
                else Conf.set(section, c, Conf.format_list(lists[c]));
            });
        },
        copy: function () {
            var lists = {};
            for (var c in this.lists) lists[c] = this.lists[c].slice();
            return lists;
        },
        set_cell: function (column, index, value) {
            var lists = this.copy();
            var list = lists[column];
            while (list.length <= index) list.push(this.default_value(column, list.length));
            list[index] = value.trim();
            Conf.set(this.section, column, Conf.format_list(list));
        },
        add_row: function () {
            var lists = this.copy();
            var n = this.row_count;
            for (var c in lists) {
                while (lists[c].length <= n) lists[c].push(this.default_value(c, lists[c].length));
            }
            this.write(lists);
        },
        remove_row: function (index) {
            var lists = this.copy();
            for (var c in lists) lists[c].splice(index, 1);
            this.write(lists);
        },
        move: function (index, step) {
            var lists = this.copy();
            var to = index + step;
            for (var c in lists) {
                var list = lists[c];
                if (to < 0 || to >= list.length || index >= list.length) continue;
                var tmp = list[index];
                list[index] = list[to];
                list[to] = tmp;
            }
            this.write(lists);
        }
    }
};

// -------------------------------------------------------------------------
// Node
// -------------------------------------------------------------------------

var NodeCard = {
    template: "#node-card-template",
    props: { section: Object, parent: Object },
    data: function () { return { open: false, rename_error: null }; },
    computed: {
        nodename: function () { return Conf.unquote(Conf.get(this.section, "nodename", "")); },
        rx: function () { return Conf.child(this.section, "rx"); },
        tx: function () { return Conf.child(this.section, "tx"); },
        node_schema: function () { return [{ label: "Node", settings: NODE_SETTINGS, descriptions: desc_group("node") }]; },
        rx_count: function () { return Conf.parse_list(Conf.get(this.rx, "names")).length; },
        summary: function () {
            var names = Conf.parse_list(Conf.get(this.rx, "names"));
            if (names.length <= 4) return names.join(", ");
            return names.slice(0, 4).join(", ") + " +" + (names.length - 4);
        },
        has_warning: function () {
            var rx = this.rx;
            if (!rx) return false;
            var lengths = INPUT_COLUMNS.filter(function (c) { return Conf.find(rx, c); }).map(function (c) {
                return Conf.parse_list(Conf.get(rx, c)).length;
            });
            return lengths.some(function (l) { return l != lengths[0]; });
        },
    },
    methods: {
        set_nodename: function (name) { Conf.set(this.section, "nodename", encode(name.trim())); },
        rename: function (input) {
            var name = input.value.trim();
            this.rename_error = valid_section_name(name, this.parent, this.section);
            if (!this.rename_error && !/^\d+$/.test(name)) this.rename_error = "Node ID must be a number";
            if (this.rename_error) {
                input.value = this.section.name;
                return;
            }
            Conf.rename_section(this.section, name);
        },
        add_sub: function (name) { Conf.add_section(this.section, name); },
        remove: function () {
            if (confirm("Delete node " + this.section.name + "?")) Conf.remove_section(this.parent, this.section);
        }
    }
};

// -------------------------------------------------------------------------
// App
// -------------------------------------------------------------------------

var config_ui = Vue.createApp({
    data: function () {
        return {
            root: null,
            original: "",
            // Fingerprint of the file as loaded, sent with each save
            hash: null,
            // Set when the file changed on disk since it was loaded
            conflict: null,
            errors: [],
            tab: "interfacers",
            store: store,
            saving: false,
            message: null,
            error: null,
            show_diff: false,
            new_interfacer: { template: "", name: "", error: null, notes: [] },
            new_node: { id: "", name: "", error: null }
        };
    },
    computed: {
        types: function () { return store.types; },
        hub_schema: function () { return [{ label: "Hub", settings: HUB_SETTINGS, descriptions: desc_group("hub") }]; },
        examples: function () { return store.examples; },
        text: function () { return this.root ? Conf.serialize(this.root) : ""; },
        dirty: function () { return this.root !== null && this.text !== this.original; },
        diff: function () { return this.show_diff ? line_diff(this.original, this.text) : []; },
        conflict_diff: function () {
            return this.conflict && this.conflict.show_diff ? line_diff(this.original, this.conflict.current) : [];
        },
        hub: function () { return Conf.child(this.root, "hub"); },
        interfacers: function () { return Conf.child(this.root, "interfacers"); },
        nodes: function () { return Conf.child(this.root, "nodes"); },
        autoconf: function () { return Conf.get(this.hub, "autoconf") == "1"; },
        other_sections: function () {
            return this.root.children.map(function (s) { return s.name; }).filter(function (name) {
                return ["hub", "interfacers", "nodes"].indexOf(name) == -1;
            });
        }
    },
    mounted: function () {
        var self = this;
        $.ajax({ url: path + "config/get", dataType: "text", success: function (text, status, xhr) {
            self.load(text, true, xhr.getResponseHeader("X-Config-Hash"));
        }});
        $.ajax({ url: path + "config/interfacers", dataType: "json", success: function (result) {
            store.types = result.types || [];
            store.settings = result.settings || null;
            store.examples = result.examples || [];
        }, complete: function () { store.loaded = true; } });
        window.addEventListener("beforeunload", function (e) {
            if (self.dirty) {
                e.preventDefault();
                e.returnValue = "";
            }
        });
    },
    methods: {
        // Load text; as_original false keeps it as unsaved edits
        load: function (text, as_original, hash) {
            var root = Conf.parse(text);
            if (!Conf.child(root, "hub")) Conf.add_section(root, "hub");
            if (!Conf.child(root, "interfacers")) Conf.add_section(root, "interfacers");
            this.root = root;
            if (as_original !== false) {
                this.original = text;
                if (hash) this.hash = hash;
                this.conflict = null;
            }
            this.error = null;
            this.errors = [];
        },
        // Config changed on disk, keep unsaved edits if possible
        external_change: function (text, apply, hash) {
            if (!this.dirty) {
                this.load(text, true, hash);
                return;
            }
            this.original = text;
            if (hash) this.hash = hash;
            if (apply) apply(this.root);
        },
        save: function () {
            var self = this;
            var text = this.text;
            this.saving = true;
            this.error = null;
            this.errors = [];
            $.ajax({ type: "POST", url: path + "config/set", data: { config: text, hash: this.hash || "" }, dataType: "json",
                success: function (result) {
                    if (result.success) {
                        self.original = text;
                        self.hash = result.hash;
                        self.conflict = null;
                        self.show_diff = false;
                        self.message = "Saved. EmonHub applies changes automatically.";
                        window.dispatchEvent(new CustomEvent("emonhub-config-saved", { detail: text }));
                    } else if (result.conflict) {
                        self.on_conflict(result.current, result.hash);
                    } else {
                        self.error = result.message;
                        self.errors = result.errors || [];
                        self.show_notice();
                    }
                },
                error: function (xhr) { self.error = "Save failed: " + xhr.status + " " + xhr.statusText; },
                complete: function () { self.saving = false; }
            });
        },
        // File changed on disk since it was loaded
        on_conflict: function (current, hash) {
            var result = analyse_conflict(this.original, current, this.root);
            // Formatting only, such as emonhub rewriting the file: save over it
            if (!result.other && !result.new_nodes.length) {
                this.original = current;
                this.hash = hash;
                this.save();
                return;
            }
            this.conflict = {
                current: current,
                hash: hash,
                new_nodes: result.new_nodes,
                other: result.other,
                show_diff: false
            };
            this.show_notice();
        },
        // Scroll the conflict or error panel into view
        show_notice: function () {
            var self = this;
            this.$nextTick(function () {
                var el = self.$refs.notice;
                if (el && el.scrollIntoView) el.scrollIntoView({ behavior: "smooth", block: "center" });
            });
        },
        // Copy nodes added to the file into the edits, then save
        merge_new_nodes: function () {
            var c = this.conflict;
            if (!Conf.child(this.root, "nodes")) Conf.add_section(this.root, "nodes");
            var nodes = Conf.child(this.root, "nodes");
            c.new_nodes.forEach(function (node) {
                Conf.set_depth(node, 2);
                Conf.trim_end(node);
                node.leading = [{ type: "line", raw: "" }];
                nodes.children.push(node);
            });
            this.original = c.current;
            this.hash = c.hash;
            this.save();
        },
        node_name: function (node) { return Conf.unquote(Conf.get(node, "nodename", "")); },
        // Replace the edits with the file on disk
        reload_current: function () {
            if (confirm("Discard your unsaved changes and load emonhub.conf as it is now?")) {
                this.load(this.conflict.current, true, this.conflict.hash);
            }
        },
        // Write the edits over the changed file
        save_anyway: function () {
            this.original = this.conflict.current;
            this.hash = this.conflict.hash;
            this.save();
        },
        // Text editor view is on the EmonHub page
        open_text_editor: function () {
            if (typeof show_view === "function") show_view("editor");
        },
        discard: function () {
            if (confirm("Discard unsaved changes?")) {
                this.load(this.original);
                this.show_diff = false;
            }
        },
        template_selected: function () {
            var example = this.examples[this.new_interfacer.template];
            if (!example) return;
            var sections = this.example_sections(example.content);
            if (sections.length) this.new_interfacer.name = sections[0].name;
        },
        // Interfacer sections in an example file
        example_sections: function (content) {
            var root = Conf.parse(content);
            var found = [];
            root.children.forEach(function (s) {
                if (s.depth == 2) found.push(s);
                if (s.depth == 1 && s.name == "interfacers") found = found.concat(s.children);
            });
            return found;
        },
        add_interfacer: function () {
            var form = this.new_interfacer;
            var parent = this.interfacers;
            form.notes = [];
            form.error = valid_section_name(form.name, parent, null);
            if (form.error) return;

            var sections = [];
            var example = this.examples[form.template];
            if (example) {
                sections = this.example_sections(example.content);
                if (!sections.length) { form.error = "No interfacer found in " + example.name; return; }
                sections[0].name = form.name;
            } else {
                sections = [Conf.parse("[[" + form.name + "]]\n    Type = \n    [[[init_settings]]]\n    [[[runtimesettings]]]\n        pubchannels = ToEmonCMS,").children[0]];
            }

            sections.forEach(function (section, index) {
                // Unique names for further interfacers in the same example
                var name = section.name;
                for (var n = 2; valid_section_name(name, parent, null); n++) name = section.name + "_" + n;
                section.name = name;
                Conf.set_depth(section, 2);
                Conf.trim_end(section);
                if (!Conf.child(section, "init_settings")) Conf.add_section(section, "init_settings");
                if (!Conf.child(section, "runtimesettings")) Conf.add_section(section, "runtimesettings");
                section.leading = [{ type: "line", raw: "" }];
                if (example) {
                    tidy_interfacer(section).forEach(function (note) { form.notes.push(section.name + ": " + note); });
                }
                parent.children.push(section);
            });
            form.name = "";
            form.template = "";
            this.message = null;
        },
        add_node: function () {
            var form = this.new_node;
            if (!this.nodes) Conf.add_section(this.root, "nodes");
            form.error = valid_section_name(form.id, this.nodes, null);
            if (!form.error && !/^\d+$/.test(form.id)) form.error = "Node ID must be a number";
            if (form.error) return;
            var node = Conf.add_section(this.nodes, form.id);
            node.leading = [{ type: "line", raw: "" }];
            if (form.name) Conf.set(node, "nodename", encode(form.name));
            Conf.add_section(node, "rx");
            form.id = "";
            form.name = "";
        }
    }
});

config_ui.component("value-editor", ValueEditor);
config_ui.component("settings-table", SettingsTable);
config_ui.component("interfacer-card", InterfacerCard);
config_ui.component("inputs-table", InputsTable);
config_ui.component("node-card", NodeCard);
config_ui = config_ui.mount("#config-ui");
