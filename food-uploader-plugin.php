<?php
/*
	Plugin Name:        Food File Uploader
	Plugin URI:         https://github.com/ProjectSoft-STUDIONIONS/food-uploader-plugin
	Description:        WordPress Плагин для загрузки файлов xlsx в папку /food/, доступен только администраторам. Плагин актуален для сайтов школ России.
	Version:            1.0.1
	Author:             Чернышёв Андрей aka ProjectSoft <projectsoft2009@yandex.ru>
	Author URI:         https://projectsoft.ru/
	GitHub Plugin URI:  https://github.com/ProjectSoft-STUDIONIONS/food-uploader-plugin
	License:            MIT
	License URI:        https://mit-license.org/
	Donate link:        https://projectsoft.ru/donate/
	Domain Path:        languages/
	Text Domain:        food-uploader-plugin
	Requires at least:  5.7
	Requires PHP:       7.4
*/

if (!defined('ABSPATH')) die();

if(!defined('FOOD_LINK_PAGE')):
	define('FOOD_LINK_PAGE', 'food_uploader');
endif;

// Нормальный вид ABSPATH
$abs_path = rtrim(preg_replace('/\\\\/', '/', ABSPATH), '/');
define('FOOD_ABSPATH', $abs_path);


global $mask_extensions, $allowed_types, $mask_folder, $all, $plugin_name;

$mask_extensions = array("xlsx");
$allowed_types = array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

$mask_folder = array("food");

$plugin_name = dirname( plugin_basename( __FILE__ ) );

$local = get_user_locale();

// Инициализация
add_action( 'init', 'food_plugin_init' );

// Добавление стилей, добавление скриптов
add_action('admin_enqueue_scripts', 'food_plugin_add_admin_style_script');

// Создание страницы в админ-панели
add_action('admin_menu', 'food_plugin_add_admin_menu', 30);

function food_plugin_init() {
	global $plugin_name;
	load_plugin_textdomain( 'food-uploader-plugin', false, $plugin_name . '/languages/' );
}

function food_admin_page_url($query = null, array $esc_options = []) {
	global $plugin_name;
	$url = menu_page_url($plugin_name, false);
	if($query) {
		$url .= '&' . (is_array($query) ? http_build_query($query) : (string) $query);
	}
	return esc_url($url, ...$esc_options);
}

function food_plugin_add_admin_menu() {
	global $plugin_name;
	if(class_exists('Transliteration')):
		$trans = new Transliteration();
		$trans->remove_action( 'admin_notices', 'notice__give_us_vote', 1 );
		$trans->remove_action( 'admin_notices', 'notice__buy_me_a_coffee', 1 );
		$trans->remove_action( 'admin_notices', 'notice__ads', 1 );
	endif;
	if (current_user_can('manage_options')) {
		$title = __("daily-meal-menu", "food-uploader-plugin");
		add_menu_page(
			$title,
			$title,
			'manage_options',
			$plugin_name,//'food_uploader',
			'food_plugin_file_uploader_page',
			'dashicons-open-folder',
			26
		);
	}
}

