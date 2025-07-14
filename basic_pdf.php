<?php

/*
 * Plugin Name: basic_pdf
 * Plugin URI: https://localhost/plugins
 * Description: useful pdf generator
 * Author: nullstep
 * Author URI: https://localhost
 * Version: 1.0.1
 */

defined('ABSPATH') or die('⎺\_(ツ)_/⎺');

// defines      

define('_PLUGIN_BASIC_PDF', 'basic_pdf');
define('_PLUGIN_BASIC_PDF_ICON', '');

define('_URL_BASIC_PDF', plugin_dir_url(__FILE__));
define('_PATH_BASIC_PDF', plugin_dir_path(__FILE__));

//   ▄████████   ▄██████▄   ███▄▄▄▄▄       ▄████████  
//  ███    ███  ███    ███  ███▀▀▀▀██▄    ███    ███  
//  ███    █▀   ███    ███  ███    ███    ███    █▀   
//  ███         ███    ███  ███    ███   ▄███▄▄▄      
//  ███         ███    ███  ███    ███  ▀▀███▀▀▀      
//  ███    █▄   ███    ███  ███    ███    ███         
//  ███    ███  ███    ███  ███    ███    ███         
//  ████████▀    ▀██████▀    ▀█    █▀     ███

// basic_pdf args

define('_ARGS_BASIC_PDF', [
	'active' => [
		'type' => 'string',
		'default' => 'no'
	],
	'overwrite' => [
		'type' => 'string',
		'default' => 'no'
	]
]);

// basic_pdf admin

define('_ADMIN_BASIC_PDF', [
	'options' => [
		'label' => 'Options',
		'columns' => 4,
		'fields' => [
			'active' => [
				'label' => 'Plugin Active',
				'type' => 'check'
			],
			'overwrite' => [
				'label' => 'Overwrite File',
				'type' => 'check'
			]
		]
	]
]);

// basic_pdf api routes

define('_APIPATH_BASIC_PDF',
	'settings'
);

define('_API_BASIC_PDF', [
	[
		'methods' => 'POST',
		'callback' => 'update_settings',
		'args' => _bpdfSettings::args(),
		'permission_callback' => 'permissions'
	],
	[
		'methods' => 'GET',
		'callback' => 'get_settings',
		'args' => [],
		'permission_callback' => 'permissions'
	]
]);

//     ▄████████     ▄███████▄   ▄█   
//    ███    ███    ███    ███  ███   
//    ███    ███    ███    ███  ███▌  
//    ███    ███    ███    ███  ███▌  
//  ▀███████████  ▀█████████▀   ███▌  
//    ███    ███    ███         ███   
//    ███    ███    ███         ███   
//    ███    █▀    ▄████▀       █▀ 

class _bpdfAPI {
	public function add_routes() {
		if (count(_API_BASIC_PDF)) {

			foreach(_API_BASIC_PDF as $route) {
				register_rest_route(_PLUGIN_BASIC_PDF . '-api', '/' . _APIPATH_BASIC_PDF, [
					'methods' => $route['methods'],
					'callback' => [$this, $route['callback']],
					'args' => $route['args'],
					'permission_callback' => [$this, $route['permission_callback']]
				]);
			}
		}
	}

	public function permissions() {
		return current_user_can('manage_options');
	}

	public function update_settings(WP_REST_Request $request) {
		$settings = [];
		foreach (_bpdfSettings::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}
		_bpdfSettings::save_settings($settings);
		return rest_ensure_response(_bpdfSettings::get_settings());
	}

	public function get_settings(WP_REST_Request $request) {
		return rest_ensure_response(_bpdfSettings::get_settings());
	}
}

