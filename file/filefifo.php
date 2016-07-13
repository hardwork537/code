<?php
/**
 * Filefifo.php 文件型FIFO队列
 *
 * @author     jiaxiongliu@tencent.com <chuyinfeng@gmail.com>
 * @team       IEG-运服中心-产品研发组-心悦俱乐部(xinyue.qq.com)
 *
 */
class Filefifo
{
    /**
     * $_file_data, 数据文件的路径 
     */
    private $_file_data = '';
    
    /**
     * $_file_idx, 索引文件的路径
     */
    private $_file_idx = '';

    /**
     * $_file_idx_bak, 索引备份文件的路径, 防止意外断电等导致索引文件破坏
     */
    private $_file_idx_bak = '';

    /**
     * $_f_data, 数据文件的句柄
     */
    private $_f_data;
    
    /**
     * $_f_idx, 索引文件句柄
     */
    private $_f_idx;

    /**
     * $_f_idx_bak, 索引备份文件句柄
     */
    private $_f_idx_bak;

    private static $_instance = array();


    public static function instance($file)
    {
        if (! isset(self::$_instance[$file])) {
            self::$_instance[$file] = new self($file);
        }
        return self::$_instance[$file];
    }

    public function __construct($file)
    {
       $this->attach($file);
    }

    public function __destruct()
    {
        $this->detach();       
    }

    /**
     * attach, 挂接一个队列文件
     */
    public function attach($file)
    {
        /**
         * 初始化文件
         */
        $this->_file_data = $file;
        $this->_file_idx = "{$file}.idx";
        $this->_file_idx_bak = "{$file}.idx.bak";

        if (! file_exists($file)) {
            $f = fopen($file, 'w+');
            fclose($f);

            if (file_exists($this->_file_idx)) unlink($this->_file_idx);
            if (file_exists($this->_file_idx_bak)) unlink($this->_file_idx_bak);
        }

        $idx_data_bak = '';

        /**
         * 有备份则读取备份数据，无备份则创建空备份文件
         */
        if (file_exists($this->_file_idx_bak)) {
            $idx_data_bak = file_get_contents($this->_file_idx_bak);
        } else {
            $f = fopen($this->_file_idx_bak, 'w+');
            fclose($f);
        }

        /**
         * 不存在索引文件则创建，并从索引备份中恢复
         */
        if (! file_exists($this->_file_idx)) {
            $f = fopen($this->_file_idx, 'w+');  
            if ($idx_data_bak) fwrite($f, $idx_data_bak);                     
            fclose($f);
        } else {
            if (! file_get_contents($this->_file_idx) && $idx_data_bak) {
                file_put_contents($this->_file_idx, $idx_data_bak);
            }
        }

        $this->_f_data = fopen($this->_file_data, 'a+b');
        $this->_f_idx = fopen($this->_file_idx, 'rw+b');
        $this->_f_idx_bak = fopen($this->_file_idx_bak, 'rw+b');
    }

    /**
     * detach, 分离当前队列文件
     */
    private function detach()
    {
        if ($this->_f_data) fclose($this->_f_data);
        if ($this->_f_idx) fclose($this->_f_idx);
        if ($this->_f_idx_bak) fclose($this->_f_idx_bak);
        $this->_f_data = NULL;
        $this->_f_idx = NULL;
        $this->_f_idx_bak = NULL;
    }

    /**
     * rewind, 设置到队列头
     */
    public function rewind()
    {
        flock($this->_f_idx, LOCK_EX);
        ftruncate($this->_f_idx, 0);
        ftruncate($this->_f_idx_bak, 0);
        flock($this->_f_idx, LOCK_UN);
    }