// Функция отображения страницы загрузки файлов
function food_plugin_file_uploader_page() {
	global $plugin_name;
	if (!current_user_can('manage_options')) {
		wp_die(__("you-not-access", "food-uploader-plugin"));
	}
	$max_files = ini_get("max_file_uploads");
	// Контейнер
	echo '<div id="food_plugin" class="container-fluid">
	<div class="row">
		<div class="wrap">';
	// Обработка загрузки файла
	if (isset($_POST['submit']) && !empty($_FILES['food_plugin_file']['name'])) {
		food_plugin_handle_file_upload($_FILES['food_plugin_file']);
	}
	$mode = isset($_POST["mode"]) ? $_POST['mode'] : "";

	switch ($mode) {
		// Обработка переименования
		case 'rename':
			// code...
			if(isset($_POST["file"]) && isset($_POST["new_file"])):
				$file = trim($_POST['file']);
				$new_file = trim($_POST['new_file']);
				if($file && $new_file):
					food_rename_file($new_file, $file);
				endif;
			endif;
			break;
		// Обработка удаления
		case 'delete':
			// code...
			if(isset($_POST["file"])):
				$file = trim($_POST['file']);
				if($file):
					food_delete_file($file);
				endif;
			endif;
			break;
	}

	// Заголовок
	echo '<h1>' . __("daily-meal-menu", "food-uploader-plugin") . '</h1>';
	// Форма загрузки файла
	//echo '<div><pre><code>' . print_r(get_plugin_data(__FILE__), true) . '</code></pre></div>';
	echo '<div class="display-flex">
			<div class="display-flex-help">
				<div class="alert alert-info">
					<h4>' . __("information-block", "food-uploader-plugin") . '</h4>
					' . nl2p(sprintf( __("information-text", "food-uploader-plugin"), $max_files)) . '
				</div>
			</div>
			<form method="post" name="upload_food" enctype="multipart/form-data" action="' . food_admin_page_url() . '">
				<div id="uploader" class="text-right">
					<div class="text-center"><strong>' . __("file-upload-block", "food-uploader-plugin") . '</strong></div>
					<label class="btn btn-default text-uppercase text-nowrap">
						<i class="dashicons dashicons-media-spreadsheet"></i>&nbsp;' . __("select-files", "food-uploader-plugin") . '
						<input type="file" name="food_plugin_file[]" accept=".xlsx" required multiple data-max="' . $max_files . '">
					</label>
					<p id="p_uploads" class="alert alert-info"></p>
					<p>
						<button class="btn btn-success text-uppercase text-nowrap" type="submit" name="submit" value="' . __("send", "food-uploader-plugin") . '"><i class="dashicons dashicons-upload"></i>&nbsp;' . __("send", "food-uploader-plugin") . '</button>
					</p>
				</div>
			</form>
		</div>';
		// Форма действий
		echo '<form method="post" name="modifed" enctype="multipart/form-data" action="' . food_admin_page_url() . '">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="file" value="">
		<input type="hidden" name="new_file">
		<input type="submit" tabindex="-1" name="submited" value="' . __("send", "food-uploader-plugin") . '">
	</form>';
	// Отображение списка загруженных файлов
	food_plugin_display_uploaded_files();
	echo '</div>
	</div>
</div>';
}

// Функция обработки загрузки файла
function food_plugin_handle_file_upload($files) {
	global $allowed_types;
	
	$all = array();
	$all["success"] = "";
	$all["error"] = "";
	
	$upload_dir = food_path_join(FOOD_ABSPATH, "food") . '/';

	// Создание папки, если её нет
	if (!file_exists($upload_dir)):
		@mkdir($upload_dir, 0755, true);
		if (!file_exists($upload_dir)):
			echo '<div class="notice bg-danger"><p><strong>' . __("error-unable-create-directory", "food-uploader-plugin") . '</strong> <pre>food</pre>.</p></div>';
			return;
		endif;
	endif;

	foreach ($files['name'] as $i => $name):
		if (empty($files['tmp_name'][$i])) continue;
		// Преобразуем в нижний регистр
		$name = strtolower($files['name'][$i]);
		// Транслит имени файла
		$name = translit_file($name);
		// На всякий случай
		// Удаляет специальные символы
		$name = preg_replace('/[^A-Za-z0-9\-\_.]/', '', $name);
		// Заменяет несколько тире на одно
		$name = preg_replace('/-+/', '-', $name);
		// Заменяет несколько нижних тире на одно
		$name = preg_replace('/_+/', '_', $name);
		// Проверяем тип файла
		if (!in_array($files['type'][$i], $allowed_types)):
			// Собираем ошибки
			$all["error"] .= '<dt>' . __("error=only-xlsx-files", "food-uploader-plugin") . '</dt><dd>' . $files['name'][$i] . '</dd>';
		else:
			// Продолжаем
			$userfile= array();
			$extension = pathinfo($name, PATHINFO_EXTENSION);
			$userfile['name'] = $name;
			$userfile['type'] = $files['type'][$i];
			$userfile['tmp_name'] = $files['tmp_name'][$i];
			$userfile['error'] = $files['error'][$i];
			$userfile['size'] = $files['size'][$i];
			$userfile['extension'] = $extension;
			if(is_uploaded_file($userfile['tmp_name'])):
				// Удалось загрузить файл
				// Перемещаем файл
				if (@move_uploaded_file($userfile['tmp_name'], $upload_dir . $name)):
					// Удалось переместить файл
					// Меняем аттрибуты файла
					if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN'):
						@chmod($upload_dir . $name, 0644);
					endif;
					// Собираем удачную загрузку
					$all["success"] .= '<dt>' . __("file-uploaded", "food-uploader-plugin") /*$_lang["file_success"]*/ . '</dt><dd>' . $name . '</dd>';
				else:
					// Не удалось переместить файл
					$all["error"] .= '<dt>' . __("failed-move-file", "food-uploader-plugin") /*$_lang["error_movied_file"]*/ . '</dt><dd>' . $files['name'][$i] . '</dd>';
				endif;
			else:
				// Не удалось загрузить файл
				$all["error"] .= '<dt>' . __("failed-upload-file", "food-uploader-plugin") /*$_lang["error_upload_file"]*/ . '</dt><dd>' . $files['name'][$i] . '</dd>';
			endif;
		endif;
	endforeach;

	if($all["error"]):
		echo '<div class="notice bg-danger"><dl class="dl-horizontal">' . $all["error"] . '</dl></div>';
	endif;
	if($all["success"]):
		echo '<div class="notice bg-success"><dl class="dl-horizontal">' . $all["success"] . '</dl></div>';
	endif;
}

