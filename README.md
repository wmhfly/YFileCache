/**
 * Yii  缓存清单管理
 * link: wmhfly.com
 */

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