//     ▄████████     ▄████████      ███          ███       ▄█   ███▄▄▄▄▄       ▄██████▄      ▄████████  
//    ███    ███    ███    ███  ▀█████████▄  ▀█████████▄  ███   ███▀▀▀▀██▄    ███    ███    ███    ███  
//    ███    █▀     ███    █▀      ▀███▀▀██     ▀███▀▀██  ███▌  ███    ███    ███    █▀     ███    █▀   
//    ███          ▄███▄▄▄          ███   ▀      ███   ▀  ███▌  ███    ███   ▄███           ███         
//  ▀███████████  ▀▀███▀▀▀          ███          ███      ███▌  ███    ███  ▀▀███ ████▄   ▀███████████  
//           ███    ███    █▄       ███          ███      ███   ███    ███    ███    ███           ███  
//     ▄█    ███    ███    ███      ███          ███      ███   ███    ███    ███    ███     ▄█    ███  
//   ▄████████▀     ██████████     ▄████▀       ▄████▀    █▀     ▀█    █▀     ████████▀    ▄████████▀ 

class _bpdfSettings {
	protected static $option_key = _PLUGIN_BASIC_PDF . '-settings';

	public static function args() {
		$args = _ARGS_BASIC_PDF;
		foreach (_ARGS_BASIC_PDF as $key => $val) {
			$val['required'] = true;
			switch ($val['type']) {
				case 'integer': {
					$cb = 'absint';
					break;
				}
				default: {
					$cb = 'sanitize_text_field';
				}
				$val['sanitize_callback'] = $cb;
			}
		}
		return $args;
	}

	public static function get_settings() {
		$defaults = [];
		foreach (_ARGS_BASIC_PDF as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		$saved = get_option(self::$option_key, []);
		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public static function save_settings(array $settings) {
		$defaults = [];
		foreach (_ARGS_BASIC_PDF as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		foreach ($settings as $i => $setting) {
			if (!array_key_exists($i, $defaults)) {
				unset($settings[$i]);
			}
		}
		update_option(self::$option_key, $settings);
	}
}

//    ▄▄▄▄███▄▄▄▄       ▄████████  ███▄▄▄▄▄    ███    █▄   
//  ▄██▀▀▀███▀▀▀██▄    ███    ███  ███▀▀▀▀██▄  ███    ███  
//  ███   ███   ███    ███    █▀   ███    ███  ███    ███  
//  ███   ███   ███   ▄███▄▄▄      ███    ███  ███    ███  
//  ███   ███   ███  ▀▀███▀▀▀      ███    ███  ███    ███  
//  ███   ███   ███    ███    █▄   ███    ███  ███    ███  
//  ███   ███   ███    ███    ███  ███    ███  ███    ███  
//   ▀█   ███   █▀     ██████████   ▀█    █▀   ████████▀ 

class _bpdfMenu {
	protected $slug = _PLUGIN_BASIC_PDF . '-menu';
	protected $assets_url;

	public function __construct($assets_url) {
		$this->assets_url = $assets_url;
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'register_assets']);
	}

	public function add_page() {
		add_menu_page(
			_PLUGIN_BASIC_PDF,
			_PLUGIN_BASIC_PDF,
			'manage_options',
			$this->slug,
			[$this, 'render_admin'],
			'data:image/svg+xml;base64,' . base64_encode(_PLUGIN_BASIC_PDF_ICON),
			30
		);

		// add config submenu

		add_submenu_page(
			$this->slug,
			'Configuration',
			'Configuration',
			'manage_options',
			$this->slug
		);
	}

	public function register_assets() {
		$boo = microtime(false);
		wp_register_script($this->slug, $this->assets_url . '/' . _PLUGIN_BASIC_PDF . '.js?' . $boo, ['jquery']);
		wp_register_style($this->slug, $this->assets_url . '/' . _PLUGIN_BASIC_PDF . '.css?' . $boo);
		wp_localize_script($this->slug, _PLUGIN_BASIC_PDF, [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(_PLUGIN_BASIC_PDF . '-api/settings')),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public function enqueue_assets() {
		if (!wp_script_is($this->slug, 'registered')) {
			$this->register_assets();
		}

		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function render_admin() {
		wp_enqueue_media();
		$this->enqueue_assets();

		$name = _PLUGIN_BASIC_PDF;
		$form = _ADMIN_BASIC_PDF;

		// build form

		echo '<div id="' . $name . '-wrap" class="wrap">';
			echo '<h1>' . $name . '</h1>';
			echo '<p>Configure your ' . $name . ' settings...</p>';
			echo '<form id="' . $name . '-form" method="post">';
				echo '<nav id="' . $name . '-nav" class="nav-tab-wrapper">';

				foreach ($form as $tid => $tab) {
					echo '<a href="#' . $name . '-' . $tid . '" class="nav-tab">' . $tab['label'] . '</a>';
				}
				echo '</nav>';
				echo '<div class="tab-content">';

				foreach ($form as $tid => $tab) {
					echo '<div id="' . $name . '-' . $tid . '" class="' . $name . '-tab">';

					foreach ($tab['fields'] as $fid => $field) {
						echo '<div class="form-block col-' . $tab['columns'] . '">';
						
						switch ($field['type']) {
							case 'input': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								break;
							}
							case 'select': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<select id="' . $fid . '" name="' . $fid . '">';
									foreach ($field['values'] as $value => $label) {
										echo '<option value="' . $value . '">' . $label . '</option>';
									}
								echo '</select>';
								break;
							}
							case 'text': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="tabs" name="' . $fid . '"></textarea>';
								break;
							}
							case 'file': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="button" class="button-primary choose-file-button" value="...">';
								break;
							}
							case 'colour': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="color" class="choose-colour-button" value="#000000">';
								break;
							}
							case 'code': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="code" name="' . $fid . '"></textarea>';
								break;
							}
							case 'check': {
								echo '<em>' . $field['label'] . ':</em>';
								echo '<label class="switch">';
									echo '<input type="checkbox" id="' . $fid . '" name="' . $fid . '" value="yes">';
									echo '<span class="slider"></span>';
								echo '</label>';
								break;
							}
						}
						echo '</div>';
					}
					echo '</div>';
				}
				echo '</div>';
				echo '<div>';
					submit_button();
				echo '</div>';
				echo '<div id="' . $name . '-feedback"></div>';
			echo '</form>';
		echo '</div>';
	}
}

