# Yii cache 缓存管理

在CFileCache的基础上扩展，目的是要实现网站后台可视化管理

Yii配置：

'components'=>array(
	'cache'=>array(
		'class'=>'ext.cache.YFileCache',
		'hashKey'=>false,
		'keyPrefix'=>'',
		'cachePath'=>'缓存路径'
	)
)


缓存操作:

	Yii::app()->cache->set('key',value);
	Yii::app()->cache->get('key');
	Yii::app()->cache->delete('key');
	
	Yii::app()->cache->flush();

读取缓存清单列表：

	$menu = Yii::app()->cache->cacheMenu;