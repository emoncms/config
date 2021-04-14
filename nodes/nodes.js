// Make local copy of nodes configuration
var conf_nodes = JSON.parse(JSON.stringify(conf.nodes));

var template_datalengths = {}
var show_apply_configuration = {}

$.getJSON( path+"config/getnodes", function( result ) {

    nodes = result;

    for (var z in nodes) {
        var fv = list_format_updated_obj(nodes[z].timestamp);
        nodes[z].time_color = fv.color
        nodes[z].time_value = fv.value
        nodes[z].datalength = nodes[z].data.length
        nodes[z].bytes = nodes[z].data
        nodes[z].data = nodes[z].data.join(", ")
        
        nodes[z].datacodes_length = 0
    }

    $.getJSON( path+"config/available", function( result ) {
        templates = result;
        
        for (var z in templates) {
            template_datalengths[z] = datacodes_len(templates[z].rx.datacodes)
        }
        
        // Work out which node decoders match the data length from the node
        // assign first option as default selected and prepare data model
        for (var n in nodes) {
            nodes[n].options = []
            for (var a in templates) {
                if (nodes[n].datalength==template_datalengths[a]) {
                    nodes[n].options.push(a)
                }
            }
            if (nodes[n].options.length) {
                // Apply configuration
                if (conf_nodes[n]==undefined) {
                    nodes[n].selected = nodes[n].options[0]
                    conf_nodes[n] = JSON.parse(JSON.stringify(templates[nodes[n].selected]))
                    conf_nodes[n].nodename += n
                    show_apply_configuration[n] = true
                } else {
                    // work out which template is selected
                    var name = is_template(conf_nodes[n])
                    if (name) {
                        nodes[n].selected = name
                        show_apply_configuration[n] = false
                    } else {
                        nodes[n].selected = "custom"
                    }
                }
            } else {
                nodes[n].selected = false
            }
        }
    
        // Dry run conversion of raw data using node decoders
        for (var nodeid in nodes) {
            if (conf_nodes[nodeid]!=undefined) {
                var result = decode_node(nodes[nodeid].bytes, conf_nodes[nodeid].rx);
                nodes[nodeid] = Object.assign(nodes[nodeid],result);
            } else {
                nodes[nodeid].bytes_available = nodes[nodeid].datalength
            }
        }
    
        var app = new Vue({
            el: '#app',
            data: { 
                nodes: nodes, 
                conf_nodes: conf_nodes, 
                show_apply_configuration: show_apply_configuration,
                add_datacode_select: 'h'
            },
            methods: {
                addDatacode: function(nodeid) {
                    if (conf_nodes[nodeid]==undefined) {
                      conf_nodes[nodeid] = {
                        rx: {
                          names: [],
                          datacodes: [],
                          scales: [],
                          units: []
                        }
                      }
                    }
                    conf_nodes[nodeid].rx.names.push(conf_nodes[nodeid].rx.names.length+1)
                    conf_nodes[nodeid].rx.datacodes.push(this.add_datacode_select)
                    conf_nodes[nodeid].rx.scales.push(1)
                    conf_nodes[nodeid].rx.units.push('')

                    var result = decode_node(nodes[nodeid].bytes, conf_nodes[nodeid].rx);
                    nodes[nodeid] = Object.assign(nodes[nodeid],result);
                    
                    Vue.set(this.show_apply_configuration,nodeid,true)
                    
                    this.add_datacode_select = 'h'
                },
                removeDatacode: function(nodeid) {

                    conf_nodes[nodeid].rx.names.pop()
                    conf_nodes[nodeid].rx.datacodes.pop()
                    conf_nodes[nodeid].rx.scales.pop()
                    conf_nodes[nodeid].rx.units.pop()

                    var result = decode_node(nodes[nodeid].bytes, conf_nodes[nodeid].rx);
                    nodes[nodeid] = Object.assign(nodes[nodeid],result);
 
                    Vue.set(this.show_apply_configuration,nodeid,true)
                },
                apply: function(nodeid) {
                    // Copy over node configuration
                    conf.nodes[nodeid] = JSON.parse(JSON.stringify(conf_nodes[nodeid]))
                    
                    // Save config
                    $.post( path+"config/save", { conf: JSON.stringify(conf) })
                        .done(function(r) {
                            Vue.set(app.show_apply_configuration,nodeid,false)
                        })
                        .fail(function(xhr, status, error) {
                            alert("There was an error applying configuration")
                        });
                },
                selectConfiguration: function(nodeid) {
                    if (nodes[nodeid].selected!='custom') {
                        conf_nodes[nodeid] = JSON.parse(JSON.stringify(templates[nodes[nodeid].selected]))
                        conf_nodes[nodeid].nodename += nodeid
                        
                        var result = decode_node(nodes[nodeid].bytes, conf_nodes[nodeid].rx);
                        nodes[nodeid] = Object.assign(nodes[nodeid],result);
                        
                        Vue.set(this.show_apply_configuration,nodeid,true)
                    }
                },
                update: function(nodeid) {
                    var result = decode_node(nodes[nodeid].bytes, conf_nodes[nodeid].rx);
                    nodes[nodeid] = Object.assign(nodes[nodeid],result);
                    
                    if (JSON.stringify(conf_nodes[nodeid])!=JSON.stringify(conf.nodes[nodeid])) {
                        Vue.set(this.show_apply_configuration,nodeid,true)
                    }
                }
            }
        });
    });
});

function is_template(node_conf) {
    node_conf = JSON.parse(JSON.stringify(node_conf));
    delete node_conf.nodename;
    var node_conf_str = JSON.stringify(node_conf);
    
    for (var name in templates) {
        var tmp = JSON.parse(JSON.stringify(templates[name]));
        delete tmp.nodename;
        if (node_conf_str==JSON.stringify(tmp)) {
            return name;
        }
    }
    return false;
}
