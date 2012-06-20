<?php
    list($REQUEST_URI) = explode('?',$_SERVER['REQUEST_URI'],2);
    $REQUEST_URI = htmlspecialchars(rawurldecode($REQUEST_URI));
?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo __('Internal Server Error');?></title>
<style type="text/css">
body{
font-size:9pt;
padding:10pt;
margin:0;
color:#111;
font-family:Arial,sans-serif,Helvetica,"宋体";
}
h1{
font-size:14pt;
font-weight:bold;
padding:0 0 10px 0;
line-height:1.2em;
margin:0;
color:#911;
_padding-left:0px;
}
pre{
margin:5px;
padding:4px 10px;
border:1px dotted #ff9797;
background:#fffff2;
-moz-border-radius:4px;
-webkit-border-radius:4px;
border-radius:4px;
}
.box{
border:1px solid #ccc;
padding:10px;
background:#ffffd6;
line-height:1.4em;
-moz-border-radius:4px;
-webkit-border-radius:4px;
border-radius:4px;
}
</style>
</head>
<body>
<h1><?php echo __('Internal Server Error');?></h1>
<div class="box">
    <?php echo __('The requested URL :REQUEST_URI was error on this server.', array(':REQUEST_URI'=>$REQUEST_URI) );?>

    <br />
    <br />
    <b><?php echo __('Error Message:');?></b>
    <pre><?php echo $error;?></pre>
</div>
</body>
</html>