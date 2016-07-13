<?php
/**
 * 文件迭代器
 * Created by PhpStorm.
 * User: payneliu
 * Date: 2016/6/15
 * Time: 17:30
 */
class ReadFileIterator implements Iterator{
    private $FilePath;
    private $fp;
    private $current;
    private $key = 0;
    private $encoding = '';
    private $localencoding = '';

    public function __construct($filepath, $encoding = 'UTF-8'){
        $this->FilePath = $filepath;
        $this->encoding = $encoding;
    }

    public function __destruct() {
        $this -> dispose();
    }

    public function next(){
        $line = fgets($this->fp);
        $this->getEncoding($line);
        if(strtolower($this->encoding) !== strtolower($this->localencoding)){
            $this->current = iconv( $this->localencoding, $this->encoding . '//IGNORE', $line );
        } else {
            $this->current = $line;
        }
        return true;
    }

    public function key(){
        return $this->key++;
    }

    public function valid(){
        return !feof($this->fp);
    }

    public function rewind(){
        $this->dispose(); //先释放

        $this->fp = fopen($this->FilePath, 'r');
        $this->key = 0;

        $this->next();
    }

    public function current(){
        return $this -> current;
    }

    public function dispose(){
        if($this->fp){
            fclose($this->fp);
            $this->fp = null;
        }
    }

    private function getEncoding($text){
        if(!$this->localencoding){
            $this->localencoding = 'UTF-8';
            if($text === iconv('GBK', 'GBK//IGNORE', $text)){
                $this->localencoding = 'GBK';
            } elseif ($text === iconv('UTF-8', 'UTF-8//IGNORE', $text)){
                $this->localencoding = 'UTF-8';
            }
        }
        return $this->localencoding;
    }
}

//使用方法
$fr = new ReadFileIterator('');
foreach($fr as $item){
 //....
}
