<?php
/**
 * YFileCache class file
 *
 * @author Wu Miao Hui <363539981@qq.com>
 * @link http://www.wmhfly.com/
 * @copyright 2014/02/13
 */

/**
 * YFileCache 文件缓存扩展
 *
 * 在CFileCache缓存的实现的基础上，扩展一个缓存清单列表功能，供应用程序后台可视化管理
 *
 * See {@link CCache} manual for common cache operations that are supported by CFileCache.
 *
 * @property integer $gCProbability The probability (parts per million) that garbage collection (GC) should be performed
 * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
 *
 * @author Wu Miao Hui <363539981@qq.com>
 * @package extensions.cache
 */
class YFileCache extends CCache
{
	/**
	 * @var string the directory to store cache files. Defaults to null, meaning
	 * using 'protected/runtime/cache' as the directory.
	 */
	public $cachePath;
	/**
	 * @var string cache file suffix. Defaults to ''.
	 */
	public $cacheFileSuffix='';
	/**
	 * @var string 缓存清单文件
	 */
	public $cacheMenuName='_CACHE_MENU_.php';
	/**
	 * @var integer the level of sub-directories to store cache files. Defaults to 0,
	 * meaning no sub-directories. If the system has huge number of cache files (e.g. 10K+),
	 * you may want to set this value to be 1 or 2 so that the file system is not over burdened.
	 * The value of this property should not exceed 16 (less than 3 is recommended).
	 */
	public $directoryLevel=0;
	/**
	 * @var boolean whether cache entry expiration time should be embedded into a physical file.
	 * Defaults to false meaning that the file modification time will be used to store expire value.
	 * True value means that first ten bytes of the file would be reserved and used to store expiration time.
	 * On some systems PHP is not allowed to change file modification time to be in future even with 777
	 * permissions, so this property could be useful in this case.
	 * @since 1.1.14
	 */
	public $embedExpiry=false;

	/**
	 * @var array 缓存清单
	 */
	private $_cacheMenu;
	private $_gcProbability=100;
	private $_gced=false;

	/**
	 * Initializes this application component.
	 * This method is required by the {@link IApplicationComponent} interface.
	 */
	public function init()
	{
		parent::init();
		if($this->cachePath===null)
			$this->cachePath=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'cache';
		if(!is_dir($this->cachePath))
			mkdir($this->cachePath,0777,true);
		
		/**
		 * 扩展说明：判断是否存在缓存清单文件，无则创建
		 */
		$cacheMenuFile = $this->getCacheMenuPath();
		if(!is_file($cacheMenuFile)){
			@file_put_contents($cacheMenuFile, "<?php\r\nreturn array();");
			@chmod($cacheMenuFile,0777);
		}
	}

	/**
	 * @return integer the probability (parts per million) that garbage collection (GC) should be performed
	 * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
	 */
	public function getGCProbability()
	{
		return $this->_gcProbability;
	}

	/**
	 * @param integer $value the probability (parts per million) that garbage collection (GC) should be performed
	 * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
	 * This number should be between 0 and 1000000. A value 0 meaning no GC will be performed at all.
	 */
	public function setGCProbability($value)
	{
		$value=(int)$value;
		if($value<0)
			$value=0;
		if($value>1000000)
			$value=1000000;
		$this->_gcProbability=$value;
	}

	/**
	 * Deletes all values from cache.
	 * This is the implementation of the method declared in the parent class.
	 * @return boolean whether the flush operation was successful.
	 * @since 1.1.5
	 */
	protected function flushValues()
	{
		$this->gc(false);
		return true;
	}

	/**
	 * Retrieves a value from cache with a specified key.
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key a unique key identifying the cached value
	 * @return string|boolean the value stored in cache, false if the value is not in the cache or expired.
	 */
	protected function getValue($key)
	{
		$cacheFile=$this->getCacheFile($key);
		if(($time=$this->filemtime($cacheFile))>time())
			return @file_get_contents($cacheFile,false,null,$this->embedExpiry ? 10 : -1);
		elseif($time>0)
			$this->updateAndDelete($key, $cacheFile);
		return false;
	}

