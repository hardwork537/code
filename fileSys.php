<?php
/**
 * �ļ�ϵͳ������
 * 
 */
class FileSystem {
    /**
     * ��������
     * 
     * @var FileSystem
     */
    private static $instance;
    
    private function __construct() {}
    
    /**
     * ��ȡ����
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
     * ��ȡ�ļ�����
     * 
     * @param File $file Ҫ��ȡ���ļ�
     * @return string 
     */
    public function read(File $file) {
        if (!$file->exists()) {
            throw new \Exception("The specified file '{$file}' doesn't exist.");
        }
        return $file->content();
    }

    /**
     * ����������д���ļ�
     * 
     * @param File $file Ҫ��д�����ݵ��ļ�
     * @param string $content Ҫд�������
     * @param boolean $append �Ƿ�Ϊ׷�����ݶ����Ǹ��ǣ�Ĭ��Ϊ����
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
     * ɾ��һ���ļ�
     * 
     * @param File $file ��ɾ�����ļ�
     * @return boolean
     */
    public function remove(File $file) {
        if ($file->exists()) {
            return $file->remove();
        }
        return true;        
    }

    /**
     * �����ļ�
     * 
     * @param File $src Դ�ļ�
     * @param File $dest Ŀ���ļ�
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
     * �ƶ������������ļ�
     * 
     * @param File $src Դ�ļ�
     * @param File $dest Ŀ���ļ�
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
     * ����Ŀ¼����ԴĿ¼�µ�������Ŀ¼���ļ����Ƶ�Ŀ��Ŀ¼�£�
     * 
     * @param Dir $src ԴĿ¼
     * @param Dir $dest Ŀ��Ŀ¼��������ʱ���ᱻ����
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
     * �ƶ�Ŀ¼����ԴĿ¼�µ�������Ŀ¼���ļ��ƶ���Ŀ��Ŀ¼�£�ԴĿ¼���ᱻɾ����
     * 
     * @param Dir $src ԴĿ¼
     * @param Dir $dest Ŀ��Ŀ¼��������ʱ���ᱻ����
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
     * ��Ŀ¼�²���ָ����չ�����ļ��б���������Ŀ¼��
     * 
     * @param Dir $dir ����Ŀ¼
     * @param string $ext �ļ���չ������txt
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
 * �ļ���
 */
class File extends \SplFileInfo implements FileOperable {
    /**
     * �ļ���·���������ļ�����
     * 
     * @var string
     */
    protected $pathname;
        
    /**
     * ���췽�������õ�ǰ�ļ�
     * 
     * @param string $pathname �ļ���·��
     */
    public function __construct($pathname) {
        parent::__construct($pathname);
        
        $this->pathname = $pathname;
    }
    
    /**
     * ��������
     */
    public function __destruct() {
        unset($this->pathname);
    }
    
    /**
     * �����ļ�
     * 
     * @return boolean
     */
    public function create() {
        return touch($this->pathname) && chmod($this->pathname, 0666);
    }
    
    /**
     * ɾ���ļ�
     *
     * @return boolean
     */
    public function remove() {
        return unlink($this->pathname);
    }
    
    /**
     * �ļ�������
     * 
     * @param string $newname �µ��ļ���
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
     * �ж��ļ��Ƿ����
     * 
     * @return boolean
     */
    public function exists() {
        return $this->isFile();
    }
    
    /**
     * ��$source�ļ��������ǵ�ǰ�ļ�
     * 
     * @param string $source Դ�ļ�·��
     * @return boolean
     */
    public function copyFrom($source) {
        return copy($source, $this->pathname);
    }
    
    /**
     * ����ǰ�ļ�������$dest
     * 
     * @param string $dest Ŀ���ļ�·��
     * @return boolean
     */
    public function copyTo($dest) {
        return copy($this->pathname, $dest);
    }
    
    /**
     * ������д���ļ�
     * 
     * @param string $content Ҫд�������
     * @param boolean $append �Ƿ�Ϊ׷�����ݶ����Ǹ��ǣ�Ĭ��Ϊ����
     * @return int
     */
    public function write($content, $append = false) {
        $flags = $append ? FILE_APPEND | LOCK_EX : null;
        return file_put_contents($this->pathname, $content, $flags);
    }
    
    /**
     * ��ȡ�����ļ�����
     *
     * @param File $file Ҫ��ȡ���ļ�
     * @return string
     */
    public function content() {
        return file_get_contents($this->pathname);
    }
    
