<?php
namespace acmepy\ocr;

use yii\base\Widget;

use yii\imagine\Image;
use Imagine\Image\Box;  
 
class Ocr extends Widget{
	
	public $image_path = __dir__ . '/test.jpg';
	public $image_name;
	public $image_size = 0;
	public $image_type = '';
	public $max_file_size = 1048576;
	public $target_dir;
	
	public function run(){
		$this->target_dir = \Yii::getAlias('@runtime/');
		$image_path = $this->image_path;
		$image_name = isset($this->image_name)?$this->image_name:$this->image_path;
		$uploadOk = 1;
		$file_type = strtolower(pathinfo($this->image_name, PATHINFO_EXTENSION));
		if($file_type != "pdf" && $file_type != "png" && $file_type != "jpg") {
			header('HTTP/1.0 403 Forbidden');
			echo "Sorry, please upload a pdf file.(" .$file_type. ")(".$this->image_type.")";
			$uploadOk = 0;
		}
		if ($image_path == __dir__ . '/test.jpg'){
			$tmp_file = $this->target_dir . $this->generateRandomString() . '.' . $file_type;
			copy($image_path, $tmp_file);
			$image_path = $tmp_file;
		}
		$target_file = $this->target_dir . $this->generateRandomString() . '.' . $file_type;
		if ($uploadOk == 1) {
			// Check & reduce file size
			// hacer un loop para verificar y reducir progresivamente el tamaño
			if ($this->image_size == 0) {
				$this->image_size = filesize($image_path);
			}
			if ($this->image_size > $this->max_file_size){
				$sizes = getimagesize ($image_path);
				Image::resize($image_path, round($sizes[0]), round($sizes[1]))->save($target_file);
			}else{
				copy($image_path, sys_get_temp_dir() ."/" . $image_name);
				$target_file = sys_get_temp_dir() ."/" . $image_name;
			}
			return $this->uploadToApi($target_file);
		} 
		return '';
		
	}

	protected function uploadToApi($target_file){
		$fileData = fopen($target_file, 'r');
		$client = new \GuzzleHttp\Client();
		try {
		$r = $client->request('POST', 'https://api.ocr.space/parse/image',[
			'headers' => ['apiKey' => 'ef87311f8788957'],
			'language' => 'spa',
			'OCREngine' => 2,
			'multipart' => [
				[
					'name' => 'file',
					'contents' => $fileData
				]
			]], 
			['file' => $fileData]);
		$response =  json_decode($r->getBody(),true);
		if(!isset($response['ErrorMessage']) || $response['ErrorMessage'] == "") {
			return $response['ParsedResults'][0]['ParsedText'];
		} else {
			header('HTTP/1.0 400 Forbidden');
			$err[] = $response['ErrorMessage'];
			$err[] = $response['ErrorDetails'];
			$err[] = $target_file;
			return json_encode($err);
		}
		} catch(Exception $err) {
			header('HTTP/1.0 403 Forbidden');
			return $err->getMessage();
		}
	}

	protected function generateRandomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}