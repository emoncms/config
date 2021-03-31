function list_format_updated(time) {
    var fv = list_format_updated_obj(time);
    return "<span class='last-update' style='color:" + fv.color + ";'>" + fv.value + "</span>";
}

function list_format_updated_obj(time) {
    time = time * 1000;
    var servertime = new Date().getTime(); // - table.timeServerLocalOffset;
    var update = new Date(time).getTime();
    
    var delta = servertime - update;
    var secs = Math.abs(delta) / 1000;
    var mins = secs / 60;
    var hour = secs / 3600;
    var day = hour / 24;
    
    var updated = secs.toFixed(0) + "s";
    if ((update == 0) || (!$.isNumeric(secs))) updated = "n/a";
    else if (secs.toFixed(0) == 0) updated = "now";
    else if (day > 7 && delta > 0) updated = "inactive";
    else if (day > 2) updated = day.toFixed(1) + " days";
    else if (hour > 2) updated = hour.toFixed(0) + " hrs";
    else if (secs > 180) updated = mins.toFixed(0) + " mins";
    
    secs = Math.abs(secs);
    var color = "rgb(255,0,0)";
    if (delta < 0) color = "rgb(60,135,170)"
    else if (secs < 25) color = "rgb(50,200,50)"
    else if (secs < 60) color = "rgb(240,180,20)"; 
    else if (secs < (3600*2)) color = "rgb(255,125,20)"
    
    return {color:color,value:updated};
}
