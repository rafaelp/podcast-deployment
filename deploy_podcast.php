#!/usr/bin/php
<?php

/**
* PodcastDeployment
*/
class PodcastDeployment
{
	private $params;
	private $files;
	private $extensions = array('dropbox' => '.wav', 'published' => '.mp3', 'to_convert' => '.wav', 'to_upload' => '.mp3', 'to_publish' => '.mp3');
	
	function __construct($params)
	{
		$this->params = $params;
	}
	
	public function run() {
		$this->check_config();
		$this->get_files('published');
		//$this->mix_intro();
		$this->rename_wavs();
		$this->convert_to_mp3();
		$this->tag_mp3();
		$this->upload_files();
		$this->save_posts();
	}
	
	private function check_config()
	{
		if(!file_exists($this->params['path'])) {
			$this->log("ERROR: Path does not exists. Check your configuration\n");
		}
	}
	
	private function path($path='')
	{
		return realpath($this->params['path']).'/'.$path;
	}
	
	private function get_files($status)
	{
		$this->files[$status] = array();
		$files = scandir($this->path($status));
		foreach($files as $file)
		{
			if(preg_match("/\\".$this->extensions[$status]."/", $file, $match))
			{
				$this->files[$status][] = $file;
			}
		}
		if($this->files[$status]) ksort($this->files[$status]);
		return $this->files[$status];
	}
	
	private function rename_wavs()
	{
		$number = intval(end($this->files['published']))+1;
		foreach($this->get_files('dropbox') as $file)
		{
			$from = $this->path('dropbox').'/'.$file;
			$to = $this->path('to_convert').'/'.sprintf("%04d",$number).'.wav';
			$this->log("Renaming $from to $to...");
			rename($from, $to);
			$this->log("OK\n");
			$number++;
		}
	}
	
	private function convert_to_mp3()
	{
		foreach($this->get_files('to_convert') as $file)
		{
			$in_file = $this->path('to_convert').'/'.$file;
			$out_file = $this->path('to_upload').'/'.basename($file,'.wav').'.mp3';
			$cmd = 'lame -b 16 "'.$in_file.'" "'.$out_file.'"';
			$this->log("Converting $file...");
			exec($cmd);
			$this->log("OK\n");
			rename($this->path('to_convert').'/'.$file, $this->path('sources').'/'.$file);
		}
	}
	
	private function tag_mp3()
	{
		foreach($this->get_files('to_upload') as $file) {
			if(preg_match("/^([0-9]{4})\.mp3$/", $file, $match))
			{
				$episode = $match[1];
				$cmd = 'id3tool ';
				foreach($this->params['id3tool'] as $key=>$value)
				{
					if($value) $cmd .= $key.' "'.str_replace('[episode]', $episode, $value).'" ';
				}
				$cmd .= '"'.$this->path('to_upload').'/'.$file.'"';
				$this->log("Tagging $file...");
				exec($cmd);
				$this->log("OK\n");
			}
		}
	}
	
	private function upload_files()
	{
		if($this->get_files('to_upload')) {
			set_time_limit(0);
			$this->log("Connecting to ftp server...");
			$conn_id = ftp_connect($this->params['ftp']['hostname'], $this->params['ftp']['port'], 600);
			if(!$conn_id) {
				$this->log("There was an error connecting to ftp server\n");
				exit;
			}
			$this->log("OK\n");
			$login_result = ftp_login($conn_id, $this->params['ftp']['username'], $this->params['ftp']['password']);

			if(!$login_result) {
				$this->log("Could not authenticate to ftp server\n");
				exit;
			}
			
			// turn passive mode on
			ftp_pasv($conn_id, true);

			foreach($this->get_files('to_upload') as $file)
			{
				$remote_path = $this->params['ftp']['path'].'/'.str_replace('[episode]', $file, $this->params['filemask']);
				$local_path = $this->path('to_upload').'/'.$file;
				$this->log("Uploading $file...");
				$ret = ftp_nb_put($conn_id, $remote_path, $local_path, FTP_BINARY);
				while ($ret == FTP_MOREDATA) {

				   // Do whatever you want
				   echo ".";

				   // Continue uploading...
				   $ret = ftp_nb_continue($conn_id);
				}
				if ($ret != FTP_FINISHED) {
				   echo "There was an error uploading $file...";
				   exit(1);
				}
			
				$this->log("OK\n");
				rename($this->path('to_upload').'/'.$file, $this->path('to_publish').'/'.$file);
			}

			ftp_close($conn_id);
		}
	}
	
	private function save_posts()
	{
		$posts_saved = 0;
		$categories = implode(",", $this->params['wordpress']['categories']);
		foreach($this->get_files('to_publish') as $file)
		{
			$this->log("Publishing $file...");
			$body = file_get_contents(dirname(__FILE__).'/post.template');
			$body = str_replace("[episode]", basename($file,".mp3"), $body);
			$XML = "<title>$file</title>".
			"<category>$categories</category>".
			$body;
			$params = array('','',$this->params['wordpress']['username'],$this->params['wordpress']['password'],$XML,0);
			$request = xmlrpc_encode_request('blogger.newPost',$params);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			curl_setopt($ch, CURLOPT_URL, $this->params['wordpress']['uri']);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_exec($ch);
			curl_close($ch);
			echo "OK\n";
			rename($this->path('to_publish').'/'.$file, $this->path('published').'/'.$file);
			$posts_saved++;
		}
		if($posts_saved) {
			exec("open ".$this->params['wordpress']['open_after_save']);
		}
	}
	
	private function log($log)
	{
		echo "$log";
	}
}

include 'config.php';

$pd = new PodcastDeployment($params);
$pd->run();

?>
