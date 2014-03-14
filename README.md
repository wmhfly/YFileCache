Yii cache 缓存管理
====================

在CFileCache的基础上扩展，目的是要实现网站后台可视化管理。

#安装

/extensions/cache/YFileCache.php

#app配置

	'components'=>array(
		...
		'cache'=>array(
			'class'=>'ext.cache.YFileCache',
			'hashKey'=>false,
			'keyPrefix'=>'',
			'cachePath'=>'缓存路径'
		)
		...
	)

#缓存操作

> Yii::app()->cache->set('key',value);
> Yii::app()->cache->get('key');
> Yii::app()->cache->delete('key');
> Yii::app()->cache->flush();

#读取缓存清单列表

$menu = Yii::app()->cache->cacheMenu;

#cacheMenu数据参数说明
	
	key => values
	
	values = 缓存大小|缓存开始时间|缓存过期时间
	
	$menu =  array (
	  'test_new' => '35|1392350158|1423886158',
	  'test_hot' => '46|1392358446|1423894446',
	  'Navigation' => '35|1392708663|1392708783',
	);