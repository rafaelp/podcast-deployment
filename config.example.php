<?php

$params = array(
	'filemask' => 'rafael_lima-voltando_pra_casa-[episode]', // [episode] will be replaced with 0001 for example.
	'path' => dirname(__FILE__).'/files',
	'id3tool' => array(
		'-t' => 'Voltando pra casa [episode]', // title
		'-a' => null, // album
		'-r' => 'Rafael Lima', // artist
		'-y' => date("Y"), // year
		'-n' => 'http://rafael.adm.br', // note
		'-g' => null, // genre
		'-G' => null, // genre-word
		'-c' => null, // track
	),
	'ftp' => array(
		'hostname' => 'ftp.yoursite.com',
		'port' => 21,
		'username' => 'username',
		'password' => '*******',
		'path' => '/path_to_directory'
	),
	'wordpress' => array(
		'uri' => 'http://rafael.adm.br/xmlrpc.php',
		'username' => 'username',
		'password' => '*******',
		'categories' => array('209'),
		'open_after_save' => 'http://rafael.adm.br/wp-admin/edit.php?post_status=draft'
	)
);

// You can comment these lines after running podcas_deploy.php first time
@mkdir($params['path'].'/dropbox',0755,true);
@mkdir($params['path'].'/published',0755,true);
@mkdir($params['path'].'/sources',0755,true);
@mkdir($params['path'].'/to_convert',0755,true);
@mkdir($params['path'].'/to_publish',0755,true);
@mkdir($params['path'].'/to_upload',0755,true);
?>