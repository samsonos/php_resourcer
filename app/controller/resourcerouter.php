<?php
use samson\resourcer\ResourceRouter;
use samson\core\Error;

/**
 * Универсальный контроллер для выдачи ресурса системы по унифицированному URL в ответ браузера
 *
 * Метод автоматически проверяет валидность запрашиваемого клиентом ресурса
 * сравния специальный заголовок HTTP запроса ETag, и принимает решение необходимо
 * ли обновить ресурс клиента или же использовать имеющийся в его кеше
 *
 * @param string $path		Относительный путь к ресурсу
 * @param string $module	Название модуля/приложения в таблице маршрутов 
 *
 * @return	Выводит в текущий поток вывода содержание ресурса предварительно
 * 			установив необходимые заголовки ответа для клиента
 */
function resourcer__HANDLER( $module = 'local' )
{	
	// Запретим вывод ошибок
	//Error::$OUTPUT = false;
	
	// Ассинхронный ответ
	s()->async(true);

	// Получить путь к ресурсу системы по URL
	$filename = ResourceRouter::parse( $_GET['p'], $module );	
				
	// Проверим существует ли ресурс реально
	if( file_exists( $filename ) )
	{			
		// Этот параметр характеризирует время последней модификации ресурса
		// и любые его доп параметры конечно( ПОКА ТАКИХ НЕТ )
		$c_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';
			
		// Получим параметр от сервера как отметку времени последнего
		// изменения оригинала ресурса и любые его доп параметры
		// конечно( ПОКА ТАКИХ НЕТ )
		$s_etag = filemtime( $filename );
	
		// Установим заголовки для кеширования
		// Поддержка кеша браузера
		header('Cache-Control:max-age=1800');
	
		// Установим заголовок с текущим значением параметра валидности ресурса
		header('ETag:' . $s_etag  );

		// Get file extension
		$extension = pathinfo($filename, PATHINFO_EXTENSION );
		
		// Если эти параметры совпадают - значит оригинал ресурса в кеше клиента - валидный
		// Сообщим об этом клиенту специальным заголовком
		if( $c_etag == $s_etag ) header( 'HTTP/1.1 304 Not Modified' );
		// Если эти параметры НЕ совпадают - значит оригинал ресурса был изменен
		// и мы поддерживаем данное расширение для выдачи как ресурс
		else if( isset( ResourceRouter::$mime[ $extension ]))
		{		
			// Укажем тип выдаваемого ресурса
			header('Content-type: '.ResourceRouter::$mime[ $extension ] );			
			
			// Выведем содержимое файла
			echo file_get_contents( $filename );
		}
		// Мы не поддерживаем указанное расширение файла для выдачи как ресурс
		else header('HTTP/1.0 404 Not Found');
	}
	// Требуемый файл не существует на сервере
	else header('HTTP/1.0 404 Not Found');		
}

/** Получить реальный путь к ресурсу */
function resourcer_parse( $module = 'local' )
{
	s()->async( true );
	
	// Получить путь к ресурсу системы по URL
	$filename = ResourceRouter2::parse( $_GET['p'], $module );
	
	// Выведем маршрут
	trace($module.'#'.$_GET['p'].' -> '.$filename);
	
}

/** Получить реальный путь к ресурсу */
function resourcer_table()
{
	s()->async( true );	
	
	$path = __SAMSON_CWD__.__SAMSON_CACHE_PATH.'/resourcer/';
	
	if ( file_exists($path)&& ($handle = opendir($path)) )
	{		
		//Именно этот способ чтения элементов каталога является правильным. 
		while ( FALSE !== ( $entry = readdir( $handle ) ) )
		{
			
			// Найдем фацл с расширением map
			if (pathinfo( $entry, PATHINFO_EXTENSION ) == 'map')
			{
				$text = file( $path.$entry);
				
				$table = isset($text[0])?unserialize($text[0]) : array();
				
				break;
			}
		}
	}
	trace($table);
}

// Если выполнено обращение к роутеру ресурсов то вручную перехватим его обработку что-бы ускорить
// время возвращения ресурсов для клиента
if( url()->module() === 'resourcer' && url()->method() != 'table' ) die( resourcer__HANDLER( url()->method() ) );