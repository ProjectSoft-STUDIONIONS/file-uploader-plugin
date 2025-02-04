<?php
/*
Plugin Name: Food File Uploader
Description: WordPress Плагин для загрузки файлов (PDF и XLSX) в папку /food/, доступен только администраторам.
Version: 1.0
Author: Чернышёв Андрей aka ProjectSoft <projectsoft2009@yandex.ru>
*/

if (!defined('ABSPATH')) die();

if(!defined('FOOD_LINK_PAGE')):
	define('FOOD_LINK_PAGE', 'food_uploader');
endif;

// Нормальный вид ABSPATH
$abs_path = rtrim(preg_replace('/\\\\/', '/', ABSPATH), '/');
define('FOOD_ABSPATH', $abs_path);


global $mask_extensions, $allowed_types, $mask_folder, $all;

$mask_extensions = array("xlsx");
$allowed_types = array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

$mask_folder = array("food");

$local = get_user_locale();

load_plugin_textdomain( 'file-uploader-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

// Добавление стилей, добавление скриптов
add_action('admin_enqueue_scripts', 'food_plugin_add_admin_style_script');

// Создание страницы в админ-панели
add_action('admin_menu', 'food_plugin_add_admin_menu', 30);

function food_admin_page_url($query = null, array $esc_options = []) {
	$url = menu_page_url('food_uploader', false);
	if($query) {
		$url .= '&' . (is_array($query) ? http_build_query($query) : (string) $query);
	}
	return esc_url($url, ...$esc_options);
}

function food_plugin_add_admin_menu() {
	$title = __("Daily Meal Menu", "file-uploader-plugin");
	add_menu_page(
		$title,
		$title,
		'manage_options',
		'food_uploader',
		'food_plugin_file_uploader_page',
		'dashicons-open-folder',
		26
	);
}

// Функция отображения страницы загрузки файлов
function food_plugin_file_uploader_page() {
	if (!current_user_can('manage_options')) {
		wp_die(__("You do not have access to this page.", "file-uploader-plugin"));
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
		/*
		mode
		file
		new_file
		*/
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
	echo '<h1>' . __("Daily Meal Menu", "file-uploader-plugin") . '</h1>';
	// Форма загрузки файла
	echo '<form method="post" name="upload_food" enctype="multipart/form-data" action="' . food_admin_page_url() . '">
			<div id="uploader" class="text-right">
				<div class="text-center"><!-- Блок загрузки файлов -->' . __("File upload block", "file-uploader-plugin") . '</div>
				<label class="btn btn-default text-uppercase text-nowrap">
					<!-- Выберите файлы --> ' . __("Select files", "file-uploader-plugin") . '
					<input type="file" name="food_plugin_file[]" accept=".xlsx" required multiple data-max="' . $max_files . '">
				</label>
				<p id="p_uploads" class="alert alert-info"></p>
				<p>
					<input type="submit" name="submit" class="btn btn-success text-uppercase text-nowrap" value="' . __("SEND", "file-uploader-plugin") . '">
				</p>
			</div>
		</form>';
	// Форма действий
	echo '<form method="post" name="modifed" enctype="multipart/form-data" action="' . food_admin_page_url() . '">
	<input type="hidden" name="mode" value="">
	<input type="hidden" name="file" value="">
	<input type="hidden" name="new_file">
	<input type="submit" tabindex="-1" name="submited" value="' . __("SEND", "file-uploader-plugin") . '">
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
			echo '<div class="notice bg-danger"><p><strong>' . __("Error: Unable to create directory", "file-uploader-plugin") . '</strong> <pre>food</pre>.</p></div>';
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
			$all["error"] .= '<dt>' . __("Error: Only XLSX files are allowed.", "file-uploader-plugin") . '</dt><dd>' . $files['name'][$i] . '</dd>';
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
					$all["success"] .= '<dt>' . __("File uploaded", "file-uploader-plugin") /*$_lang["file_success"]*/ . '</dt><dd>' . $name . '</dd>';
				else:
					// Не удалось переместить файл
					$all["error"] .= '<dt>' . __("Failed to move file", "file-uploader-plugin") /*$_lang["error_movied_file"]*/ . '</dt><dd>' . $files['name'][$i] . '</dd>';
				endif;
			else:
				// Не удалось загрузить файл
				$all["error"] .= '<dt>' . __("Failed to upload file", "file-uploader-plugin") /*$_lang["error_upload_file"]*/ . '</dt><dd>' . $files['name'][$i] . '</dd>';
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
			echo '<div class="notice bg-danger"><p><strong>' . __("Error: Unable to create directory", "file-uploader-plugin")/*$_lang["error_createdir"]*/ . '</strong> <pre>food</pre>.</p></div>';
			return;
		endif;
	endif;
	echo '<h4>Загруженные файлы:</h4>';
	echo '
<div class="table-responsive">
	<table class="table table-bordered table-hover">
		<thead>
			<tr>
				<th class="manage-column column-primary text-nowrap text-left text-upercase">' . __("Name", "file-uploader-plugin") . /*ИМЯ*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase">' . __("File permissions", "file-uploader-plugin") . /*ПРАВА*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase">' . __("Time of change", "file-uploader-plugin") . /*ИЗМЕНЁН*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase">' . __("File size", "file-uploader-plugin") . /*РАЗМЕР ФАЙЛА*/ '</th>
				<th class="manage-column text-nowrap text-right text-upercase">' . __("Actions", "file-uploader-plugin") . /*ДЕЙСТВИЯ*/ '</th>
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
					<a class="food-link food-view" data-fansybox data-src="' . $url . '" href="' . $url . '" target="_blank" title="' . __("View file", "file-uploader-plugin") /*Просмотр файла*/ . '">
						<i class="glyphicon glyphicon-eye-open"></i>
					</a>
					<a class="food-link food-rename" href="' . $url . '" target="_blank" title="' . __("Rename file", "file-uploader-plugin") /*Переименовать файл*/ . '" data-mode="rename">
						<i class="glyphicon glyphicon-pencil"></i>
					</a>
					<a class="food-link food-delete" href="' . $url . '" target="_blank" title="' . __("Delete file", "file-uploader-plugin") /*Удалить файл*/ . '" data-mode="delete">
						<i class="glyphicon glyphicon-trash"></i>
					</a>
				</td>
			</tr>';
				endif;
			endforeach;
		else:
			echo '<tr><td colspan="5">' . __("Files not uploaded.", "file-uploader-plugin") /*$_lang["files_not_found"]*/ . '</td></tr>';
		endif;
	else:
		echo '<tr><td colspan="5">' . __("The food folder for download does not exist.", "file-uploader-plugin") /*$_lang["files_food_not_found"]*/ . '</td></tr>';
	endif;
			echo '
		</tbody>
	</table>
</div>';

}

// Добавление стилей
function food_plugin_add_admin_style_script() {
	wp_register_style( 'file-uploader-plugin', plugins_url( 'file-uploader-plugin/css/main.css' ), array(), '1.0.0-dev-'.time(), false );
	wp_register_script( 'file-uploader-plugin_app', plugin_dir_url( __FILE__ ) . 'js/appjs.min.js', array(), '1.0.0', true );
	wp_enqueue_style( 'file-uploader-plugin' );
	wp_enqueue_script( 'file-uploader-plugin_app');

	// Подключение моего вьювера если он установлен
	if(is_file(FOOD_ABSPATH . '/viewer/fancybox.min.js')):
		wp_register_script( 'file-uploader-plugin_fancybox_js', site_url('viewer/fancybox.min.js'), array(), '1.0.0-dev-'.time(), true );
		wp_register_style( 'file-uploader-plugin_fancybox_css', site_url('viewer/app.min.css'), array(), '1.0.0-dev-'.time(), false );
		wp_enqueue_style( 'file-uploader-plugin_fancybox_css' );
		wp_enqueue_script( 'file-uploader-plugin_fancybox_js');
	endif;

	wp_register_script( 'file-uploader-plugin_main', plugin_dir_url( __FILE__ ) . 'js/main.min.js', array(), '1.0.0-dev-'.time(), true );
	wp_enqueue_script( 'file-uploader-plugin_main');
}

// Переименование файла
function food_rename_file($new_file="", $file=""){
	global $mask_extensions;
	$startpath = food_path_join(FOOD_ABSPATH, "food") . '/';
	$msg = '';
	// Если имена одинаковые - ничего не делаем. Выходим
	if($file == $new_file):
		echo '<div class="notice bg-danger"><p><strong>' . __("The file exists", "file-uploader-plugin") /*Файл существует*/ . '</strong><br>' . $file . '</p></div>';
		return;
	endif;
	// Исходный файл
	$old_pathinfo = pathinfo($file);
	$old_pathinfo['extension'] = trim($old_pathinfo['extension']);
	// Переименование только pdf или xlsx
	if(!in_array($old_pathinfo['extension'], $mask_extensions)):
		echo '<div class="notice bg-danger"><p><strong>' . __("Disable file renaming", "file-uploader-plugin") /*Запрет на переименование файла*/ . '</strong><br>' . $file . '</p></div>';
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
				echo '<div class="notice bg-success"><p><strong>' . __("File renamed", "file-uploader-plugin") /*Файл переименован*/ . '</strong><br>' . "$file => $new_file" . '</p></div>';
			else:
				// Не удачно
				echo '<div class="notice bg-danger"><p><strong>' . __("Failed to rename file", "file-uploader-plugin") /*Не удалось переименовать файл*/ . '</strong><br>' . "$file => $new_file" . '</p></div>';
			endif;
		else:
			// Уже есть данный файл
			echo '<div class="notice bg-danger"><p><strong>' . __("The file exists", "file-uploader-plugin") /*Файл существует*/ . '</strong><br>' . $new_file . '</p></div>';
		endif;
	else:
		// Не существует
		echo '<div class="notice bg-danger"><p><strong>' . __("The file does not exist", "file-uploader-plugin") /*Файл не существует*/ . '</strong><br>' . $file . '</p></div>';
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
			echo '<div class="notice bg-success"><p><strong>' . __("File deleted", "file-uploader-plugin") /*Файл удалён*/ . '</strong><br>' . $file . '</p></div>';
		else:
			echo '<div class="notice bg-danger"><p><strong>' . __("The file was not deleted", "file-uploader-plugin") /*Файл не удалён*/ . '</strong><br>' . $file . '</p></div>';
		endif;
	else:
		echo '<div class="notice bg-danger"><p><strong>' . __("The file does not exist", "file-uploader-plugin") /*Файл не существует*/ . '</strong><br>' . $file . '</p></div>';
	endif;
	return;
}

// Размер файла
function food_plugin_nicesize($size)
{
	$TB = __("TB", "file-uploader-plugin");
	$GB = __("GB", "file-uploader-plugin");
	$MB = __("MB", "file-uploader-plugin");
	$KB = __("KB", "file-uploader-plugin");
	$bt = __("byte", "file-uploader-plugin");

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

// Объединение строк
function string_join(...$string) {
	$result = "<p>" . implode("</p><p>", $string) . "</p>";
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

