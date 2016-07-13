<?php
error_reporting(E_ALL ^ E_NOTICE);
/**
 * 远程图片并行下载
 *
 * 确保服务器配置有curl
 *
 * @author blog.ja168.net
 * @param  string $url
 * @param  string $save_path
 * @return void
 */
function graber_remote_image($url, $save_path="./") {
	set_time_limit(0);
	ignore_user_abort(true);
	if(!in_array('curl', get_loaded_extensions())){
        die("curl not support !");
	}
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_REFERER => !empty($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "",
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_FOLLOWLOCATION => 1,
		CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
    ));
	$message = curl_exec($ch);
	if(preg_match_all("/(http:\/\/|www\.)[^ \"']+/i", $message, $matches)){
		$images_links = array();
		foreach ($matches[0] as $item) {
			$suffix = pathinfo($item, PATHINFO_EXTENSION);
			if(in_array(strtolower($suffix), array('gif', 'png', 'jpg'))) {
				array_push($images_links, $item);
				//在这里进行简单修改可以选择性下载不同格式文件
			}
		}
	}
	if(empty($images_links)) {
		die('get nothing image inforation.');
	}
	$images_links = array_unique($images_links);
	@mkdir($save_path, 0777, true);
	// Download images
	$mh = curl_multi_init();
	$handle = array();
	foreach($images_links as $k=>$item_url){
		$filename = pathinfo($item_url, PATHINFO_BASENAME);

		$curl = curl_copy_handle($ch);
		curl_setopt($curl, CURLOPT_URL, $item_url);
		$data=curl_exec($curl); 

		file_put_contents($save_path . '/' . $filename , $data);
		curl_close($curl);
	}
	return true;
}

//demon测试下载新浪首页全部图片
function microtime_float() {
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}
$time_start = microtime_float();
//graber_remote_image("http://www.ja168.net", "D:/b2");
graber_remote_image("http://www.sina.com.cn/", "D:/b2");
$time_end = microtime_float();
$time = $time_end - $time_start;

echo "Time: $time seconds<br />";