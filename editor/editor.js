var config = "";

var height = $("#wrap").height()-180;
$("#configtextarea").height(height)

$.ajax({
    url: path+"config/get",
    dataType: 'text', async: true,
    success: function(data) {
        config = data;
        $("#configtextarea").val(config);
    }
});

$("#save").click(function(){
    config = $("#configtextarea").val();
    $.ajax({ type: "POST", url: path+"config/set", data: "config="+config, async: false, success: function(response){
        console.log(response);
        alert(response);
    }});
});


