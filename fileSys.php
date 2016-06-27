<?php
/**
 * 文件系统操作类
 * 
 */
class FileSystem {
    /**
     * 单例对象
     * 
     * @var FileSystem
     */
    private static $instance;
    
    private function __construct() {}
    
    /**
     * 获取单例
     * 
     * @return FileSystem
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * 读取文件内容
     * 
     * @param File $file 要读取的文件
     * @return string 
     */
    public function read(File $file) {
        if (!$file->exists()) {
            throw new \Exception("The specified file '{$file}' doesn't exist.");
        }
        return $file->content();
    }

    /**
     * 将内容数据写入文件
     * 
     * @param File $file 要被写入数据的文件
     * @param string $content 要写入的数据
     * @param boolean $append 是否为追加数据而不是覆盖，默认为覆盖
     * @return int
     */
    public function write(File $file, $content, $append = false) {
        if (!$file->exists()) {
            $dir = new Dir($file->getPath());
            if (!$dir->exists() && !$dir->create()) {
                return false;
            }
        }
        return $file->write($content, $append);
    }

    /**
     * 删除一个文件
     * 
     * @param File $file 待删除的文件
     * @return boolean
     */
    public function remove(File $file) {
        if ($file->exists()) {
            return $file->remove();
        }
        return true;        
    }

    /**
     * 复制文件
     * 
     * @param File $src 源文件
     * @param File $dest 目标文件
     * @return boolean
     */
    public function copy(File $src, File $dest) {
        if(!$src->exists()) {
            return false;
        }
        $dir = new Dir($dest->getPath());
        if (!$dir->exists() && !$dir->create()) {
            return false;
        }
        return $src->copyTo($dest);
    }

    /**
     * 移动（重命名）文件
     * 
     * @param File $src 源文件
     * @param File $dest 目标文件
     * @return boolean
     */
    function move(File $src, File $dest) {
        if(!$src->exists()) {
            return false;
        }
        $dir = new Dir($dest->getPath());
        if (!$dir->exists() && !$dir->create()) {
            return false;
        }
        return $src->rename($dest);
    }

    /**
     * 复制目录（把源目录下的所有子目录及文件复制到目标目录下）
     * 
     * @param Dir $src 源目录
     * @param Dir $dest 目标目录，不存在时将会被创建
     * @return boolean
     */
    public function copyDir(Dir $src, Dir $dest) {
        if(!$src->exists()) {
            return false;
        }
        if (!$dest->exists() && !$dest->create()) {
            return false;
        }
        $files = $src->listing();
        foreach ($files as $f) {
            $_dest = $dest . '/' . $f->getFilename();
            if ($f->isFile()) {
                $f->copyTo($_dest);
            } else if($f->isDir()) {
                $this->copy($f, new Dir($_dest));
            }
        }
        return true;
    }

    /**
     * 移动目录（把源目录下的所有子目录及文件移动到目标目录下，源目录将会被删除）
     * 
     * @param Dir $src 源目录
     * @param Dir $dest 目标目录，不存在时将会被创建
     * @return boolean
     */
    function moveDir(Dir $src, Dir $dest) {
        if(!$src->exists()) {
            return false;
        }
        if (!$dest->exists() && !$dest->create()) {
            return false;
        }
        $files = $src->listing();
        foreach ($files as $f) {
            $_dest = $dest . '/' . $f->getFilename();
            if ($f->isFile()) {
                $f->rename($_dest);
            } else if($f->isDir()) {
                $this->moveDir($f, new Dir($_dest));
                rmdir($f);
            }
        }
        return rmdir($src);
    }

    /**
     * 在目录下查找指定扩展名的文件列表（不包含子目录）
     * 
     * @param Dir $dir 查找目录
     * @param string $ext 文件扩展名，如txt
     * @return array
     */
    public function findByExt(Dir $dir, $ext) {
        $arr =[];
        $files = $dir->listing();
        foreach ($files as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $info = pathinfo($f);
            if (isset($info['extension']) && $info['extension'] == $ext) {
                $arr[] = $f;
            }
        }
        return $arr;
    }
}

