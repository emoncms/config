#!/usr/bin/env python3
"""
List settings used by each emonhub interfacer, read from the emonhub source.

Usage: interfacer_settings.py <emonhub src dir>

Output: JSON {
    "common": {key: default},
    "types": {Type: {"init": {key: default}, "required": [key], "runtime": {key: default},
                     "common": [key], "descriptions": {"init": {}, "runtime": {}}}},
    "descriptions": {"common": {}, "hub": {}, "node": {}, "rx": {}, "tx": {}}
}

Init settings are the __init__ arguments. Runtime settings are the keys of
dicts assigned to or updated into self._defaults and self._*settings*, and
constant keys read from them, merged with those of base classes. Keys only
copied into self._settings from __init__ arguments are not runtime settings.
Common settings come from the EmonHubInterfacer base class and are not
repeated per type. Each type lists the common settings it uses: those its own
code reads, and those read by base class methods (run, action, flush,
_process_rx, _process_tx) that the type reaches.

Descriptions come from settings_info dicts in the emonhub source: on each
interfacer class, on EmonHubInterfacer for common settings, and at module
level in emonhub_setup.py for hub and node settings.
"""

import ast
import json
import os
import sys


def literal(node):
    try:
        return ast.literal_eval(node)
    except Exception:
        return None


def settings_attr(node):
    """Attribute name if node is self.<name> holding settings"""
    if isinstance(node, ast.Attribute) and isinstance(node.value, ast.Name) and node.value.id == "self":
        name = node.attr
        if name == "init_settings":
            return None
        if name == "_defaults" or "settings" in name:
            return name
    return None


def dict_keys(node):
    out = {}
    if isinstance(node, ast.Dict):
        for k, v in zip(node.keys, node.values):
            if isinstance(k, ast.Constant) and isinstance(k.value, str):
                out[k.value] = literal(v)
    return out


def settings_info(body):
    """settings_info = {...} in a class or module body, or None"""
    for node in body:
        if isinstance(node, ast.Assign) and any(isinstance(t, ast.Name) and t.id == "settings_info" for t in node.targets):
            value = literal(node.value)
            return value if isinstance(value, dict) else None
    return None


def read_class(cls):
    # copied: keys written straight into self._settings, often init arguments
    # methods: methods defined, calls: self.method() calls, reads: setting keys read
    info = {"bases": [], "init": None, "required": [], "runtime": {}, "copied": {},
            "methods": set(), "extends": set(), "calls": set(), "reads": set(),
            "descriptions": settings_info(cls.body) or {}}
    for base in cls.bases:
        if isinstance(base, ast.Name):
            info["bases"].append(base.id)
        elif isinstance(base, ast.Attribute):
            info["bases"].append(base.attr)

    for item in cls.body:
        if not isinstance(item, ast.FunctionDef):
            continue
        info["methods"].add(item.name)
        # Method that calls super().<same name>() extends the base method
        for node in ast.walk(item):
            if isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) and node.func.attr == item.name \
                    and isinstance(node.func.value, ast.Call) and isinstance(node.func.value.func, ast.Name) \
                    and node.func.value.func.id == "super":
                info["extends"].add(item.name)

        if item.name == "__init__":
            args = item.args.args[1:]
            defaults = item.args.defaults
            offset = len(args) - len(defaults)
            info["init"] = {}
            for i, arg in enumerate(args):
                if arg.arg == "name":
                    continue
                if i >= offset:
                    info["init"][arg.arg] = literal(defaults[i - offset])
                else:
                    info["init"][arg.arg] = None
                    info["required"].append(arg.arg)

        for node in ast.walk(item):
            # self._x_settings = {...}
            if isinstance(node, ast.Assign) and isinstance(node.value, ast.Dict):
                for target in node.targets:
                    attr = settings_attr(target)
                    if attr:
                        dest = "copied" if attr == "_settings" else "runtime"
                        info[dest].update(dict_keys(node.value))
            # self._x_settings.update({...})
            if isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) and node.func.attr == "update":
                attr = settings_attr(node.func.value)
                if attr and node.args:
                    dest = "copied" if attr == "_settings" else "runtime"
                    info[dest].update(dict_keys(node.args[0]))
            # self.method() call
            if isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) \
                    and isinstance(node.func.value, ast.Name) and node.func.value.id == "self":
                info["calls"].add(node.func.attr)
            # self._x_settings['key'] read
            if isinstance(node, ast.Subscript) and isinstance(node.ctx, ast.Load) and settings_attr(node.value):
                key = node.slice
                if isinstance(key, ast.Constant) and isinstance(key.value, str):
                    info["runtime"].setdefault(key.value, None)
                    info["reads"].add(key.value)
            # 'key' in self._x_settings
            if isinstance(node, ast.Compare) and len(node.ops) == 1 and isinstance(node.ops[0], ast.In):
                if isinstance(node.left, ast.Constant) and isinstance(node.left.value, str) and settings_attr(node.comparators[0]):
                    info["runtime"].setdefault(node.left.value, None)
                    info["reads"].add(node.left.value)
            # self._x_settings.get('key')
            if isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) and node.func.attr == "get" \
                    and settings_attr(node.func.value) and node.args \
                    and isinstance(node.args[0], ast.Constant) and isinstance(node.args[0].value, str):
                info["reads"].add(node.args[0].value)
    return info