    /**
     * ��ȡ�ļ���С���ֽ���
     * 
     * @return int
     */
    public function size() {
        return $this->getSize();
    }
    
    /**
     * ��ȡ�ļ�����չ��
     * 
     * @return string
     */
    public function ext() {
        return $this->getExtension();
    }
    
    /**
     * ��ȡ�ļ���
     * 
     * @return string
     */
    public function name() {
        return $this->getBasename();
    }
    
    /**
     * ��ȡ�ļ�����������
     * 
     * @param string $mode �ļ����ķ�������
     * @return FileStream
     */
    public function stream($mode = 'r') {
        return new FileStream($this->pathname, $mode);
    }
    
}

/**
 * Ŀ¼��
 * 
 */
class Dir extends \SplFileInfo implements FileOperable {
    /**
     * Ŀ¼��·��
     * 
     * @var string
     */
    protected $path;

    /**
     * ���췽�������õ�ǰĿ¼
     * 
     * @param string $path Ŀ¼��·��
     */
    public function __construct($path) {
        parent::__construct($path);
        
        $this->path = rtrim($path, '/');
    }

    /**
     * ��������
     */
    public function __destruct() {
        unset($this->path);
    }
    
    /**
     * ����Ŀ¼
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
     * ɾ��Ŀ¼
     * 
     * @return boolean
     */
    public function remove() {
        $this->clear();
        return rmdir($this->path);
    }
    
    /**
     * ɾ��Ŀ¼�������ļ�������ɾ����ǰĿ¼
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
     * ������Ŀ¼
     * 
     * @param string $name ��Ŀ¼���ƣ���������·����
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
     * �ж�Ŀ¼�Ƿ����
     *
     * @return boolean
     */
    public function exists() {
        return $this->isDir();
    }
    
    /**
     * �ж�Ŀ¼�Ƿ�Ϊ��
     * 
     * @return boolean
     */
    public function isEmpty() {
        return $this->listing() ? false : true;
    }
    
    /**
     * ��ȡĿ¼��������Ŀ¼���ļ�
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
 * �ļ���������װ��
 * 
 */
class FileStream {
    /**
     * �ļ�������ָ����Դ
     * 
     * @var resource
     */
    protected $handle;

    /**
     * ���췽���������ļ�������ָ����Դ
     *
     * @param string $name �ļ���·��
     * @param string $mode �ļ�����������
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
     * �����������ر��Ѵ򿪵��ļ�ָ��
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * �ر��Ѵ򿪵��ļ�ָ��
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
     * ��ȡ��ǰ�Ѵ򿪵��ļ�ָ��
     *
     * @return resource
     */
    public function getHandle() {
        return $this->handle;
    }

    /**
     * ��ȡ���length���ֽ�
     *
     * @param int $length ����ȡlength���ֽ�
     * @return string
     */
    public function read($length) {
        return fread($this->handle, $length);
    }
    
    /**
     * ��ȡ�ļ������ֽ�
     *
     * @param int $bucketSize һ�ζ�ȡ���ֽ���
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
     * ���ļ�ָ���ж�ȡһ��
     *
     * @param int $length ��ȡһ�����length - 1���ֽڣ�ʡ��Ϊ��ȡ����ֱ���н���
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
     * д��lendth���ֽڵ��ļ�ָ�봦
     * 
     * @param string $content Ҫд����ַ�������
     * @param int $length ��ָ����length,д��length���ֽں�ֹͣд��
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
     * �����ļ�ָ���Ƿ����ļ�������λ��
     * 
     * @return boolean
     */
    public function eof() {
        return feof($this->handle);
    }

    /**
     * �����ļ�ָ���λ��
     * 
     * @return boolean
     */
    public function rewind() {
        rewind($this->handle);
    }
}

/**
 * �ļ���Ŀ¼�����ӿ�
 * 
 */
interface FileOperable {
	/**
	 * �����ļ���Ŀ¼
	 */
	public function create();
	
	/**
	 * ɾ���ļ���Ŀ¼
	 */
	public function remove();
	
	/**
	 * �������ļ���Ŀ¼
	 * 
	 * @param string $newname �µ��ļ���Ŀ¼��
	 */
	public function rename($newname);
	
	/**
	 * ����ļ���Ŀ¼�Ƿ����
	 */
	public function exists();
	
}
?>