// Функция отображения загруженных файлов
function food_plugin_display_uploaded_files() {
	global $mask_extensions;

	$upload_dir = food_path_join(FOOD_ABSPATH, "food") . '/';

	// Создание папки, если её нет
	if (!file_exists($upload_dir)):
		@mkdir($upload_dir, 0755, true);
		if (!file_exists($upload_dir)):
			echo '<div class="notice bg-danger"><p><strong>' . __("error-unable-create-directory", "food-uploader-plugin")/*$_lang["error_createdir"]*/ . '</strong>.</p></div>';
			return;
		else:
			echo '<div class="notice bg-success"><p><strong>' . __("food-create-directory", "food-uploader-plugin")/*$_lang["error_createdir"]*/ . '</strong>.</p></div>';
		endif;
	endif;
	echo '<h4>' . __("uploaded-files", "food-uploader-plugin") . ':</h4>';
	echo '
<div class="table-responsive">
	<table class="table table-bordered table-hover">
		<thead>
			<tr>
				<th class="manage-column column-primary text-nowrap text-left text-upercase">' . __("name", "food-uploader-plugin") . /*ИМЯ*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase" width="1%">' . __("file-permissions", "food-uploader-plugin") . /*ПРАВА*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase" width="1%">' . __("time-of-change", "food-uploader-plugin") . /*ИЗМЕНЁН*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase" width="1%">' . __("file-size", "food-uploader-plugin") . /*РАЗМЕР ФАЙЛА*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase" width="1%">' . __("actions", "food-uploader-plugin") . /*ДЕЙСТВИЯ*/ '</th>
			</tr>
		</thead>
		<tbody>
';
	if (file_exists($upload_dir)):
		$files = scandir($upload_dir);
		$files = array_diff($files, array('.', '..'));
		// Сортировка файлов
		rsort($files);
		if (!empty($files)):
			foreach ($files as $file):
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				if(is_file($upload_dir . $file) && in_array($ext, $mask_extensions)):
					$ltime = strtotime(wp_timezone_string(), filemtime($upload_dir . $file));
					$size = filesize($upload_dir . $file);
					$perms = substr(sprintf('%o', fileperms($upload_dir . $file)), -4);
					$url = site_url('food/' . $file);
					echo '<tr>
				<td class="column text-pre text-left">' . esc_html($file) . '</td>
				<td class="column text-pre text-right">' . $perms . '</td>
				<td class="column text-pre text-right">' . food_plugin_date_format($ltime) . '</td>
				<td class="column text-pre text-right">' . food_plugin_nicesize($size) . '</td>
				<td class="column text-nowrap text-right">
					<span class="food-link-wrap">
						<a class="food-link food-view" data-fansybox data-src="' . $url . '" href="' . $url . '" target="_blank" title="' . __("view-file", "food-uploader-plugin") /*Просмотр файла*/ . '">
							<i class="glyphicon glyphicon-eye-open"></i>
						</a>
						<a class="food-link food-rename" href="' . $url . '" target="_blank" title="' . __("rename-file", "food-uploader-plugin") /*Переименовать файл*/ . '" data-mode="rename">
							<i class="glyphicon glyphicon-pencil"></i>
						</a>
						<a class="food-link food-delete" href="' . $url . '" target="_blank" title="' . __("delete-file", "food-uploader-plugin") /*Удалить файл*/ . '" data-mode="delete">
							<i class="glyphicon glyphicon-trash"></i>
						</a>
					</span>
				</td>
			</tr>';
				endif;
			endforeach;
		else:
			echo '<tr><td colspan="5">' . __("files-not-uploaded", "food-uploader-plugin") /*$_lang["files_not_found"]*/ . '</td></tr>';
		endif;
	else:
		echo '<tr><td colspan="5">' . __("food-folder-does-no-exist", "food-uploader-plugin") /*$_lang["files_food_not_found"]*/ . '</td></tr>';
	endif;
			echo '
		</tbody>
	</table>
</div>';

}

