<?php
global $path;
$version = 2;
?>
<style>
label {
   font-weight:bold;
   font-size:18px;
}
</style>

<?php if(!empty($tabs)) echo $tabs ?>
  <h2>Connect to remote emoncms account</h2>
  <p>Configure emonhub to send data to remote emoncms account (e.g emoncms.org)<p>

  <div style="background-color:#eee; padding:20px; max-width:600px">
    <p style="color:#666">Enter username and password to fetch remote account apikey.<br>Apikey automatically placed in emonhub.conf.</p>
    <label>Host:</label>
    <input type="text" id="host">
    
    <label>Username:</label>
    <input type="text" id="username">
    
    <label>Password:</label>
    <input type="text" id="password">
    <br>
    <button id="connect" class="btn">Connect</button>
    <br><br>
    <label>Current emonhub.conf apikey:</label>
    <input type="text" id="apikey" style="width:400px" readonly>
  </div>
  
<link rel="stylesheet" href="<?php echo $path; ?>Modules/config/style.css?v=<?php echo $version ?>">
<script src="<?php echo $path; ?>Modules/config/vue.js"></script>

<script>

init_sidebar({menu_element:"#config_menu"});

var conf = <?php echo !empty($conf) ? $conf: "{}"; ?>;

if (conf.interfacers==undefined) {
    alert("emonhub interfacers missing");
}else{
    if (conf.interfacers.emoncmsorg==undefined) {
        alert("emonhub emoncmsorg interfacer missing");
    } else {
        $("#host").val(conf.interfacers.emoncmsorg.runtimesettings.url);
        $("#apikey").val(conf.interfacers.emoncmsorg.runtimesettings.apikey);
    }
}

$("#connect").click(function() {
    var host = $("#host").val();
    var username = $("#username").val();
    var password = $("#password").val();
    
    $.ajax({ type: "POST", url: path+"config/remoteauth", data: "host="+host+"&username="+username+"&password="+password, async: false, success: function(result){ 
        if (result.success!=undefined && result.success) {
            $("#apikey").val(result.apikey_write);
            $("#apikey").css("background-color","rgba(0,255,0,0.3)");
        } else {
            alert("Emoncms account does not exist");
        }
    }});
});
</script>
