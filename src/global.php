<?php
use samson\resourcer\ResourceRouter;

/**
 * Route(Маршрут) - Получить экземпляр класса для работы с маршрутами системы
 * @see ResourceRouter
 * @deprecated 
 * @return ResourceRouter Экземпляр класса для работы с маршрутами системы
 */
function & route(){	static $_v; return ( $_v = isset($_v) ? $_v : new ResourceRouter()); }

/**
 * SRC(Source) - Источник - сгенерирвать URL к ресурсу веб-приложения
 * Данный метод определяет текущее место работы веб-приложения
 * и строит УНИКАЛЬНЫЙ путь к требуемому ресурсу.
 *
 *  Это позволяет подключать CSS/JS/Image ресурсы в HTML/CSS не пережевая
 *  за их физическое месторасположение относительно веб-приложения, что
 *  в свою очередь дает возможность выносить модули(делать их внешними),
 *  а так же и целые веб-приложения.
 *
 * @param string $src 		Путь к ресурсу модуля
 * @param string $module 	Имя модуля которому принадлежит ресурс
 * @param string $return 	Флаг необходимо ли возвращать значение
 * @return string Возвращает сгенерированный адресс ссылки на получение ресурса для браузера
 */
function src( $src = '', $module = NULL ){ echo ResourceRouter::url( $src, $module );}

/** Perform custom simple URL parsing to match needed URL for static resource serving */
$url = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';

// Remove BASE from url path to support internal web-applications
if(($basePos = strpos($url, __SAMSON_BASE__)) == 0) {
    $url = substr($url, strlen(__SAMSON_BASE__));
}

// Get URL path from URL and split with "/"
$url = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));
$module = isset($url[0]) ? $url[0] : '';
$method = isset($url[1]) ? $url[1] : '';

/**
 * Special hook to avoid further framework loading if this is static resource request
 */
if ($module === 'resourcer' && $method != 'table') {

    // Запретим вывод ошибок
    //Error::$OUTPUT = false;

    // Получить путь к ресурсу системы по URL
    $filename = ResourceRouter::parse($_GET['p'], $method);

    // Проверим существует ли ресурс реально
    if (file_exists($filename)) {
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
        header('ETag:' . $s_etag);

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

    // Avoid further request processing
    die();
}