// Переименование файла
function food_rename_file($new_file="", $file=""){
	global $mask_extensions;
	$startpath = food_path_join(FOOD_ABSPATH, "food") . '/';
	$msg = '';
	// Если имена одинаковые - ничего не делаем. Выходим
	if($file == $new_file):
		echo '<div class="notice bg-danger"><p><strong>' . __("file-exists", "food-uploader-plugin") /*Файл существует*/ . '</strong><br>' . $file . '</p></div>';
		return;
	endif;
	// Исходный файл
	$old_pathinfo = pathinfo($file);
	$old_pathinfo['extension'] = trim($old_pathinfo['extension']);
	// Переименование только pdf или xlsx
	if(!in_array($old_pathinfo['extension'], $mask_extensions)):
		echo '<div class="notice bg-danger"><p><strong>' . __("disable-file-renaming", "food-uploader-plugin") /*Запрет на переименование файла*/ . '</strong><br>' . $file . '</p></div>';
		return;
	endif;
	// Транслит имени файла
	$pthinfo = pathinfo($new_file);
	$f_name = $pthinfo['filename'];

	$f_name = translit_file($f_name);
	// На всякий случай
	// Удаляет специальные символы
	$f_name = preg_replace('/[^A-Za-z0-9\-\_.]/', '', $f_name);
	// Заменяет несколько тире на одно
	$f_name = preg_replace('/-+/', '-', $f_name);
	// Заменяет несколько нижних тире на одно
	$f_name = preg_replace('/_+/', '_', $f_name);
	// Запрещаем переименовывать расширение.
	// Объединяем новое имя с расширением исходного файла
	$new_file = $f_name . "." . $old_pathinfo['extension'];
	// Если имена одинаковые - выходим c ошибкой
	if($file == $new_file):
		echo '<div class="notice bg-danger"><p><strong>' . $new_file . '</strong><br>Файл существует.</p></div>';
		return $all;
	endif;
	$oFile = path_join($startpath, $file);
	$nFile = path_join($startpath, $new_file);
	// Существование исходного файла
	if(is_file($oFile)):
		// Продолжаем
		if(!is_file($nFile)):
			// Продолжаем
			// Переименовываем
			if(@rename($oFile, $nFile)):
				// Удачно
				echo '<div class="notice bg-success"><p><strong>' . __("file-renamed", "food-uploader-plugin") /*Файл переименован*/ . '</strong><br>' . "$file => $new_file" . '</p></div>';
			else:
				// Не удачно
				echo '<div class="notice bg-danger"><p><strong>' . __("failed-rename-file", "food-uploader-plugin") /*Не удалось переименовать файл*/ . '</strong><br>' . "$file => $new_file" . '</p></div>';
			endif;
		else:
			// Уже есть данный файл
			echo '<div class="notice bg-danger"><p><strong>' . __("file-exists", "food-uploader-plugin") /*Файл существует*/ . '</strong><br>' . $new_file . '</p></div>';
		endif;
	else:
		// Не существует
		echo '<div class="notice bg-danger"><p><strong>' . __("file-not-exist", "food-uploader-plugin") /*Файл не существует*/ . '</strong><br>' . $file . '</p></div>';
	endif;
	return;
}