// menu stuff

function bpdf_set_current_menu($parent_file) {
	global $submenu_file, $current_screen, $pagenow;

	if (in_array($current_screen->id, ['edit-svg', 'svg'])) {
		if ($pagenow == 'post.php') {
			$submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
		}
		$parent_file = _PLUGIN_BASIC_PDF . '-menu';
	}
	return $parent_file;
}


//     ▄███████▄  ████████▄      ▄████████  
//    ███    ███  ███   ▀███    ███    ███  
//    ███    ███  ███    ███    ███    █▀   
//    ███    ███  ███    ███   ▄███▄▄▄      
//  ▀█████████▀   ███    ███  ▀▀███▀▀▀      
//    ███         ███    ███    ███         
//    ███         ███   ▄███    ███         
//   ▄████▀       ████████▀     ███

function bpdf_load_libs($fpdi = false) {
	require_once(__DIR__ . '/lib/tcpdf.php');

	if ($fpdi) {
		require_once(__DIR__ . '/lib/fpdi.php');
	}
}

function bpdf_create_font_defs($font_file) {
	if (!$font_file) {
		return;
	}

	$dir = wp_upload_dir()['basedir'] . '/';

	bpdf_make_folder($dir);
	bpdf_load_libs();

	$font = $dir . $font_file;

	if (file_exists($font)) {
		$name = strtolower(pathinfo($font, PATHINFO_FILENAME));

		$compressed = $dir . 'fonts/' . $name . '.z';
		$definitions = $dir . 'fonts/' . $name . '.php';

		if ((!file_exists($compressed)) || (!file_exists($definitions))) {
			$fm = new TCPDF_FONTS();
			$fm->addTTFfont($font, 'TrueTypeUnicode', '', 32, $dir . 'fonts/');
			update_post_meta($post_id, '_bt_font_defs', $name);
		}
	}
}

