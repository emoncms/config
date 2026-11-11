/*
  Descriptions of emonhub.conf settings, shown in the config UI.
  Checked against the emonhub source (src/emonhub.py, src/emonhub_interfacer.py,
  src/emonhub_auto_conf.py and src/interfacers/*.py).

  common: runtime settings handled by the EmonHubInterfacer base class
  hub, node, rx, tx: [hub] and [nodes] sections
  types: per interfacer type, split into init and runtime
*/

var SETTING_DESCRIPTIONS = {

    common: {
        pause: "Stops sending data on when set to out or all. Values: off, in, out, all. in is accepted but currently has no effect.",
        interval: "Minimum time between sends from the buffer, in seconds. 0 sends on every loop.",
        datacode: "Default datacode for decoding nodes that have no datacode or datacodes under [nodes]. 0 passes values through without decoding.",
        scale: "Default scale for nodes that have no scale or scales under [nodes].",
        timestamped: "Socket interfacer only. Expects a Unix timestamp as the first value of each received line. true or false.",
        targeted: "Socket and PacketGen only. Expects a target node ID after the source node ID. true or false. No effect on other interfacers.",
        nodeoffset: "Number added to the node ID of received data. Used by serial and radio interfacers, Pulse, BLE and Socket.",
        pubchannels: "Channels this interfacer sends received data to. With no channels the interfacer does not read data.",
        subchannels: "Channels this interfacer takes data from, to send on to its destination.",
        batchsize: "Maximum number of buffered items sent in one go. Capped at the interfacer's own limit.",
        nodelistonly: "Discards data from nodes not listed under [nodes]. true or false."
    },

    hub: {
        loglevel: "Detail written to emonhub.log: DEBUG, INFO, WARNING, ERROR or CRITICAL. Applied without a restart.",
        autoconf: "Adds known nodes to [nodes] from templates in available.conf when they first send data. 1 on, 0 off. Restart emonhub after changing.",
        use_syslog: "Also sends log messages to syslog when set to yes. Read at start-up, so restart emonhub after changing.",
        log_backup_count: "Number of old log files kept when emonhub.log is rotated. Default 1.",
        log_max_bytes: "Size at which emonhub.log is rotated, in bytes. Default 5120000."
    },

    node: {
        nodename: "Name used for this node's inputs in emoncms, in place of the node ID.",
        nodeids: "Only used in autoconf templates in available.conf to match node IDs. No effect here.",
        datalength: "Only used in autoconf templates in available.conf to match data length. No effect here."
    },

    rx: {
        names: "Input names, one per value, in the order the values are received.",
        datacodes: "Datacode for each value, giving its binary type. Total size must match the received data length or the frame is discarded.",
        datacode: "Single datacode used for every value when datacodes is not set.",
        scales: "Multiplier for each value, e.g. 0.01 turns 2345 into 23.45. Count must match the number of values.",
        scale: "Single multiplier used for every value when scales is not set.",
        units: "Unit for each value. Not read by emonhub at present, kept for reference and node templates.",
        whitening: "Undoes radio data whitening by XOR with 0x55 on each byte. Set to 1 to enable."
    },

    tx: {
        names: "Names of the values sent to this node. Not read by emonhub at present, kept for reference.",
        datacodes: "Datacode for each value sent, giving its binary type.",
        datacode: "Single datacode used for every value sent when datacodes is not set.",
        scales: "Divisor for each value before sending, the reverse of the rx scales.",
        scale: "Single divisor used for every value sent when scales is not set.",
        units: "Unit for each value. Not read by emonhub at present, kept for reference."
    },

    types: {
        "EmonHubBMWInterfacer": {
            "init": {
                "bmwapiusername": "Username for the BMW ConnectedDrive website.",
                "bmwapipassword": "Password for the BMW ConnectedDrive website.",
                "tempcredentialfile": "File that caches the API access token across restarts. Default /tmp/bmwcredentials.json.",
                "timeinverval": "Seconds between API reads while the car is not charging. Default 600.",
                "duringchargetimeinterval": "Seconds between API reads while the car is charging. Default 60.",
                "nodeid": "Node id for the car inputs. Default 28."
            },
            "runtime": {}
        },
        "EmonHubBleInterfacer": {
            "init": {
                "device_addr": "Bluetooth LE MAC address of the sensor, e.g. 00:0b:57:64:8c:a2. Required."
            },
            "runtime": {
                "read_interval": "Time between readings of temperature, humidity and battery level in seconds. Default 60."
            }
        },
        "EmonHubDS18B20Interfacer": {
            "init": {},
            "runtime": {
                "read_interval": "Time between readings in seconds, aligned to the clock. Default 10.",
                "nodename": "Node name for the temperature inputs. Default sensors.",
                "ids": "Comma-separated sensor ids, e.g. 28-000008e2db06. Paired by position with names. Unlisted sensors use their id as input name.",
                "names": "Comma-separated input names, in the same order as ids."
            }
        },
        "EmonHubDigitalInputInterfacer": {
            "init": {
                "pins": "GPIO pins to read, BOARD (physical) numbering, comma-separated. Add a trailing comma for a single pin, e.g. 13,",
                "invert": "1 inverts the reported pin state, 0 reports it as read. Default 0."
            },
            "runtime": {
                "pins": "Set from init_settings, changing it here has no effect.",
                "nodename": "Currently has no effect. Node name is fixed at gpio.",
                "read_interval": "Currently has no effect. Pins are read every 10 seconds.",
                "invert": "Set from init_settings, changing it here has no effect."
            }
        },
        "EmonHubEconet300Interfacer": {
            "init": {
                "host": "Hostname or IP address of the ecoNET 300 bridge. Required.",
                "username": "HTTP basic auth username for the bridge. Required.",
                "password": "HTTP basic auth password for the bridge. Required.",
                "pollinterval": "Seconds between successful polls. After an error the bridge is polled again in 10 seconds. Required.",
                "nodeid": "Node id for the heat pump inputs. Default 30."
            },
            "runtime": {}
        },
        "EmonHubEconextInterfacer": {
            "init": {
                "host": "Hostname or IP address of the econext-gateway. Overridden by the runtime setting of the same name.",
                "port": "Gateway HTTP port. Default 8000. Overridden by the runtime setting of the same name.",
                "pollinterval": "Seconds between successful polls. Default 60. Overridden by the runtime setting of the same name.",
                "nodeid": "Node id for the heat pump inputs. Default 30. Overridden by the runtime setting of the same name."
            },
            "runtime": {
                "host": "Hostname or IP address of the econext-gateway. Falls back to the init_settings value. Nothing is read until set.",
                "port": "Gateway HTTP port. Falls back to the init_settings value, default 8000.",
                "pollinterval": "Seconds between successful polls. Falls back to the init_settings value, default 60. Failed polls retry after 10 to 60 seconds.",
                "nodeid": "Node id for the heat pump inputs. Falls back to the init_settings value, default 30.",
                "nodename": "Node name for the heat pump inputs. Defaults to the interfacer name.",
                "timeout": "HTTP request timeout in seconds. Default 10."
            }
        },
        "EmonHubEmoncmsHTTPInterfacer": {
            "init": {},
            "runtime": {
                "interval": "Seconds between bulk posts to emoncms. Default 30. Repeated failures back off up to 600 seconds.",
                "batchsize": "Maximum number of buffered frames sent in one post. Default 1000, upper limit 1000.",
                "apikey": "Emoncms write API key, 32 characters. Buffered data is discarded while no valid key is set.",
                "url": "Emoncms server address, starting with http or https. Default http://emoncms.org.",
                "senddata": "Set to 1 to post input data to <url>/input/bulk.json. Default 1.",
                "sendstatus": "Set to 1 to ping the emoncms myip module with the hub's address. Only sent when senddata is 0. Default 0.",
                "sendnames": "Set to 1 to send node names and input names with each value. Enables compress. Default 0.",
                "compress": "Set to 1 to zlib compress the post body when this reduces its size. Default 0."
            }
        },
        "EmonHubGoodWeInterfacer": {
            "init": {},
            "runtime": {
                "name": "Node name for the inverter inputs in emoncms. Default goodwe.",
                "ip": "Inverter IP address on the local network. Nothing is read until set.",
                "port": "Inverter UDP port. Default 8899.",
                "timeout": "Seconds to wait for each inverter reply. Default 2. Value is passed on as text, so setting it may stop readings.",
                "retries": "Number of retries when the inverter does not reply. Default 3. Value is passed on as text, so setting it may stop readings.",
                "readinterval": "Seconds between readings. Default 10."
            }
        },
        "EmonHubGraphiteInterfacer": {
            "init": {},
            "runtime": {
                "interval": "Seconds between posts to Graphite. Default 30.",
                "batchsize": "Maximum number of buffered frames sent in one post. Default 100, upper limit 250.",
                "graphite_host": "Graphite server hostname or IP address. Default localhost. Do not add a trailing comma.",
                "graphite_port": "Graphite plaintext TCP port. Default 2003.",
                "prefix": "First part of each metric path, e.g. emonpi.emontx.power1. Default emonpi."
            }
        },
        "EmonHubInfluxInterfacer": {
            "init": {
                "influx_host": "InfluxDB server hostname or IP address. Default localhost.",
                "influx_interval": "Default seconds between posts to InfluxDB. A runtime interval setting overrides it. Default 30.",
                "influx_port": "InfluxDB HTTP port. Default 8086, set it explicitly as the built-in default causes an error.",
                "influx_user": "InfluxDB username. Default emoncms.",
                "influx_passwd": "InfluxDB password. Default emoncmspw.",
                "influx_db": "InfluxDB 1.x database to write to. Default emoncms."
            },
            "runtime": {
                "batchsize": "Maximum number of buffered frames sent in one post. Default 100, upper limit 250.",
                "prefix": "Currently has no effect. Prefix tag is always written as 'prefix'."
            }
        },
        "EmonHubJaguarLandRoverInterfacer": {
            "init": {
                "jlrusername": "Username for the Jaguar Land Rover InControl account. Requires the jlrpy Python module.",
                "jlrpassword": "Password for the Jaguar Land Rover InControl account.",
                "timeinverval": "Seconds between API reads while the car is not charging. Default 600.",
                "duringchargetimeinterval": "Seconds between API reads while the car is charging. Default 60.",
                "nodeid": "Node id for the car inputs. Default 28."
            },
            "runtime": {}
        },
        "EmonHubJeeInterfacer": {
            "init": {
                "com_port": "Serial port of the RFM69Pi or emonPi radio board, e.g. /dev/ttyAMA0. Default /dev/ttyAMA0.",
                "com_baud": "Serial baud rate of the radio board. 38400 for RFM69Pi and emonPi, 9600 for old RFM12Pi boards. Default 38400."
            },
            "runtime": {
                "datacode": "Default datacode for decoding radio packets from nodes without datacodes in [nodes]. Accepts 0, b, B, h, H, l, L or f. Default h.",
                "baseid": "Radio node id of the receiving board, 1 to 26. Default 15.",
                "frequency": "Radio frequency in MHz: 433, 868 or 915. Must match the other nodes. Default 433.",
                "group": "Radio network group, 0 to 250. Must match the other nodes. Default 210.",
                "quiet": "true stops the radio board reporting corrupted packets, false reports them. Default true.",
                "calibration": "Mains voltage calibration sent to the radio board: 230V (UK/EU) or 110V (US). Default 230V."
            }
        },
        "EmonHubMBUSInterfacer": {
            "init": {
                "device": "Serial device of the M-Bus reader, e.g. /dev/ttyUSB0. Trailing * matches any port starting with that path. Default /dev/ttyUSB0.",
                "device_vid": "Optional USB vendor ID filter, as a decimal number, e.g. 1659.",
                "device_pid": "Optional USB product ID filter, as a decimal number.",
                "baud": "M-Bus baud rate, typically 2400 or 4800. Default 2400.",
                "use_meterbus_lib": "Parse frames with the pyMeterBus library when set. Any non-empty value enables it, including false. Leave unset to disable."
            },
            "runtime": {
                "read_interval": "Seconds between readings, aligned to the clock. Default 10.",
                "nodename": "Node name for published inputs. Default MBUS.",
                "validate_checksum": "Discard frames with a bad checksum. Only the exact value False disables it. Default True.",
                "meters": "Subsection with one [[[[[name]]]]] block per meter, each with address (default 1) and type: standard, qalcosonic_e3, sontex531, sdm120, kamstrup403 or kamstrup402."
            }
        },
        "EmonHubMinimalModbusInterfacer": {
            "init": {
                "device": "Serial device of the RS485 adapter, e.g. /dev/ttyUSB0. Trailing * matches any port starting with that path. Default /dev/modbus.",
                "device_vid": "Optional USB vendor ID filter, as a decimal number.",
                "device_pid": "Optional USB product ID filter, as a decimal number.",
                "baud": "Modbus baud rate, e.g. 2400 or 9600. Default 2400.",
                "parity": "Serial parity: none, even, odd, mark or space. Default none.",
                "datatype": "Default register type: float (32-bit over two registers, function code 4) or int (signed 16-bit, function code 3). Default float."
            },
            "runtime": {
                "read_interval": "Seconds between readings, aligned to the clock. Default 10.",
                "nodename": "Node name for published inputs. Default sdm120.",
                "prefix": "Text added before each input name, which is prefix + meter name + _ + register name. Default empty.",
                "meters": "Subsection with one [[[[[name]]]]] block per meter: address, registers, names, precision, scales, datatypes, functioncodes, byteorder."
            }
        },
        "EmonHubModbusRenogyInterfacer": {
            "init": {
                "com_port": "Serial port of the Renogy Rover USB cable. Default /dev/ttyUSB0.",
                "com_baud": "Modbus serial baud rate, typically 9600. Set it explicitly, as the built-in default fails to connect.",
                "toextract": "Currently has no effect. Values read are fixed: BatteryPercent, Charging_Stage, BatteryTemp_F, SolarVoltage, SolarCurrent, SolarPower, PowerGenToday.",
                "poll_interval": "Currently has no effect. Use the interval runtime setting to set the time between readings."
            },
            "runtime": {}
        },
        "EmonHubMqttInterfacer": {
            "init": {
                "mqtt_user": "MQTT broker username.",
                "mqtt_passwd": "MQTT broker password.",
                "mqtt_host": "MQTT broker address. Default 127.0.0.1.",
                "mqtt_port": "MQTT broker port. Default 1883, MQTTS usually uses 8883.",
                "mqtt_tls_enabled": "Connect over TLS (MQTTS). Accepts true, 1 or yes. Default false.",
                "mqtt_tls_ca_certs": "Path to CA certificate file for server verification. Leave blank to use system CAs.",
                "mqtt_tls_certfile": "Path to client certificate file, needed for mutual TLS authentication only.",
                "mqtt_tls_keyfile": "Path to client private key file, needed for mutual TLS authentication only.",
                "mqtt_tls_insecure": "Skip server hostname verification when true. Not recommended. Default false."
            },
            "runtime": {
                "node_format_enable": "Set to 1 to publish all values of a node as one comma-separated payload, e.g. emonhub/rx/10/values. Default 1.",
                "node_format_basetopic": "Base topic for node format, with trailing slash. Default emonhub/. Also subscribes to <basetopic>tx/# for data to send out on pubchannels.",
                "nodevar_format_enable": "Set to 1 to publish each value on its own topic, e.g. emon/emontx/power1. Used by the emoncms MQTT input. Default 0.",
                "nodevar_format_basetopic": "Base topic for node variable format, with trailing slash. Default nodes/, emoncms expects emon/.",
                "node_JSON_enable": "Set to 1 to publish each node as one JSON object with names, values and time, e.g. emon/emontx. Default 0.",
                "node_JSON_basetopic": "Base topic for JSON format, with trailing slash. Topic is basetopic plus node name. Default emon/."
            }
        },
        "EmonHubOEMInterfacer": {
            "init": {
                "com_port": "Serial port of the connected device, e.g. /dev/ttyAMA0 for the emonPi or /dev/ttyUSB0 for a USB device. Default /dev/ttyAMA0.",
                "com_baud": "Serial baud rate. Use 115200 for current OpenEnergyMonitor firmware, 38400 for older radio boards. Default 38400."
            },
            "runtime": {
                "datacode": "Default datacode for decoding space-separated byte frames from nodes without datacodes in [nodes]. Default h. Ignored once JSON or key:value data is received.",
                "nodename": "Node name for JSON and key:value data. Space-separated frames use the node id in the frame. Defaults to the interfacer name."
            }
        },
        "EmonHubPacketGenInterfacer": {
            "init": {},
            "runtime": {
                "interval": "Seconds between requests for the polling interval set in the emoncms PacketGen module. 0 disables them. Default 5.",
                "datacode": "Datacode used to decode packet values. Default b (signed byte).",
                "apikey": "Emoncms API key for PacketGen module requests, 32 characters.",
                "url": "Emoncms server root, e.g. http://localhost. /emoncms/packetgen/ is appended to it. Default http://localhost/emoncms."
            }
        },
        "EmonHubPulseCounterInterfacer": {
            "init": {
                "pulse_pin": "GPIO pin of the pulse sensor, BOARD (physical) numbering. Required. Use a separate interfacer for each sensor.",
                "bouncetime": "Debounce time in ms. Pulses closer together than this are ignored. Default 1.",
                "rate_limit": "Minimum time between posts in seconds. Posts only when the count has changed. Default 2."
            },
            "runtime": {
                "pulse_pin": "Set from init_settings, changing it here has no effect.",
                "bouncetime": "Set from init_settings, changing it here has no effect.",
                "rate_limit": "Set from init_settings, changing it here has no effect."
            }
        },
        "EmonHubRF69Interfacer": {
            "init": {
                "nodeid": "Radio node id of this receiver, 1 to 63. Use 63 to receive packets for all nodes. Only used with format 2. Required, typically 5.",
                "group": "Radio network group, 0 to 255. Must match the transmitting nodes. Required, typically 210.",
                "format": "Packet format of the transmitting nodes: 1 for JeeLib classic (hardware up to Nov 2022), 2 for JeeLib native. Default 2.",
                "selPin": "Radio chip select pin, Raspberry Pi BOARD (physical) numbering. 16 on emonPi2, emonPi3 and emonHP, 26 on emonBase RFM69 SPI. Default 16.",
                "resetPin": "Radio reset pin, BOARD numbering, e.g. 24 on emonPi2. Leave unset if the hardware has no reset line. Default none."
            },
            "runtime": {}
        },
        "EmonHubRFM69LPLInterfacer": {
            "init": {
                "nodeid": "Radio node id of this receiver, 1 to 1023. Default 5.",
                "networkID": "Radio network id, 0 to 255. Must match the transmitting nodes. Default 210.",
                "interruptPin": "Radio interrupt (DIO0) pin, BOARD numbering. Falls back to polling if the interrupt cannot be set up. Default 22.",
                "resetPin": "Radio reset pin, BOARD numbering. Leave unset if the hardware has no reset line. Default none.",
                "selPin": "Radio chip select pin, BOARD numbering. Default 26.",
                "freqBand": "Radio frequency band code. 49 for 433.92 MHz on current OpenEnergyMonitor hardware, 43 for 433 MHz on legacy nodes. Default 43."
            },
            "runtime": {}
        },
        "EmonHubRedisInterfacer": {
            "init": {
                "redis_host": "Redis server hostname or IP address. Default localhost.",
                "redis_port": "Redis server port. Default 6379.",
                "redis_db": "Redis database number. Default 0."
            },
            "runtime": {
                "prefix": "Prefix for Redis keys, joined with a colon to form prefix:nodeid:name. Default empty."
            }
        },
        "EmonHubSDM120Interfacer": {
            "init": {
                "device": "Serial device of the RS485 adapter. Default /dev/modbus.",
                "baud": "Modbus baud rate of the SDM120 meter. Default 2400."
            },
            "runtime": {
                "read_interval": "Seconds between readings, aligned to the clock. Default 10.",
                "nodename": "Node name for published inputs. Default sdm120.",
                "prefix": "Text added before each input name. Default empty.",
                "datafields": "Comma-separated sdm_modbus SDM120 fields to read, e.g. voltage,power_active,import_energy_active.",
                "names": "Comma-separated input names, in the same order as datafields. Field name is used where none is given.",
                "precision": "Comma-separated decimal places, in the same order as datafields. Setting it here causes a read error, leave unset to use the defaults."
            }
        },
        "EmonHubSDS011Interfacer": {
            "init": {
                "com_port": "Serial port of the SDS011 sensor, e.g. /dev/ttyUSB0.",
                "readinterval": "Currently has no effect. Use readinterval under runtimesettings."
            },
            "runtime": {
                "nodename": "Node name for the pm_25, pm_10 and msg inputs. Default SDS011.",
                "readinterval": "Time between readings in minutes. 0 reads every 5 seconds. Sensor sleeps between readings above 30 seconds. Default 5."
            }
        },
        "EmonHubSMASolarInterfacer": {
            "init": {
                "inverteraddress": "Bluetooth address of the inverter, e.g. 00:80:25:1D:AC:53. Run hcitool scan to find it.",
                "inverterpincode": "Inverter PIN code, up to 12 digits. Default 0000.",
                "timeinverval": "Seconds between readings. Default 10.",
                "nodeid": "Node id for the inverter inputs. Default 29.",
                "packettrace": "Logs Bluetooth packets at debug level. Any non-empty value enables it, including 0. Leave unset to disable."
            },
            "runtime": {}
        },
        "EmonHubSerialInterfacer": {
            "init": {
                "com_port": "Serial port of the device, e.g. /dev/ttyUSB0. Device must send lines of space-separated values with the node id first.",
                "com_baud": "Serial baud rate, to match the connected device. Default 9600."
            },
            "runtime": {}
        },
        "EmonHubShellyInterfacer": {
            "init": {},
            "runtime": {
                "read_interval": "Seconds between published readings, aligned to the clock. Minimum 1, default 10.",
                "devices": "Subsection with one named section per Shelly device. Each needs host and may set fields, password and transport (ws or http). Section name becomes the node name."
            }
        },
        "EmonHubSocketInterfacer": {
            "init": {
                "port_nb": "TCP port to listen on for space-separated data lines ending in CRLF. Default 50011."
            },
            "runtime": {
                "apikey": "Optional 32-character key. When set, lines without the key are discarded and the key is removed from accepted lines."
            }
        },
        "EmonHubSunampInterfacer": {
            "init": {
                "com_port": "Serial port of the Sunamp heat battery controller. Default /dev/ttyUSB0.",
                "com_baud": "Serial baud rate of the Sunamp controller. Default 115200."
            },
            "runtime": {
                "nodename": "Node name for the inputs. Defaults to the interfacer name."
            }
        },
        "EmonHubTemplateInterfacer": {
            "init": {
                "port_nb": "Currently has no effect."
            },
            "runtime": {
                "read_interval": "Seconds to wait between generated test frames. Default 10."
            }
        },
        "EmonHubTeslaPowerWallInterfacer": {
            "init": {},
            "runtime": {
                "name": "Node name for the state of charge input in emoncms. Default powerwall.",
                "url": "Powerwall state of charge URL, e.g. http://POWERWALL-IP/api/system_status/soe. Nothing is read until set.",
                "readinterval": "Seconds between readings, also used as the request timeout. Default 10."
            }
        },
        "EmonHubTx3eInterfacer": {
            "init": {
                "com_port": "Serial port of the emonTx, e.g. /dev/ttyUSB0. Expects key:value lines starting with MSG:.",
                "com_baud": "Serial baud rate of the emonTx, typically 115200. Default 9600."
            },
            "runtime": {
                "nodename": "Node name for the inputs. Leave empty to use nodeoffset as the node id. Default empty."
            }
        },
        "EmonHubVEDirectInterfacer": {
            "init": {
                "com_port": "Serial port of the Victron VE.Direct cable, e.g. /dev/ttyUSB0.",
                "com_baud": "Serial baud rate. VE.Direct uses 19200. Default 9600.",
                "toextract": "Comma-separated list of VE.Direct field labels to read, e.g. V,I,SOC. Order must match the input names in [nodes]. ON/OFF values become 1/0.",
                "poll_interval": "Time between readings in seconds. Default 30."
            },
            "runtime": {}
        },
        "EmonModbusTcpInterfacer": {
            "init": {
                "modbus_IP": "IP address of the Modbus TCP device. Default 192.168.1.10.",
                "modbus_port": "Modbus TCP port, usually 502. Default 0, so it needs to be set."
            },
            "runtime": {
                "nodeId": "Node ID for published data. Must match a [nodes] entry that sets names and datacode(s). Required.",
                "register": "Comma-separated register numbers, 1-based, decimal or hex such as 0xA001. One per name in the node's rx section."
            }
        }
    }
};