// Удаление файла
function food_delete_file($file) {
	global $mask_extensions;
	$startpath = food_path_join(FOOD_ABSPATH, "food") . '/';
	$old_file = path_join($startpath, $file);
	if(is_file($old_file)):
		if(@unlink($old_file)):
			echo '<div class="notice bg-success"><p><strong>' . __("file-deleted", "food-uploader-plugin") /*Файл удалён*/ . '</strong><br>' . $file . '</p></div>';
		else:
			echo '<div class="notice bg-danger"><p><strong>' . __("file-not-deleted", "food-uploader-plugin") /*Файл не удалён*/ . '</strong><br>' . $file . '</p></div>';
		endif;
	else:
		echo '<div class="notice bg-danger"><p><strong>' . __("file-not-exist", "food-uploader-plugin") /*Файл не существует*/ . '</strong><br>' . $file . '</p></div>';
	endif;
	return;
}

// Добавление стилей
function food_plugin_add_admin_style_script() {
	global $plugin_name;
	wp_register_style( 'food-uploader-plugin', plugins_url( $plugin_name . '/css/main.css' ), array(), '1.0.0-dev-1738874436', false );
	wp_register_script( 'food-uploader-plugin_app', plugins_url( $plugin_name . '/js/appjs.min.js' ), array(), '1.0.0', true );
	wp_enqueue_style( 'food-uploader-plugin' );
	wp_enqueue_script( 'food-uploader-plugin_app');

	// Подключение моего вьювера если он установлен
	if(is_file(FOOD_ABSPATH . '/viewer/fancybox.min.js')):
		wp_register_script( 'food-uploader-plugin_fancybox_js', site_url('viewer/fancybox.min.js'), array(), '1.0.0-dev-1738874436', true );
		wp_register_style( 'food-uploader-plugin_fancybox_css', site_url('viewer/app.min.css'), array(), '1.0.0-dev-1738874436', false );
		wp_enqueue_style( 'food-uploader-plugin_fancybox_css' );
		wp_enqueue_script( 'food-uploader-plugin_fancybox_js');
	endif;

	wp_register_script( 'food-uploader-plugin_main', plugins_url( $plugin_name . '/js/main.min.js' ), array(), '1.0.0-dev-1738874436', true );
	wp_enqueue_script( 'food-uploader-plugin_main');
}

// Размер файла
function food_plugin_nicesize($size)
{
	$TB = __("TB", "food-uploader-plugin");
	$GB = __("GB", "food-uploader-plugin");
	$MB = __("MB", "food-uploader-plugin");
	$KB = __("KB", "food-uploader-plugin");
	$bt = __("byte", "food-uploader-plugin");

	$sizes = array($TB => 1099511627776, $GB => 1073741824, $MB => 1048576, $KB => 1024, $bt => 1);

	$precisions = count($sizes) - 1;
	foreach ($sizes as $unit => $bytes) {
		if ($size >= $bytes) {
			return number_format($size / $bytes, $precisions) . ' ' . $unit;
		}
		$precisions--;
	}
	return '0 b';
}

// Формат времени
function food_plugin_date_format(int $timestamp = 0)
{
	$timestamp = trim($timestamp);
	$timestamp = (int)$timestamp;
	$gmt = get_option('gmt_offset');
	$offset = $timestamp + $gmt * 7200;
	$strTime = date_i18n( "d-m-Y H:i:s", $offset, false );
	return $strTime;
}