function bpdf_text_fits_in_lines($pdf, $text, $width, $lines) {
	$text_lines = $pdf->splitLines($text, $width);
	return count($text_lines) <= $lines;
}

function bpdf_split_lines($pdf, $text, $width) {
	$words = explode(' ', $text);
	$lines = [];
	$currentLine = '';

	foreach ($words as $word) {
		$testLine = ($currentLine === '') ? $word : $currentLine . ' ' . $word;

		if ($pdf->GetStringWidth($testLine) <= $width) {
			$currentLine = $testLine;
		}
		else {
			if ($currentLine !== '') {
				$lines[] = $currentLine;
			}
			$currentLine = $word;
		}
	}

	if ($currentLine !== '') {
		$lines[] = $currentLine;
	}

	return $lines;
}

function bpdf_get_rgb($value) {
	$parts = explode(',', $value);

	if (count($parts) == 4) {
		$c = (intval(trim($parts[0])) / 100);
		$m = (intval(trim($parts[1])) / 100);
		$y = (intval(trim($parts[2])) / 100);
		$k = (intval(trim($parts[3])) / 100);

		$r = 255 * (1 - $c) * (1 - $k);
		$g = 255 * (1 - $m) * (1 - $k);
		$b = 255 * (1 - $y) * (1 - $k);

		return [
			'r' => round($r),
			'g' => round($g),
			'b' => round($b)
		];
	}

	if (count($parts) == 3) {
		return [
			'r' => intval(trim($parts[0])),
			'g' => intval(trim($parts[1])),
			'b' => intval(trim($parts[2]))
		];
	}

	return false;
}

function bpdf_generate_pdf($width, $height, $config, $filename) {
	$doc = (is_array($config)) ? $config : json_decode($config, true);

	$result = false;

	if ($doc && count($doc)) {
		$upload_dir = wp_upload_dir();
		$dir = $upload_dir['basedir'] . '/';

		bpdf_make_folder($dir);
		bpdf_load_libs();

		$pdf = new TCPDF('L', 'mm', [$width, $height]);
		$pdf->SetMargins(0, 0, 0, 0);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetCellHeightRatio(1);

		$pdf->AddPage();

		foreach ($doc as $asset => $data_object) {
			list($type, $num) = explode('_', $asset);

			$data = (array)$data_object;

			switch ($type) {
				case 'ga': {
					if ($data['asset_graphic']) {
						$pdf->ImageSVG($data['asset_graphic'], $data['asset_left'], ($data['asset_bottom'] - $data['asset_height']), 0, $data['asset_height']);
					}

					break;
				}
				case 'gpa': {
					bpdf_pdf_graphic('asset_graphic', $data, $pdf);

					break;
				}
				case 'gca': {
					bpdf_pdf_graphic('asset_chosen', $data, $pdf);

					break;
				}
				case 'ta': {
					$font = pathinfo($data['text_font'], PATHINFO_FILENAME);

					$pdf->AddFont($font, '', $font . '.php', $dir);
					$pdf->SetFont($font, '', ($data['font_height'] / _PTM));

					if (isset($data['font_colour']) && ($data['font_colour'] != '')) {
						$colours = m_get_rgb($data['font_colour']);

						if ($colours && is_array($colours)) {
							$pdf->SetTextColor($colours['r'], $colours['g'], $colours['b']);
						}
					}
					else {
						$pdf->SetTextColor(0, 0, 0);
					}

					$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
					$pdf->Cell(0, $data['font_height'], $data['asset_text'], 0, 0, 'L', 0, '', 0, false, 'B', 'B');

					break;
				}
				case 'tpa': {
					bpdf_pdf_text($data, $pdf);

					break;
				}
				case 'tca': {
					bpdf_pdf_text($data, $pdf);

					break;
				}
			}
		}

		$file = $dir . 'pdfs/' . $filename . '.pdf';

		if (file_exists($file)) {
			if (_BPDF['overwrite']) {
				unlink($file);
			}
			else {
				return false;
			}
		}

		$pdf->Output($file, 'F');
		$result = pathinfo($file, PATHINFO_BASENAME);
	}

	return $result;
}