/**
 * 文件类
 */
class File extends \SplFileInfo implements FileOperable {
    /**
     * 文件的路径（包括文件名）
     * 
     * @var string
     */
    protected $pathname;
        
    /**
     * 构造方法，设置当前文件
     * 
     * @param string $pathname 文件的路径
     */
    public function __construct($pathname) {
        parent::__construct($pathname);
        
        $this->pathname = $pathname;
    }
    
    /**
     * 析构方法
     */
    public function __destruct() {
        unset($this->pathname);
    }
    
    /**
     * 创建文件
     * 
     * @return boolean
     */
    public function create() {
        return touch($this->pathname) && chmod($this->pathname, 0666);
    }
    
    /**
     * 删除文件
     *
     * @return boolean
     */
    public function remove() {
        return unlink($this->pathname);
    }
    
    /**
     * 文件重命名
     * 
     * @param string $newname 新的文件名
     * @return boolean
     */
    public function rename($newname) {
        if (rename($this->pathname, $newname)) {
            $this->pathname = $newname;
            return true;
        }
        return false;
    }
    
    /**
     * 判断文件是否存在
     * 
     * @return boolean
     */
    public function exists() {
        return $this->isFile();
    }
    
    /**
     * 将$source文件拷贝覆盖当前文件
     * 
     * @param string $source 源文件路径
     * @return boolean
     */
    public function copyFrom($source) {
        return copy($source, $this->pathname);
    }
    
    /**
     * 将当前文件拷贝到$dest
     * 
     * @param string $dest 目标文件路径
     * @return boolean
     */
    public function copyTo($dest) {
        return copy($this->pathname, $dest);
    }
    
    /**
     * 将内容写入文件
     * 
     * @param string $content 要写入的数据
     * @param boolean $append 是否为追加数据而不是覆盖，默认为覆盖
     * @return int
     */
    public function write($content, $append = false) {
        $flags = $append ? FILE_APPEND | LOCK_EX : null;
        return file_put_contents($this->pathname, $content, $flags);
    }
    
    /**
     * 读取整个文件内容
     *
     * @param File $file 要读取的文件
     * @return string
     */
    public function content() {
        return file_get_contents($this->pathname);
    }
    
    /**
     * 获取文件大小的字节数
     * 
     * @return int
     */
    public function size() {
        return $this->getSize();
    }
    
    /**
     * 获取文件的扩展名
     * 
     * @return string
     */
    public function ext() {
        return $this->getExtension();
    }
    
    /**
     * 获取文件名
     * 
     * @return string
     */
    public function name() {
        return $this->getBasename();
    }
    
    /**
     * 获取文件流操作对象
     * 
     * @param string $mode 文件流的访问类型
     * @return FileStream
     */
    public function stream($mode = 'r') {
        return new FileStream($this->pathname, $mode);
    }
    
}

/**
 * 目录类
 * 
 */
class Dir extends \SplFileInfo implements FileOperable {
    /**
     * 目录的路径
     * 
     * @var string
     */
    protected $path;

    /**
     * 构造方法，设置当前目录
     * 
     * @param string $path 目录的路径
     */
    public function __construct($path) {
        parent::__construct($path);
        
        $this->path = rtrim($path, '/');
    }

    /**
     * 析构方法
     */
    public function __destruct() {
        unset($this->path);
    }
    
    /**
     * 创建目录
     * 
     * @return boolean
     */
    public function create() {
        if (!$this->exists()) {
            $arr = explode('/', str_replace("\\", "/", $this->path));
            $build = '';
            foreach($arr as $dir) {
                if(strstr($dir, ":") != false) {
                    $build = $dir;
                    continue;
                }
                $build .= "/$dir";
                if (!is_dir($build)) {
                    if (!mkdir($build, 0777)) {
                        return false;
                    }
                    chmod($build, 0777);
                }
            }
        }
        return true;
    }
    
    /**
     * 删除目录
     * 
     * @return boolean
     */
    public function remove() {
        $this->clear();
        return rmdir($this->path);
    }
    
