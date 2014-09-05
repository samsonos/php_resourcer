<?php
namespace samson\resourcer;

use samson\core\iModule;
use samson\core\File;
use \samson\core\ExternalModule;

/**
 * Класс для определения, построения и поиска путей к ресурсам
 * системы. Класс предназначен для формирования УНИКАЛЬНЫХ URL
 * описывающих путь к ресурсу веб-приложения/модуля независимо
 * от его расположения на HDD.
 *
 * Создавая возможность один рас описать путь вида:
 * 	ИМЯ_РЕСУРСА - ИМЯ_ВЕБПРИЛОЖЕНИЯ - ИМЯ_МОДУЛЯ
 *
 * И больше не задумываться об реальном(физическом) местоположении
 * ресурса
 *
 * @package SamsonPHP
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @author Nikita Kotenko <nick.w2r@gmail.com>
 * @version 1.0
 */
class ResourceRouter extends ExternalModule
{
    /** Коллекция маршрутов к модулям */
    public static $routes = array();

    /** @var string Marker for inserting generated javascript link */
    public $javascriptMarker = '</body>';

    /** Коллекция MIME типов для формирования заголовков */
    public static $mime = array
    (
        'css' 	=> 'text/css',
        'woff' 	=> 'application/x-font-woff',
        'otf' 	=> 'application/octet-stream',
        'ttf' 	=> 'application/octet-stream',
        'eot' 	=> 'application/vnd.ms-fontobject',
        'js'	=> 'application/x-javascript',
        'htm'	=> 'text/html;charset=utf-8',
        'htc'	=> 'text/x-component',
        'jpg'	=> 'image/jpeg',
        'png'	=> 'image/png',
        'jpg' 	=> 'image/jpg',
        'gif'	=> 'image/gif',
        'txt'	=> 'text/plain',
        'pdf'	=> 'application/pdf',
        'rtf'	=> 'application/rtf',
        'doc'	=> 'application/msword',
        'xls'	=> 'application/msexcel',
        'xls' 	=> 'application/vnd.ms-excel',
        'xlsx'	=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx'	=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );

    /** Идентификатор модуля */
    protected $id = 'resourcer';

    /** Автор модуля */
    protected $author = array( 'Vitaly Iegorov', 'Nikita Kotenko');

    /** Версия модуля */
    protected $version = '1.1.1';

    /** Cached resources path collection */
    public $cached = array();

    /** Collection of updated cached resources for notification of changes */
    public $updated = array();

    /** Pointer to processing module */
    private $c_module;

    /** Default controller */
    public function __BASE()
    {
        $this->init();
    }

    /**
     * Core render handler for including CSS and JS resources to html
     *
     * @param sting $view View content
     * @param array $data View data
     * @return string Processed view content
     */
    public function renderer(&$view, $data = array(), iModule $m = null)
    {
        // Define resource urls
        $css = url()->base().$this->cached['css'];
        $js = url()->base().$this->cached['js'];

        // TODO: Прорисовка зависит от текущего модуля, сделать єто через параметр прорисовщика
        // If called from compressor
        if( $m->id() == 'compressor' || $m->id() == 'deploy' )
        {
            $css = url()->base().basename($this->cached['css']);
            $js = url()->base().basename($this->cached['js']);
        }

        // Put css link at the end of <head> page block
        $view = str_ireplace( '</head>', "\n".'<link type="text/css" rel="stylesheet" href="'.$css.'">'."\n".'</head>', $view );

        // Put javascript link in the end of the document
        $view = str_ireplace( $this->javascriptMarker, "\n".'<script type="text/javascript" src="'.$js.'"></script>'."\n".$this->javascriptMarker, $view );

        //elapsed('Rendering view =)');

        return $view;
    }

    /**	@see ModuleConnector::init() */
    public function init( array $params = array() )
    {
        parent::init( $params );

        // Создадим имя файла содержащего пути к модулям
        $map_file = md5( implode( '', array_keys(s()->module_stack))).'.map';

        // Если такого файла нет
        if ( $this->cache_refresh( $map_file ) )
        {
            // Fill in routes collection
            foreach (s()->module_stack as $id => $module ) self::$routes[ $id ] = $module->path();

            // Save routes to file
            file_put_contents( $map_file, serialize( self::$routes ));
        }

        // Cache main web resources
        foreach (array(array('js'),array('css','less'),array('coffee')) as $rts) {
            // Get first resource type as extension
            $rt = $rts[0];

            $hash_name = '';

            // Iterate gathered namespaces for their resources
            foreach (s()->load_stack as $ns => & $data) {
                // If necessary resources has been collected
                foreach ($rts as $_rt) {
                    if (isset($data['resources'][ $_rt ])) {
                        foreach ($data['resources'][ $_rt ] as & $resource) {
                            // Created string with last resource modification time
                            $hash_name .= filemtime( $resource );
                        }
                    }
                }
            }

            // Get hash that's describes resource status
            $hash_name = md5( $hash_name ).'.'.$rt;

            $path = $hash_name;

            // Check if cache file has to be updated
            if( $this->cache_refresh( $path ) )
            {
                // Read content of resource files
                $content = '';
                foreach (s()->load_module_stack as $id => $data) {
                    $this->c_module = & m( $id );

                    // If this ns has resources of specified type
                    foreach ($rts as $_rt) {
                        if (isset($data['resources'][ $_rt ])) {
                            //TODO: If you will remove & from iterator - system will fail at last element
                            foreach ($data['resources'][ $_rt ] as & $resource) {
                                elapsed($resource);
                                // Read resource file
                                $c = file_get_contents( $resource );

                                // Rewrite url in css
                                if( $rt == 'css')
                                {
                                    $c = preg_replace_callback( '/url\s*\(\s*(\'|\")?([^\)\s\'\"]+)(\'|\")?\s*\)/i', array( $this, 'src_replace_callback'), $c );
                                }

                                // Gather processed resource text together
                                $content .= "\n\r".$c;
                            }
                        }
                    }
                }

                // Fix updated resource file with new path to it
                $this->updated[ $rt ] = $path;

                // Запишем содержание нового "собранного" ресурса
                file_put_contents( $path, $content );
            }

            // Save path to resource cache
            $this->cached[ $rt ] = __SAMSON_CACHE_PATH.'/'.$this->id.'/'.$hash_name;
        }

        // Subscribe to core rendered event
        s()->subscribe('core.rendered', array( $this, 'renderer'));

        // Register view renderer
        //s()->renderer( array( $this, 'renderer') );
    }

    /** Callback for CSS url rewriting */
    public function src_replace_callback( $matches )
    {
        // Если мы нашли шаблон - переберем все найденные патерны
        if( isset( $matches[2]) )
        {
            $_url = $matches[2];

            // Получим путь к ресурсу используя маршрутизацию
            $url = str_replace('../','', $matches[2] );

            //trace($this->c_module->id.'-'.get_class($this->c_module).'-'.$url.'-'.is_a( $this->c_module, ns_classname('ExternalModule','samson\core')));;

            // Always rewrite url's for external modules
            if( is_a( $this->c_module, ns_classname('ExternalModule','samson\core')) )
            {
                $url = self::url( $url, $this->c_module );
                //trace('external module url:'.$url);
            }
            // Always rewrite url's for remote web applications
            else if( __SAMSON_REMOTE_APP )
            {
                $url = self::url( $url, $this->c_module );
                elapsed('remote web-app url:'.$url);
            }
            // Do not rewrite url's for local resources
            else if( is_a($this->c_module, ns_classname('LocalModule','samson\core')))
            {
                $url = url()->base().$url;
                //trace('local module url:'.$url);
            }

            return 'url("'.$url.'")';
        }
    }

    /**
     * Получить путь к ресурсу веб-приложения/модуля по унифицированному URL
     *
     * @param string $path 			Относительный путь к ресурсу модуля/приложения
     * @param string $destination 	Имя маршрута из таблицы маршрутизации
     * @return string Физическое относительное расположение ресурса в системе
     */
    public static function parse( $path, $destination = 'local' )
    {
        // Найдем в рабочей папке приложения файл с маршрутами
        $result = array();
        foreach ( File::dir( __SAMSON_CWD__.__SAMSON_CACHE_PATH, 'map', '', $result ) as $file )
        {
            // Прочитаем файл с маршрутами и загрузим маршруты
            self::$routes = unserialize( file_get_contents( $file ) );

            // Остановим цикл
            break;
        }

        // Если передан слеш в пути - отрежим его т.к. пути к модулям его обязательно включают
        if( $path[0] == '/' ) $path = substr( $path, 1 );

        // Сформируем путь к модулю/предложению, если он задан
        // и добавим относительный путь к самому ресурсу
        $path = ( isset( self::$routes[$destination] ) ? self::$routes[ $destination ] : '').$path;

        // Вернем полученный путь
        return $path;
    }

    /**
     * Parse URL to get module name and relative path to resource
     * @param string $url String for parsing
     * @return array Array [0] => module name, [1]=>relative_path
     */
    public static function parseURL( $url, & $module = null, & $path = null  )
    {
        // If we have URL to resource router
        if( preg_match('/resourcer\/(?<module>.+)\?p=(?<path>.+)/ui', $url, $matches ))
        {
            $module = $matches['module'];
            $path = $matches['path'];

            return true;
        }
        else return false;
    }

    /**
     * Получить уникальный URL однозначно определяющий маршрут к ресурсу
     * веб-приложения/модуля
     *
     * @param string $path 		Путь к требуемому ресурсу вннутри веб-приложения/модуля
     * @param string $module	Имя модуля которому принадлежит ресурс
     * @param string $app		Имя веб-приложения которому принадлежит ресурс
     * @return string Унифицированный URL для получения ресурса веб-приложения/модуля
     */
    public static function url( $path, $_module = NULL  )
    {
        // Безопасно получим переданный модуль
        $_module = s()->module( $_module );

        // Сформируем URL-маршрут для доступа к ресурсу
        return 'http://'.$_SERVER['HTTP_HOST'].url()->base().'resourcer/'.($_module->id()!= 'resourcer'?$_module->id():'').'?p='.$path;
    }
}