function bpdf_pdf_graphic($check, &$data, &$pdf) {
	$dir = wp_upload_dir()['basedir'] . '/';

	if (isset($data[$check]) && $data[$check] != '') {
		$image = $dir . $data[$check];
		$ext = strtoupper(pathinfo($image, PATHINFO_EXTENSION));

		if (in_array($ext, ['PNG', 'JPG', 'JPEG'])) {
			$pdf->Image($image, $data['asset_left'], ($data['asset_bottom'] - $data['asset_height']), $data['asset_width'], $data['asset_height'], $ext, '', '', true, 300);
		}
		else {
			$pdf->SetFont('helvetica', '', 10);
			$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
			$pdf->Cell(0, 10, 'unsupported image format', 0, 1, 'C');
		}
	}
	else {
		$image = '@' . '<svg viewBox="0 0 20 20" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" width="20" height="20" fill="none" stroke="black" stroke-width="2" /><line x1="0" y1="0" x2="20" y2="20" stroke="black" stroke-width="1" /><line x1="20" y1="0" x2="0" y2="20" stroke="black" stroke-width="1" /></svg>';
		$pdf->ImageSVG($image, $data['asset_left'], ($data['asset_bottom'] - $data['asset_height']), $data['asset_width'], $data['asset_height']);
	}
}