// Объединени пути
function food_path_join (...$strings) {
	$result = [];
	foreach ($strings as $n):
		$result[] = rtrim( $n, '/' );
	endforeach;
	return implode('/', $strings);
}

// 
function nl2p (string $string="") {
	$str_arr = explode("\n", $string);
	if(!count($str_arr)):
		return "";
	endif;
	$result = "<p>" . implode("</p><p>", $str_arr) . "</p>";
	return $result;
}

// Транслит имени файлаfunction translit_file($filename)
function translit_file($filename) {
	$ret = array(
			// russian
			'А'  => 'A',
			'а'  => 'a',
			'Б'  => 'B',
			'б'  => 'b',
			'В'  => 'V',
			'в'  => 'v',
			'Г'  => 'G',
			'г'  => 'g',
			'Д'  => 'D',
			'д'  => 'd',
			'Е'  => 'E',
			'е'  => 'e',
			'Ё'  => 'Jo',
			'ё'  => 'jo',
			'Ж'  => 'Zh',
			'ж'  => 'zh',
			'З'  => 'Z',
			'з'  => 'z',
			'И'  => 'I',
			'и'  => 'i',
			'Й'  => 'J',
			'й'  => 'j',
			'К'  => 'K',
			'к'  => 'k',
			'Л'  => 'L',
			'л'  => 'l',
			'М'  => 'M',
			'м'  => 'm',
			'Н'  => 'N',
			'н'  => 'n',
			'О'  => 'O',
			'о'  => 'o',
			'П'  => 'P',
			'п'  => 'p',
			'Р'  => 'R',
			'р'  => 'r',
			'С'  => 'S',
			'с'  => 's',
			'Т'  => 'T',
			'т'  => 't',
			'У'  => 'U',
			'у'  => 'u',
			'Ф'  => 'F',
			'ф'  => 'f',
			'Х'  => 'H',
			'х'  => 'h',
			'Ц'  => 'C',
			'ц'  => 'c',
			'Ч'  => 'Ch',
			'ч'  => 'ch',
			'Ш'  => 'Sh',
			'ш'  => 'sh',
			'Щ'  => 'Shh',
			'щ'  => 'shh',
			'Ъ'  => '',
			'ъ'  => '',
			'Ы'  => 'Y',
			'ы'  => 'y',
			'Ь'  => '',
			'ь'  => '',
			'Э'  => 'Je',
			'э'  => 'je',
			'Ю'  => 'Ju',
			'ю'  => 'ju',
			'Я'  => 'Ja',
			'я'  => 'ja',
			// global
			'Ґ'  => 'G',
			'ґ'  => 'g',
			'Є'  => 'Ie',
			'є'  => 'ie',
			'І'  => 'I',
			'і'  => 'i',
			'Ї'  => 'I',
			'ї'  => 'i',
			'Ї' => 'i',
			'ї' => 'i',
			'Ё' => 'Jo',
			'ё' => 'jo',
			'й' => 'i',
			'Й' => 'I'
		);

	$new = '';
	$filename = urldecode( $filename );
	$file = pathinfo(trim($filename));
	if (!empty($file['filename'])) {
		// Нижний регистр
		$alias = strtolower($file['filename']);
		// Очищаем от html
		$alias = strip_tags($alias);
		// Транслит
		$alias = strtr($alias, $ret);
		// Удаляем все неразрешённые символы
		$alias = preg_replace('/[^\.A-Za-z0-9 _-]/', '', $alias);
		// Удаляем все пробельные символы. Заменяем их на один дефис
		$alias = preg_replace('/\s+/', '-', $alias);
		// Удаляем все дефисы. Заменяем на один дефис
		$alias = preg_replace('/-+/', '-', $alias);
		// Удаляем все нижние подчёркивания. Заменяем на один знак подчёркивания
		$alias = preg_replace('/_+/', '_', $alias);
		// Удаляем сначала и сконца дефисы
		$alias = trim($alias, '-');
		// Удаляем сначала и сконца нижние подчёркивания
		$alias = trim($alias, '_');
		$new .= $alias;
	}
	if (!empty($file['extension'])) {
		$new .= '.' . trim($file['extension']);
	}
	return $new;
}

