<?php
if (!isset($_GET) || !is_array($_GET) || !isset($_GET['field']) || empty($_GET['field'])) die();
$size = isset($_GET['size']) && !empty($_GET['size']) ? $_GET['size'] : 'thumbnail';
header('Content-Type: text/javascript');
?>(function($){ $(function(){ $('form').live('submit', function() { $('<input type="hidden" name="<?= $_GET['field'] ?>" value="1"/>').appendTo(this); $('<input type="hidden" name="_preview_size" value="<?= $size ?>"/>').appendTo(this); return true;}); }); })(jQuery);
//var opener={tinymce:{EditorManager:{activeEditor:{selection:{getNode:function(){return jQuery('<div></div>').appendTo('body').css({display:'none'});}}}}},tinyMCE:{}};
//var uploaderMode=0;
