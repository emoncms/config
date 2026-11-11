#!/usr/bin/env python3
"""
Check that emonhub.conf text parses as emonhub reads it.

Usage: validate_config.py < emonhub.conf

Output: JSON {"valid": true} or {"valid": false, "errors": ["..."]}

Uses ConfigObj with the same defaults as emonhub_setup.py, so syntax errors,
duplicate keys and duplicate sections are reported before the file is written.
"""

import json
import sys

from configobj import ConfigObj, ConfigObjError


def main():
    text = sys.stdin.read()
    try:
        ConfigObj(text.splitlines())
    except ConfigObjError as e:
        errors = [str(err) for err in getattr(e, "errors", [])] or [str(e)]
        json.dump({"valid": False, "errors": errors}, sys.stdout)
        return
    except Exception as e:
        json.dump({"valid": False, "errors": [str(e)]}, sys.stdout)
        return
    json.dump({"valid": True}, sys.stdout)


if __name__ == "__main__":
    main()