	/**
	 * Stores a value identified by a key in cache.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function setValue($key,$value,$expire)
	{
		//自动回收过期缓存，有万分之一的可能会执行-删除过期缓存文件操作
		if(!$this->_gced && mt_rand(0,1000000)<$this->_gcProbability)
		{
			$this->gc();
			$this->_gced=true;
		}

		if($expire<=0)
			$expire=31536000; // 1 year
		$expire+=time();

		$cacheFile=$this->getCacheFile($key);
		if($this->directoryLevel>0)
			@mkdir(dirname($cacheFile),0777,true);
		if(@file_put_contents($cacheFile,$this->embedExpiry ? $expire.$value : $value,LOCK_EX)!==false)
		{
			/**
			 * 扩展代码，新增缓存$key和过期时间加入到清单
			 */
			$this->initCacheMenu();
			$this->_cacheMenu[$key] = @filesize($cacheFile).'|'.time().'|'.$expire;
			@chmod($cacheFile,0777);
			return $this->embedExpiry ? true : @touch($cacheFile,$expire);
		}
		else
			return false;
	}

	/**
	 * Stores a value identified by a key into cache if the cache does not contain this key.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function addValue($key,$value,$expire)
	{
		$cacheFile=$this->getCacheFile($key);
		if($this->filemtime($cacheFile)>time())
			return false;
		return $this->setValue($key,$value,$expire);
	}

	/**
	 * Deletes a value with the specified key from cache
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	protected function deleteValue($key)
	{
		$cacheFile=$this->getCacheFile($key);
		$this->updateAndDelete($key, $cacheFile);
		return true;
	}

	/**
	 * Returns the cache file path given the cache key.
	 * @param string $key cache key
	 * @return string the cache file path
	 */
	protected function getCacheFile($key)
	{
		if($this->directoryLevel>0)
		{
			$base=$this->cachePath;
			for($i=0;$i<$this->directoryLevel;++$i)
			{
				if(($prefix=substr($key,$i+$i,2))!==false)
					$base.=DIRECTORY_SEPARATOR.$prefix;
			}
			return $base.DIRECTORY_SEPARATOR.$key.$this->cacheFileSuffix;
		}
		else
			return $this->cachePath.DIRECTORY_SEPARATOR.$key.$this->cacheFileSuffix;
	}

	/**
	 * Removes expired cache files.
	 * @param boolean $expiredOnly whether only expired cache files should be removed.
	 * If false, all cache files under {@link cachePath} will be removed.
	 * @param string $path the path to clean with. If null, it will be {@link cachePath}.
	 */
	public function gc($expiredOnly=true,$path=null)
	{
		if($path===null)
			$path=$this->cachePath;
		if(($handle=opendir($path))===false)
			return;
		while(($file=readdir($handle))!==false)
		{
			if($file[0]==='.')
				continue;
			$fullPath=$path.DIRECTORY_SEPARATOR.$file;
			if(is_dir($fullPath))
				$this->gc($expiredOnly,$fullPath);
			elseif($expiredOnly && $this->filemtime($fullPath)<time() || !$expiredOnly)
				$this->updateAndDelete($file, $fullPath);
		}
		closedir($handle);
	}

	/**
	 * Returns cache file modification time. {@link $embedExpiry} aware.
	 * @param string $path to the file, modification time to be retrieved from.
	 * @return integer file modification time.
	 */
	private function filemtime($path)
	{
		if($this->embedExpiry)
			return (int)@file_get_contents($path,false,null,0,10);
		else
			return @filemtime($path);
	}
	
	/**
	 * 获取缓存清单路径
	 * @return string
	 */
	public function getCacheMenuPath(){
		return $this->cachePath.$this->cacheMenuName;
	}
	
	/**
	 * 更新缓存清单和删除缓存文件
	 * @param string $key
	 * @param string $path
	 */
	private function updateAndDelete($key,$path){
		$this->initCacheMenu();
		unset($this->_cacheMenu[$key]);
		@unlink($path);
	}	
	
	/**
	 * 初始化缓存清单列表
	 * @return {@link cacheMenu}
	 */
	private function initCacheMenu(){
		if($this->_cacheMenu===null)
			$this->_cacheMenu = include($this->getCacheMenuPath());
	}
	
	/**
	 * GET 获取缓存清单列表
	 * @return array
	 */
	public function getCacheMenu(){
		$this->initCacheMenu();
		return $this->_cacheMenu;
	}
	
	/**
	 * 析构函数，最后保存修改缓存清单
	 */
	public function __destruct() {
		$cacheMenuFile = $this->getCacheMenuPath();
		if(is_file($cacheMenuFile)&&$this->_cacheMenu!==null){
			$cacheMenuStr = var_export($this->_cacheMenu,TRUE);
			$cacheMenuStr = "<?php\r\nreturn ".$cacheMenuStr.";";
			@file_put_contents($cacheMenuFile, $cacheMenuStr);
			@chmod($cacheMenuFile,0777);
		}
	}
	
}