/*
  emonhub.conf parser and serialiser (ConfigObj format)

  Comments, blank lines and inline comments are kept. Unchanged lines are
  written back as read, so parse() followed by serialize() returns the
  original text. Edited settings and sections are rewritten individually.

  Section: { id, name, depth, header, header_dirty, leading: [Line], items: [Line|Setting], children: [Section] }
  Setting: { type: 'kv', key, value, comment, indent, raw, dirty }
  Line:    { type: 'line', raw }
*/

var EmonHubConf = (function () {

    var next_id = 1;
    var INDENT = "    ";

    function new_section(name, depth) {
        return { id: next_id++, name: name, depth: depth, header: null, header_dirty: false, leading: [], items: [], children: [] };
    }

    // Split "value  # comment" into value and comment, ignoring # inside quotes
    function split_comment(text) {
        var quote = null;
        for (var i = 0; i < text.length; i++) {
            var c = text[i];
            if (quote) {
                if (c == quote) quote = null;
            } else if (c == '"' || c == "'") {
                quote = c;
            } else if (c == '#') {
                var start = i;
                while (start > 0 && /\s/.test(text[start - 1])) start--;
                return { value: text.substring(0, start).trim(), comment: text.substring(start) };
            }
        }
        return { value: text.trim(), comment: "" };
    }

    // Lines that can move to the next section: blank lines and unindented comments
    function is_moveable(line) {
        return line.type == 'line' && (line.raw.trim() == "" || line.raw[0] == '#');
    }

    function parse(text) {
        var root = new_section("", 0);
        var stack = [root];
        var lines = text.split("\n");

        // Final newline is restored by serialize()
        root.final_newline = lines.length > 1 && lines[lines.length - 1] === "";
        if (root.final_newline) lines.pop();

        for (var i = 0; i < lines.length; i++) {
            var raw = lines[i];
            var current = stack[stack.length - 1];

            // Section header
            var h = raw.match(/^(\s*)(\[+)\s*(.*?)\s*(\]+)\s*(#.*)?$/);
            if (h && h[2].length == h[4].length) {
                var depth = h[2].length;
                var name = h[3].replace(/^(["'])(.*)\1$/, "$2");
                var section = new_section(name, depth);
                section.header = raw;

                // Trailing comments and blank lines belong to the next header
                var moved = [];
                while (current.items.length && is_moveable(current.items[current.items.length - 1])) {
                    moved.unshift(current.items.pop());
                }
                section.leading = moved;

                while (stack.length > 1 && stack[stack.length - 1].depth >= depth) stack.pop();
                stack[stack.length - 1].children.push(section);
                stack.push(section);
                continue;
            }

            // Setting
            var kv = raw.match(/^(\s*)([^=#\s\[][^=]*?)\s*=(.*)$/);
            if (kv) {
                var rest = kv[3];
                var v = rest.trim();
                var triple = v.substring(0, 3);

                // Multi-line triple quoted value
                if ((triple == '"""' || triple == "'''") && v.indexOf(triple, 3) == -1) {
                    var block = [raw];
                    while (i + 1 < lines.length) {
                        i++;
                        block.push(lines[i]);
                        if (lines[i].indexOf(triple) != -1) break;
                    }
                    current.items.push({ id: next_id++, type: 'kv', key: kv[2], value: block.join("\n").substring(raw.length - rest.length).trim(), comment: "", indent: kv[1], raw: block.join("\n"), dirty: false, multiline: true });
                    continue;
                }

                var parts = split_comment(rest);
                current.items.push({ id: next_id++, type: 'kv', key: kv[2], value: parts.value, comment: parts.comment, indent: kv[1], raw: raw, dirty: false });
                continue;
            }

            // Comment, blank or unrecognised line
            current.items.push({ type: 'line', raw: raw });
        }
        return root;
    }

    function serialize_section(section, out) {
        section.leading.forEach(function (l) { out.push(l.raw); });

        if (section.depth > 0) {
            if (section.header_dirty || section.header === null) {
                var open = "[".repeat(section.depth);
                var close = "]".repeat(section.depth);
                out.push(INDENT.repeat(section.depth - 1) + open + section.name + close);
            } else {
                out.push(section.header);
            }
        }

        section.items.forEach(function (item) {
            if (item.type == 'kv' && item.dirty) {
                out.push(item.indent + item.key + " = " + item.value + item.comment);
            } else {
                out.push(item.raw);
            }
        });

        section.children.forEach(function (child) { serialize_section(child, out); });
    }

    function serialize(root) {
        var out = [];
        serialize_section(root, out);
        return out.join("\n") + (root.final_newline ? "\n" : "");
    }

    // ---------------------------------------------------------------------
    // Section helpers
    // ---------------------------------------------------------------------

    function child(section, name) {
        if (!section) return null;
        for (var i = 0; i < section.children.length; i++) {
            if (section.children[i].name == name) return section.children[i];
        }
        return null;
    }

    function settings(section) {
        return section.items.filter(function (item) { return item.type == 'kv'; });
    }

    function find(section, key) {
        if (!section) return null;
        for (var i = 0; i < section.items.length; i++) {
            if (section.items[i].type == 'kv' && section.items[i].key == key) return section.items[i];
        }
        return null;
    }

    function get(section, key, fallback) {
        var item = find(section, key);
        return item ? item.value : (fallback === undefined ? null : fallback);
    }

    function default_indent(section) {
        var existing = settings(section);
        if (existing.length && !existing[0].multiline) return existing[0].indent;
        return INDENT.repeat(section.depth);
    }

    function set(section, key, value) {
        var item = find(section, key);
        if (item) {
            if (item.value === value) return item;
            item.value = value;
            item.dirty = true;
            item.multiline = false;
            return item;
        }
        item = { id: next_id++, type: 'kv', key: key, value: value, comment: "", indent: default_indent(section), raw: "", dirty: true };

        // Insert after last setting, or at start of section
        var pos = 0;
        for (var i = 0; i < section.items.length; i++) {
            if (section.items[i].type == 'kv') pos = i + 1;
        }
        section.items.splice(pos, 0, item);
        return item;
    }

    // Replace a comment line, such as "# key = value", with a setting
    function replace_line(section, line, key, value) {
        var pos = section.items.indexOf(line);
        var m = line.raw.match(/^(\s*)/);
        var item = { id: next_id++, type: 'kv', key: key, value: value, comment: "", indent: m[1] || default_indent(section), raw: "", dirty: true };
        if (pos == -1) section.items.push(item);
        else section.items.splice(pos, 1, item);
        return item;
    }

    function remove(section, key) {
        section.items = section.items.filter(function (item) {
            return !(item.type == 'kv' && item.key == key);
        });
    }

    function add_section(parent, name, depth) {
        var section = new_section(name, depth || parent.depth + 1);
        parent.children.push(section);
        return section;
    }

    function remove_section(parent, section) {
        parent.children = parent.children.filter(function (s) { return s !== section; });
    }

    function rename_section(section, name) {
        if (section.name === name) return;
        section.name = name;
        section.header_dirty = true;
    }

    // Set depth of a section tree, used when moving a parsed snippet into place
    // Remove blank lines at the end of a section tree
    function trim_end(section) {
        var last = section;
        while (last.children.length) last = last.children[last.children.length - 1];
        while (last.items.length && last.items[last.items.length - 1].type == 'line' && last.items[last.items.length - 1].raw.trim() == "") {
            last.items.pop();
        }
        return section;
    }

    function set_depth(section, depth) {
        var shift = depth - section.depth;
        var walk = function (s) {
            s.depth += shift;
            s.header_dirty = true;
            s.items.forEach(function (item) {
                if (item.type == 'kv') {
                    item.indent = INDENT.repeat(s.depth);
                    item.dirty = true;
                } else if (item.raw.trim() != "") {
                    item.raw = INDENT.repeat(s.depth) + item.raw.trim();
                }
            });
            s.children.forEach(walk);
        };
        walk(section);
        return section;
    }

    // ---------------------------------------------------------------------
    // Value helpers
    // ---------------------------------------------------------------------

    // "a, b, c" or "a," to array
    function parse_list(value) {
        if (value === null || value === undefined) return [];
        return value.split(",").map(function (s) { return unquote(s.trim()); }).filter(function (s) { return s !== ""; });
    }

    // Array to "a, b, c", single item lists keep the trailing comma
    function format_list(list) {
        list = list.map(function (s) { return quote(String(s).trim()); });
        if (list.length == 0) return ",";
        if (list.length == 1) return list[0] + ",";
        return list.join(", ");
    }

    function unquote(value) {
        if (value === null || value === undefined) return "";
        var m = value.match(/^(["'])(.*)\1$/);
        return m ? m[2] : value;
    }

    // Quote values that ConfigObj would otherwise misread
    function quote(value) {
        if (value === "" || /^\s|\s$|[#,"]/.test(value)) {
            if (value.indexOf('"') == -1) return '"' + value + '"';
            if (value.indexOf("'") == -1) return "'" + value + "'";
        }
        return value;
    }

    return {
        parse: parse,
        serialize: serialize,
        child: child,
        settings: settings,
        find: find,
        get: get,
        set: set,
        replace_line: replace_line,
        split_comment: split_comment,
        remove: remove,
        add_section: add_section,
        remove_section: remove_section,
        rename_section: rename_section,
        set_depth: set_depth,
        trim_end: trim_end,
        parse_list: parse_list,
        format_list: format_list,
        unquote: unquote,
        quote: quote
    };
})();

if (typeof module !== 'undefined') module.exports = EmonHubConf;
