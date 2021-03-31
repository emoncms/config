function decode_node(bytes, config) {
    var result = {
        values: [],
        datacodes_length: 0,
        bytes_available: 0
    }
    
    bytes = JSON.parse(JSON.stringify(bytes))
    
    if (config.whitening) {
        for (x in bytes) bytes[x] ^= 0x55
    }
    
    var pos = 0;
    for (var z in config.datacodes) {
        var datacode = config.datacodes[z]
        var scale = config.scales[z]
        var len = datacode_len(datacode)
        
        var value = unpack(datacode,bytes.slice(pos,pos+len)) 
        value *= scale
        if (scale==0.01) value = value.toFixed(2)
        if (scale==0.1) value = value.toFixed(1)
        
        result.values.push(value)
        pos += len
    }
    result.datacodes_length = pos
    result.bytes_available = bytes.length - result.datacodes_length
                    
    return result;
}

function unpack(datacode,bytearray) {
    if (datacode=="h") {
        var sign = bytearray[1] & (1 << 7);
        var x = ((bytearray[1] & 0xFF) << 8) | (bytearray[0] & 0xFF);
        if (sign) {
           x = 0xFFFF0000 | x;
        }
        return x
    }
    if (datacode=="l") {
        var sign = bytearray[3] & (1 << 7);
        var x = ((bytearray[3] & 0xFF) << 24) | ((bytearray[2] & 0xFF) << 16) | ((bytearray[1] & 0xFF) << 8) | (bytearray[0] & 0xFF);
        if (sign) {
           x = 0xFFFF0000 | x;
        }
        return x
    }
    if (datacode=="L") {
        var x = ((bytearray[3] & 0xFF) << 24) | ((bytearray[2] & 0xFF) << 16) | ((bytearray[1] & 0xFF) << 8) | (bytearray[0] & 0xFF);
        return x
    }
}

function datacode_len(datacode) {
    var lengths = {"h":2,"l":4,"L":4}
    return lengths[datacode]
}

function datacodes_len(datacodes) {
    var len = 0;
    for (var i in datacodes) {
        len += datacode_len(datacodes[i])
    }
    return len;
}

// This interface requires that all node configurations are
// given in full with corresponding datacodes, names, scales 
// and units for each node. Configurations are not always
// entered like this and so we ensure that they are consistent.
function validate_config(config) {
    if (typeof config !== 'object') config = {}
    if (config.rx==undefined) config.rx = {}
    if (config.rx.whitening==undefined) config.rx.whitening = 0
    if (config.rx.datacodes==undefined) config.rx.datacodes = []
    if (config.rx.names==undefined) config.rx.names = []
    if (config.rx.scales==undefined) config.rx.scales = []
    if (config.rx.units==undefined) config.rx.units = []
    return config;
}