def main():
    src = sys.argv[1] if len(sys.argv) > 1 else "/opt/openenergymonitor/emonhub/src"
    classes = {}

    files = [os.path.join(src, "emonhub_interfacer.py")]
    idir = os.path.join(src, "interfacers")
    files += [os.path.join(idir, f) for f in sorted(os.listdir(idir)) if f.endswith(".py")]

    for path in files:
        try:
            with open(path) as fh:
                tree = ast.parse(fh.read())
        except Exception:
            continue
        for node in tree.body:
            if isinstance(node, ast.ClassDef):
                classes[node.name] = read_class(node)

    def resolve(name, seen=()):
        info = classes.get(name)
        if not info or name in seen:
            return {"init": {}, "required": [], "runtime": {}, "copied": {}}
        result = {"init": None, "required": [], "runtime": {}, "copied": {}}
        for base in info["bases"]:
            parent = resolve(base, seen + (name,))
            result["runtime"].update(parent["runtime"])
            result["copied"].update(parent["copied"])
            if result["init"] is None and parent["init"]:
                result["init"] = parent["init"]
                result["required"] = parent["required"]
        result["copied"].update(info["copied"])
        # Declared defaults take precedence over keys found only as reads
        for key, value in info["runtime"].items():
            if value is not None or key not in result["runtime"]:
                result["runtime"][key] = value
        if info["init"] is not None:
            result["init"] = info["init"]
            result["required"] = info["required"]
        result["init"] = result["init"] or {}
        return result

    BASE = "EmonHubInterfacer"

    def chain(name, seen=()):
        """Class and its bases, most derived first"""
        if name not in classes or name in seen:
            return []
        out = [name]
        for b in classes[name]["bases"]:
            out += [c for c in chain(b, seen + (name,)) if c not in out]
        return out

    def common_used(name, common):
        """Common settings used by a type"""
        classes_up = chain(name)
        own = [c for c in classes_up if c != BASE]

        def overridden(method):
            """Replaced in the type, not extended with a super() call"""
            for c in own:
                if method in classes[c]["methods"]:
                    return method not in classes[c]["extends"]
            return False

        calls = set().union(*[classes[c]["calls"] for c in own]) if own else set()
        reads = set().union(*[classes[c]["reads"] for c in own]) if own else set()

        run_base = not overridden("run")
        has_read = overridden("read")
        has_post = overridden("_process_post")
        rx = (run_base and has_read) or "_process_rx" in calls
        tx = "_process_tx" in calls
        action_path = (run_base or "action" in calls) and not overridden("action")
        flush_path = (action_path or "flush" in calls) and not overridden("flush") and has_post

        used = set(k for k in reads if k in common)
        if run_base and has_read:
            used.add("pubchannels")
        if run_base and (overridden("add") or has_post):
            used.add("subchannels")
        if rx or tx:
            used.update(["datacode", "scale"])
        if rx:
            used.add("nodelistonly")
        if action_path and has_post:
            used.update(["pause", "interval"])
        if flush_path:
            used.add("batchsize")
        return sorted(used)

    def descriptions_of(name):
        """Descriptions of a type, inherited from base classes"""
        out = {"init": {}, "runtime": {}}
        for c in reversed([c for c in chain(name) if c != BASE]):
            for part in out:
                out[part].update(classes[c]["descriptions"].get(part, {}))
        return out

    base = resolve(BASE)
    common = dict(base["copied"])
    common.update(base["runtime"])
    types = {}
    for name in classes:
        if name.startswith("Emon") and name.endswith("Interfacer") and name != "EmonHubInterfacer":
            result = resolve(name)
            # Keys copied into _settings are runtime settings unless they are
            # init arguments. Common settings are kept only where the type
            # changes the default.
            for key, value in result.pop("copied").items():
                if key not in result["init"]:
                    result["runtime"].setdefault(key, value)
            for key in list(result["runtime"]):
                if key in common and result["runtime"][key] == common[key]:
                    del result["runtime"][key]
            result["common"] = common_used(name, common)
            result["descriptions"] = descriptions_of(name)
            types[name] = result
    descriptions = {"common": classes.get(BASE, {}).get("descriptions", {}).get("runtime", {})}
    try:
        with open(os.path.join(src, "emonhub_setup.py")) as fh:
            descriptions.update(settings_info(ast.parse(fh.read()).body) or {})
    except Exception:
        pass
    json.dump({"common": common, "types": types, "descriptions": descriptions}, sys.stdout, default=str)


if __name__ == "__main__":
    main()
