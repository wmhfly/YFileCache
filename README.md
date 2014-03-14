# Yii cache 缓存管理

Menu cache可视化管理，对yii的缓存进行扩展修改，实现后台可以查询缓存，自定义管理操作它们

Yii配置：

// application components
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

缓存操作:

	Yii::app()->cache->set('key',value);
	Yii::app()->cache->get('key');
	Yii::app()->cache->delete('key');
	
	Yii::app()->cache->flush();

读取缓存清单列表：

	$menu = Yii::app()->cache->cacheMenu;