    /**
     * 删除目录下所有文件，但不删除当前目录
     * 
     * @return void
     */
    public function clear() {
        $list = $this->listing();
        foreach($list as $f) {
            $f->remove();
        }
    }
    
    /**
     * 重命名目录
     * 
     * @param string $name 新目录名称（包含完整路径）
     * @return boolean
     */
    public function rename($newname) {
        if (rename($this->path, $newname)) {
            $this->path = $newname;
            return true;
        }
        return false;
    }

    /**
     * 判断目录是否存在
     *
     * @return boolean
     */
    public function exists() {
        return $this->isDir();
    }
    
    /**
     * 判断目录是否为空
     * 
     * @return boolean
     */
    public function isEmpty() {
        return $this->listing() ? false : true;
    }
    
    /**
     * 获取目录下所有子目录及文件
     * 
     * @return array
     */
    public function listing() {
        $arr = [];
        $i = new \DirectoryIterator($this->path);
        foreach($i as $f) {
            if(!$f->isDot()) {
                $path = $f->getRealPath();
                $arr[] = $f->isDir() ? new Dir($path) : new File($path);
            }
        }
        return $arr;
    }
}

/**
 * 文件流操作封装类
 * 
 */
class FileStream {
    /**
     * 文件流操作指针资源
     * 
     * @var resource
     */
    protected $handle;

    /**
     * 构造方法，生成文件流操作指针资源
     *
     * @param string $name 文件的路径
     * @param string $mode 文件流访问类型
     */
    public function __construct($name, $mode = 'r', $handle = null) {
        if (is_resource($handle)) {
            $this->handle = $handle;
        }
        else {
            $this->handle = fopen($name, $mode);
        }
    }

    /**
     * 析构方法，关闭已打开的文件指针
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * 关闭已打开的文件指针
     * 
     * @return void
     */
    public function close() {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        unset($this->handle);
    }

    /**
     * 获取当前已打开的文件指针
     *
     * @return resource
     */
    public function getHandle() {
        return $this->handle;
    }

    /**
     * 读取最多length个字节
     *
     * @param int $length 最多读取length个字节
     * @return string
     */
    public function read($length) {
        return fread($this->handle, $length);
    }
    
    /**
     * 读取文件所有字节
     *
     * @param int $bucketSize 一次读取的字节数
     * @return string
     */
    public function readAll($bucketSize = 4096) {
        $contents = "";
        while (!$this->eof()) {
            $contents .= $this->read($bucketSize);
        }
        return $contents;
    }

    /**
     * 从文件指针中读取一行
     *
     * @param int $length 读取一行最多length - 1个字节，省略为读取数据直到行结束
     * @return string
     */
    public function getLine($length = 0) {
        $content = null;

        if (0 < $length) {
            $content = fgets($this->handle, $length);
        }
        else {
            $content = fgets($this->handle);
        }

        return $content;
    }

    /**
     * 写入lendth个字节到文件指针处
     * 
     * @param string $content 要写入的字符串内容
     * @param int $length 若指定了length,写入length个字节后将停止写入
     * @return int
     */
    public function write($content, $length = null) {
        $writtenBytes = 0;
        
        if (is_null($length)) {
            $writtenBytes = fwrite($this->handle, $content);
        }
        else {
            $writtenBytes = fwrite($this->handle, $content, $length);
        }

        return $writtenBytes;
    }

    /**
     * 测试文件指针是否到了文件结束的位置
     * 
     * @return boolean
     */
    public function eof() {
        return feof($this->handle);
    }

    /**
     * 倒回文件指针的位置
     * 
     * @return boolean
     */
    public function rewind() {
        rewind($this->handle);
    }
}

/**
 * 文件或目录操作接口
 * 
 */
interface FileOperable {
	/**
	 * 创建文件或目录
	 */
	public function create();
	
	/**
	 * 删除文件或目录
	 */
	public function remove();
	
	/**
	 * 重命名文件或目录
	 * 
	 * @param string $newname 新的文件或目录名
	 */
	public function rename($newname);
	
	/**
	 * 检查文件或目录是否存在
	 */
	public function exists();
	
}
?>