    /**
     * end, 设置到队列尾
     */
    public function end()
    {
        flock($this->_f_idx, LOCK_EX);
        // 重新计算数据文件行数
        $line = $this->len();
        $file_len = filesize($this->_file_data);
        fseek($this->_f_data, $file_len);   

        ftruncate($this->_f_idx, 0);
        rewind($this->_f_idx);        
        fwrite($this->_f_idx, $file_len.",".$line);

        ftruncate($this->_f_idx_bak, 0);
        rewind($this->_f_idx_bak);
        fwrite($this->_f_idx_bak, $file_len.",".$line);

        flock($this->_f_idx, LOCK_UN);
    }

    /**
     * pos, 获取当前队列位置
     */
    public function pos()
    {
        flock($this->_f_idx, LOCK_EX);
        rewind($this->_f_idx);
        $data_idx = fgets($this->_f_idx, 1024);
        $data_idx = explode(",", $data_idx);
        $pos = (int) trim($data_idx[0]);
        $line = isset($data_idx[1]) ? (int) trim($data_idx[1]) : 0;
        flock($this->_f_idx, LOCK_UN);

        return array('pos' => $pos, 'line' => $line);

    }

    /**
     * len, 获取队列总长度
     */
    public function len()
    {
        flock($this->_f_data, LOCK_EX);

        $old_pos = ftell($this->_f_data);
        rewind($this->_f_data);
        $line = 0;
        while (fgets($this->_f_data, 1024) !== FALSE) $line ++; 
        fseek($this->_f_data, $old_pos);

        flock($this->_f_data, LOCK_UN);

        return $line;
    }
  

    /**
     * pop, 先进先出顺序弹出多条记录
     *     
     * @param int $num, 一次性返回多条记录
     * @param array $cur_pos, 返回当前记录所在偏移量、文件行位置信息  
     * @return array | boolean, 返回字符串数组记录，失败则返回FALSE
     */
    public function pop($num = 1, & $cur_pos = array())
    {
        $num = $num < 1 ? 1 : $num;

        /**
         * 锁定索引文件，读取索引内容
         */
        flock($this->_f_idx, LOCK_EX);
        rewind($this->_f_idx);
        $data_idx = fgets($this->_f_idx, 1024);
        $data_idx = explode(",", $data_idx);
        $pos = (int) trim($data_idx[0]);
        $line = isset($data_idx[1]) ? (int) trim($data_idx[1]) : 0;

        $data_all = array();
        for ($i = 0; $i < $num; $i ++) {
            /**
             * 根据索引位置，读取数据文件
             */
            fseek($this->_f_data, $pos);
            $data = fgets($this->_f_data, 8192);

            /**
             * 如果读取成功则更新索引记录
             */
            if ($data !== FALSE) {
                $pos = ftell($this->_f_data);
                $line ++;

                rewind($this->_f_idx);
                ftruncate($this->_f_idx, 0);
                fwrite($this->_f_idx, "{$pos},{$line}");   

                rewind($this->_f_idx_bak);
                ftruncate($this->_f_idx_bak, 0);
                fwrite($this->_f_idx_bak, "{$pos},{$line}");         
            } else {
                break;
            }

            $data_all[$line] = $data;
        }


        flock($this->_f_idx, LOCK_UN);

        $cur_pos = array(
            'pos' => $pos,
            'line' => $line,
        );

        return $data_all ? $data_all : FALSE;
    }

    /**
     * push, 队尾压入多条记录
     *
     * @param string | array $data, 字符串数据，不能包含回车换行，否则会追加多条记录
     * @return int, 返回插入的记录条数
     */
    public function push($data)
    {
        if (! is_array($data)) {
            $data = array($data);
        }

        $count = 0;

        /**
         * 锁定数据文件，追加记录
         */
        flock($this->_f_data, LOCK_EX);
        if (is_array($data)) {
            foreach ($data as $line) {
                fwrite($this->_f_data, $line."\r\n");
                $count ++;
            }
        }
        flock($this->_f_data, LOCK_UN);

        return $count;

    }

    /**
     * del, 清空一个队列
     */
    public function del()
    {
        $this->detach();
        unlink($this->_file_data);
        unlink($this->_file_idx);
        unlink($this->_file_idx_bak);

        return TRUE;
    }
}