function bpdf_pdf_text(&$data, &$pdf) {
	$dir = wp_upload_dir()['basedir'] . '/';
	$font = pathinfo($data['text_font'], PATHINFO_FILENAME);
	$size = ($data['font_height'] / _PTM);
	$lines = $data['max_lines'];
	$text = $data['asset_chosen'] ?? ($data['asset_text'] ?? $data['asset_description']);

	$pdf->AddFont($font, '', $font . '.php', $dir);
	$pdf->SetFont($font, '', $size);

	if (isset($data['font_colour']) && ($data['font_colour'] != '')) {
		$colours = m_get_rgb($data['font_colour']);

		if ($colours && is_array($colours)) {
			$pdf->SetTextColor($colours['r'], $colours['g'], $colours['b']);
		}
	}
	else {
		$pdf->SetTextColor(0, 0, 0);
	}

	switch ($data['text_align']) {
		case 'centre': {
			$align = 'C';
			break;
		}
		case 'right': {
			$align = 'R';
			break;
		}
		default: {
			$align = 'L';
			break;
		}
	}

	switch ($data['size_method']) {
		case 'shrink': {
			while ($pdf->GetStringWidth($text) > $data['asset_width'] && $size > ($data['font_min'] / _PTM)) {
				$size--;
				$pdf->SetFont($font, '', $size);
			}

			$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
			$pdf->Cell($data['asset_width'], $data['asset_height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');

			break;
		}
		case 'lines': {
			if ($pdf->GetStringWidth($text) > $data['asset_width']) {
				$text_lines = m_split_lines($pdf, $text, $data['asset_width']);
				$new_lines = [];

				for ($x = 0; $x < $lines; $x++) {
					$new_lines[] = $text_lines[$x];
				}

				$pdf->SetXY($data['asset_left'], ($data['asset_bottom'] - ($data['asset_height'] * $lines)));
				$pdf->MultiCell($data['asset_width'], $size, implode("\n", $new_lines), 0, $align, false, 0, '', '', false, 0, false, false, $data['asset_height'] * $lines, 'B', false);
			}
			else {
				$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
				$pdf->Cell($data['asset_width'], $data['asset_height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			}
			break;
		}
		case 'shrink-lines': {
			while ($pdf->GetStringWidth($text) > $data['asset_width'] && $size > ($data['font_min'] / _PTM)) {
				$size--;
				$pdf->SetFont($font, '', $size);
			}

			if ($pdf->GetStringWidth($text) > $data['asset_width']) {
				$text_lines = m_split_lines($pdf, $text, $data['asset_width']);
				$new_lines = [];

				for ($x = 0; $x < $lines; $x++) {
					$new_lines[] = str_replace("\n", ' ', $text_lines[$x]);
				}

				$pdf->SetXY($data['asset_left'], ($data['asset_bottom'] - ($data['asset_height'] * $lines)));
				$pdf->MultiCell($data['asset_width'], $size, implode("\n", $new_lines), 0, $align, false, 0, '', '', false, 0, false, false, $data['asset_height'] * $lines, 'B', false);
			}
			else {
				$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
				$pdf->Cell($data['asset_width'], $data['asset_height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			}

			break;
		}
		case 'lines-shrink': {
			if ($pdf->GetStringWidth($text) > $data['asset_width']) {
				$text_lines = m_split_lines($pdf, $text, $data['asset_width']);

				while (count($text_lines) > $lines) {
					$size--;
					$pdf->SetFont($font, '', $size);
					$text_lines = m_split_lines($pdf, $text, $data['asset_width']);
				}

				$output = $text;

				$pdf->SetXY($data['asset_left'], ($data['asset_bottom'] - ($data['asset_height'] * $lines)));
				$pdf->MultiCell($data['asset_width'], $size, $output, 0, $align, false, 0, '', '', true, 0, false, false, $size * $lines, 'B', false);
			}
			else {
				$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
				$pdf->Cell($data['asset_width'], $data['asset_height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			}

			break;
		}
		default: {
			$pdf->SetXY($data['asset_left'], $data['asset_bottom']);
			$pdf->Cell($data['asset_width'], $data['asset_height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			break;
		}
	}
}

function bpdf_make_folder($dir) {
	$pdfs = $dir . 'pdfs';

	if (!is_dir($pdfs)) {
		mkdir($pdfs, 0755);
	}

	$fonts = $dir . 'fonts';

	if (!is_dir($fonts)) {
		mkdir($fonts, 0755);
	}
}

function bpdf_merge_pdfs($array, $filename) {
	bpdf_load_libs(true);

	$pdf = new Fpdi();

	if (is_array($array) && count($array) > 0) {
		foreach ($array as $file) {
			$pages = $pdf->setSourceFile(_UPLOAD_DIR . '/pdfs/' . $file);

			for ($page_num = 1; $page_num <= $pages; $page_num++) {
				$template_id = $pdf->importPage($page_num);
				$size = $pdf->getTemplateSize($template_id);

				$pdf->setPrintHeader(false);
				$pdf->setPrintFooter(false);

				$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
				$pdf->useTemplate($template_id);
			}
		}

		$pdf->Output(_UPLOAD_DIR . '/pdfs/' . $filename . '.pdf', 'F');
	}
}


//   ▄█   ███▄▄▄▄▄     ▄█       ███      
//  ███   ███▀▀▀▀██▄  ███   ▀█████████▄  
//  ███▌  ███    ███  ███▌     ▀███▀▀██  
//  ███▌  ███    ███  ███▌      ███   ▀  
//  ███▌  ███    ███  ███▌      ███      
//  ███   ███    ███  ███       ███      
//  ███   ███    ███  ███       ███      
//  █▀     ▀█    █▀   █▀       ▄████▀

function bpdf_init($dir) {
	// do something
}


//     ▄██████▄    ▄██████▄   
//    ███    ███  ███    ███  
//    ███    █▀   ███    ███  
//   ▄███         ███    ███  
//  ▀▀███ ████▄   ███    ███  
//    ███    ███  ███    ███  
//    ███    ███  ███    ███  
//    ████████▀    ▀██████▀

define('_BPDF', _bpdfSettings::get_settings());

// actions

add_action('init', 'bpdf_init');

// filters

add_filter('parent_file', 'bpdf_set_current_menu');

// boot plugin

add_action('init', function() {
	if (is_admin()) {
		new _bpdfMenu(_URL_BASIC_PDF);
	}
});

add_action('rest_api_init', function() {
	_bpdfSettings::args();
	$api = new _bpdfAPI();
	$api->add_routes();
});

// eof