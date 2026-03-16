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
define('_PLUGIN_BASIC_PDF_ICON', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500"><path fill="#a7aaad" d="M305.9,133.4h-141.5v233.1h171.3s0-204.7,0-204.7h-29.8v-28.4ZM307.7,332.1h-114.9c-9.6,0-9.6-14.9,0-14.9h114.9c9.6,0,9.6,14.9,0,14.9ZM310,298.8h-114.9c-9.6,0-9.6-14.9,0-14.9h114.9c9.6,0,9.6,14.9,0,14.9ZM310,263.4h-114.9c-9.6,0-9.6-14.9,0-14.9h114.9c9.6,0,9.6,14.9,0,14.9ZM310,230.7h-114.9c-9.6,0-9.6-14.9,0-14.9h114.9c9.6,0,9.6,14.9,0,14.9ZM310,180.5c9.6,0,9.6,14.9,0,14.9h-114.9c-9.6,0-9.6-14.9,0-14.9h114.9Z"/><path fill="#a7aaad" d="M250,9.8L42,129.9v240.2l208,120.1,208-120.1v-240.2L250,9.8ZM353.6,384.5h-207.1V115.5h168.6l38.5,37.3v231.6Z"/></svg>');

define('_URL_BASIC_PDF', plugin_dir_url(__FILE__));
define('_PATH_BASIC_PDF', plugin_dir_path(__FILE__));
define('_PTM', 0.352778);

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
	],
	'colour_mode' => [
		'type' => 'string',
		'default' => 'rgb'
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
			],
			'colour_mode' => [
				'label' => 'Colour Mode',
				'type' => 'select',
				'values' => [
					'hex' => 'HEX',
					'rgb' => 'RGB',
					'cmyk' => 'CMYK'
				]
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


//     ▄███████▄  ████████▄      ▄████████  
//    ███    ███  ███   ▀███    ███    ███  
//    ███    ███  ███    ███    ███    █▀   
//    ███    ███  ███    ███   ▄███▄▄▄      
//  ▀█████████▀   ███    ███  ▀▀███▀▀▀      
//    ███         ███    ███    ███         
//    ███         ███   ▄███    ███         
//   ▄████▀       ████████▀     ███

// load our libraries

function bpdf_load_libs($fpdi = false) {
	require_once(__DIR__ . '/lib/tcpdf.php');

	if ($fpdi) {
		require_once(__DIR__ . '/lib/fpdi.php');
	}
}

// handle ttf font upload to the media library
// and create our pdf font definitions files

function bpdf_font_handler($aid) {
    $file = get_attached_file($aid);
    $mime = get_post_mime_type($aid);

    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'ttf' || $mime === 'font/ttf') {
        s9pdf_create_font_defs($file);
    }
}

// create our font defintions folder if needed
// and add in helvetica for default text

function bpdf_make_folder($dir) {
	$fonts = $dir . 'fonts';

	if (!is_dir($fonts)) {
		mkdir($fonts, 0755);
	}

	if (!file_exists($fonts . '/helvetica.php')) {
		file_put_contents($fonts . '/helvetica.php', '<' . '?php ' . '$type="core";$name="Helvetica";$up=-100;$ut=50;$dw=513;$diff="";$enc="";$desc=array("Flags"=>32,"FontBBox"=>"[-166 -225 1000 931]","ItalicAngle"=>0,"Ascent"=>931,"Descent"=>-225,"Leading"=>0,"CapHeight"=>718,"XHeight"=>523,"StemV"=>88,"StemH"=>76,"AvgWidth"=>513,"MaxWidth"=>1015,"MissingWidth"=>513);$cw=array(0=>500,1=>500,2=>500,3=>500,4=>500,5=>500,6=>500,7=>500,8=>500,9=>500,10=>500,11=>500,12=>500,13=>500,14=>500,15=>500,16=>500,17=>500,18=>500,19=>500,20=>500,21=>500,22=>500,23=>500,24=>500,25=>500,26=>500,27=>500,28=>500,29=>500,30=>500,31=>500,32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>277,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,127=>500,128=>655,129=>500,130=>222,131=>278,132=>333,133=>1000,134=>556,135=>556,136=>333,137=>1000,138=>667,139=>250,140=>1000,141=>500,142=>611,143=>500,144=>500,145=>222,146=>221,147=>333,148=>333,149=>350,150=>556,151=>1000,152=>333,153=>1000,154=>500,155=>250,156=>938,157=>500,158=>500,159=>667,160=>278,161=>278,162=>556,163=>556,164=>556,165=>556,166=>260,167=>556,168=>333,169=>737,170=>370,171=>448,172=>584,173=>333,174=>737,175=>333,176=>606,177=>584,178=>350,179=>350,180=>333,181=>556,182=>537,183=>278,184=>333,185=>350,186=>365,187=>448,188=>869,189=>869,190=>879,191=>556,192=>667,193=>667,194=>667,195=>667,196=>667,197=>667,198=>1000,199=>722,200=>667,201=>667,202=>667,203=>667,204=>278,205=>278,206=>278,207=>278,208=>722,209=>722,210=>778,211=>778,212=>778,213=>778,214=>778,215=>584,216=>778,217=>722,218=>722,219=>722,220=>722,221=>667,222=>666,223=>611,224=>556,225=>556,226=>556,227=>556,228=>556,229=>556,230=>896,231=>500,232=>556,233=>556,234=>556,235=>556,236=>251,237=>251,238=>251,239=>251,240=>556,241=>556,242=>556,243=>556,244=>556,245=>556,246=>556,247=>584,248=>611,249=>556,250=>556,251=>556,252=>556,253=>500,254=>555,255=>500);');
	}
}

// create font definitions from file

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

// c,m,y,k to [r,g,b] function

function bpdf_cmyk_rgb($value) {
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

// r,g,b to [r,g,b] function

function bpdf_rgb_rgb($value) {
	$parts = explode(',', $value);

	if (count($parts) == 3) {
		return [
			'r' => intval(trim($parts[0])),
			'g' => intval(trim($parts[1])),
			'b' => intval(trim($parts[2]))
		];
	}

	return false;
}

// #rrggbb to [r,g,b] function

function bpdf_hex_rgb($value) {
	list($r, $g, $b) = sscanf($value, '#%02x%02x%02x');

	return [
		'r' => intval($r),
		'g' => intval($g),
		'b' => intval($b)
	];
}

// get colours

function bpdf_get_colours($colour) {
	switch (_BPDF['colour_mode']) {
		case 'rgb': {
			$colours = bpdf_rgb_rgb($colour);
			break;
		}
		case 'cmyk': {
			$colours = bpdf_cmyk_rgb($colour);
			break;
		}
		case 'hex': {
			$colours = bpdf_hex_rgb($colour);
			break;
		}
		default: {
			$colours = false;
		}
	}

	return $colours;
}

function bpdf_generate_pdf($width, $height, $data, $filename, $template = false) {
	$doc = (is_array($data)) ? $data : json_decode($data, true);

	$result = false;

	if ($doc && count($doc)) {
		$upload_dir = wp_upload_dir();
		$dir = $upload_dir['basedir'] . '/';

		bpdf_make_folder($dir);
		bpdf_load_libs($template);

		if ($template) {
			$pdf = new Fpdi();
			$pages = $pdf->setSourceFile(_UPLOAD_DIR . '/' . $template);

			for ($page_num = 1; $page_num <= $pages; $page_num++) {
				$template_id = $pdf->importPage($page_num);
				$size = $pdf->getTemplateSize($template_id);
				$pdf->setPrintHeader(false);
				$pdf->setPrintFooter(false);
				$pdf->SetAutoPageBreak(true, 0);

				for ($x = 1; $x <= count($doc); $x++) {
					$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
					$pdf->useTemplate($template_id);
				}
			}
		}
		else {
			$pdf = new TCPDF('L', 'mm', [$width, $height]);
			$pdf->SetMargins(0, 0, 0, 0);
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->SetCellHeightRatio(1);

			for ($x = 1; $x <= count($doc); $x++) {
				$pdf->AddPage();
			}
		}

		foreach ($doc as $page => $page_data) {
			list($void, $page_num) = explode('_', $page);
			$pdf->setPage((int)$page_num);

			foreach ($page_data as $asset => $data_object) {
				list($type, $num) = explode('_', $asset);

				$data = (array)$data_object;

				switch ($type) {
					case 'g': {
						bpdf_pdf_graphic('graphic', $data, $pdf);

						break;
					}
					case 't': {
						bpdf_pdf_text($data, $pdf);

						break;
					}
					case 'u': {
						// url
						bpdf_pdf_link($data, $pdf);

						break;
					}
					case 'b': {
						// box
						bpdf_pdf_box($data, $pdf);

						break;
					}
					case 'r': {
						// rectangle
						//bpdf_pdf_rect($data, $pdf);

						break;
					}
				}
			}
		}

		$file = _UPLOAD_DIR . '/' . $filename . '.pdf';

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
			$left = $data['left'] ?? 0;
			$bottom = $data['bottom'] ?? 0;
			$width = $data['width'] ?? -1;
			$height = $data['height'] ?? -1;

			$pdf->Image($image, $left, ($bottom - $height), $width, $height, $ext, '', '', true, 300);
		}
		else {
			$pdf->SetFont('helvetica', '', 10);
			$pdf->SetXY($data['left'], ($data['bottom'] - $data['height']));
			$pdf->Cell(0, 10, 'unsupported image format', 0, 1, 'C');
		}
	}
	else {
		$image = '@' . '<svg viewBox="0 0 20 20" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" width="20" height="20" fill="none" stroke="black" stroke-width="2" /><line x1="0" y1="0" x2="20" y2="20" stroke="black" stroke-width="1" /><line x1="20" y1="0" x2="0" y2="20" stroke="black" stroke-width="1" /></svg>';
		$pdf->ImageSVG($image, $data['left'], ($data['bottom'] - $data['height']), $data['width'], $data['height']);
	}
}

function bpdf_pdf_text(&$data, &$pdf) {
	$dir = wp_upload_dir()['basedir'] . '/';

	$size = ($data['font_height'] / _PTM);
	$lines = $data['max_lines'];
	$text = $data['text'];

	if ($data['font_name'] != '') {
		$font = pathinfo($data['font_name'], PATHINFO_FILENAME);

		$pdf->AddFont($font, '', $font . '.php', $dir);
	}
	else {
		$font = 'helvetica';
	}

	$pdf->SetFont($font, '', $size);

	if (isset($data['font_colour']) && ($data['font_colour'] != '')) {
		$colours = bpdf_get_colours($data['font_colour']);

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
			while ($pdf->GetStringWidth($text) > $data['width'] && $size > ($data['font_min'] / _PTM)) {
				$size--;
				$pdf->SetFont($font, '', $size);
			}

			$pdf->SetXY($data['left'], $data['bottom']);
			$pdf->Cell($data['width'], $data['height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');

			break;
		}
		case 'lines': {
			$pdf->SetXY($data['left'], $data['top']);
			$pdf->MultiCell($data['width'], $data['height'], $text, 0, $align, false, 0, '', '', false, 0, false, false, $data['height'], 'B', false);
			break;
		}
		case 'shrink-lines': {
			while ($pdf->GetStringWidth($text) > $data['width'] && $size > ($data['font_min'] / _PTM)) {
				$size--;
				$pdf->SetFont($font, '', $size);
			}

			if ($pdf->GetStringWidth($text) > $data['width']) {
				$text_lines = bpdf_split_lines($pdf, $text, $data['width']);
				$new_lines = [];

				for ($x = 0; $x < $lines; $x++) {
					$new_lines[] = str_replace("\n", ' ', $text_lines[$x]);
				}

				$pdf->SetXY($data['left'], ($data['bottom'] - ($data['height'] * $lines)));
				$pdf->MultiCell($data['width'], $size, implode("\n", $new_lines), 0, $align, false, 0, '', '', false, 0, false, false, $data['height'] * $lines, 'B', false);
			}
			else {
				$pdf->SetXY($data['left'], $data['bottom']);
				$pdf->Cell($data['width'], $data['height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			}

			break;
		}
		case 'lines-shrink': {
			if ($pdf->GetStringWidth($text) > $data['width']) {
				$text_lines = bpdf_split_lines($pdf, $text, $data['width']);

				while (count($text_lines) > $lines) {
					$size--;
					$pdf->SetFont($font, '', $size);
					$text_lines = bpdf_split_lines($pdf, $text, $data['width']);
				}

				$output = $text;

				$pdf->SetXY($data['left'], ($data['bottom'] - ($data['height'] * $lines)));
				$pdf->MultiCell($data['width'], $size, $output, 0, $align, false, 0, '', '', true, 0, false, false, $size * $lines, 'B', false);
			}
			else {
				$pdf->SetXY($data['left'], $data['bottom']);
				$pdf->Cell($data['width'], $data['height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			}

			break;
		}
		default: {
			$pdf->SetXY($data['left'], $data['bottom']);
			$pdf->Cell($data['width'], $data['height'], $text, 0, 0, $align, 0, '', 0, false, 'B', 'B');
			break;
		}
	}
}

// add link to pdf

function bpdf_pdf_link(&$data, &$pdf) {
	if (isset($data['text'])) {
		$pdf->SetXY($data['left'], $data['top']);
		$pdf->Write($data['height'], $data['text'], $data['url'], false, 'L', true);
	}
	else {
		$pdf->Link($data['left'], $data['top'], $data['width'], $data['height'], $data['url']);
	}

	if (isset($data['border']) && $data['border']) {
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.5);
		$pdf->Rect($data['left'], $data['top'], $data['width'], $data['height'], 'D');
	}
}

// add a filled box to pdf

function bpdf_pdf_box(&$data, &$pdf) {
	if (isset($data['colour']) && ($data['colour'] != '')) {
		$colours = bpdf_get_colours($data['colour']);
		$pdf->SetFillColor($colours['r'], $colours['g'], $colours['b']);
	}
	else {
		$pdf->SetFillColor(0, 0, 0);
	}

	$pdf->Rect($data['left'], $data['top'], $data['width'], $data['height'], 'F');
}

// merge multiple pdfs into one

function bpdf_merge_pdfs($array, $filename) {
	bpdf_load_libs(true);

	$pdf = new Fpdi();

	if (is_array($array) && count($array) > 0) {
		foreach ($array as $file) {
			$pages = $pdf->setSourceFile(_UPLOAD_DIR . '/' . $file);

			for ($page_num = 1; $page_num <= $pages; $page_num++) {
				$template_id = $pdf->importPage($page_num);
				$size = $pdf->getTemplateSize($template_id);

				$pdf->setPrintHeader(false);
				$pdf->setPrintFooter(false);

				$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
				$pdf->useTemplate($template_id);
			}
		}

		$result = $filename . '.pdf';
		$pdf->Output(_UPLOAD_DIR . '/' . $result, 'F');

		if (file_exists(_UPLOAD_DIR . '/' . $result)) {
			return $result;
		}
	}

	return false;
}


//  ███    █▄      ▄████████     ▄████████  
//  ███    ███    ███    ███    ███    ███  
//  ███    ███    ███    █▀     ███    █▀   
//  ███    ███    ███          ▄███▄▄▄      
//  ███    ███  ▀███████████  ▀▀███▀▀▀      
//  ███    ███           ███    ███    █▄   
//  ███    ███     ▄█    ███    ███    ███  
//  ████████▀    ▄████████▀     ██████████

/*

CLASS FUNCTIONS HERE TO MAKE PDF GENERATION EASY

$width and $height are in mm
$data is an array like this:

$page_1['t_1'] = [
	'bottom' => '54',
	'left' => '53',
	'width' => '180',
	'height' => '9',
	'font_name' => 'lato-bold',
	'text_align' => 'left',
	'font_height' => '9',
	'max_lines' => '1',
	'size_method' => 'shrink',
	'font_min' => '10',
	'text' => 'hello, world!',
	'font_colour' => '100,28,0,22'
];

$page_1['g_1'] = [
	'bottom' => '100',
	'left' => '10',
	'height' => '30',
	'graphic' => 'test-image.png'
];

$data = [
	'page_1' => $page_1
];

...then passed like this:

json_encode($data)

$file is the name of the output pdf

$template if supplied is the pdf to add the content to
and width/height are ignored as it uses the size of the
template pdf

size_method for text is what happens if text is too wide for specified
width at the specified font_size. options are:

- shrink = the text size is reduced in size to fit
- lines = the text is split on to multiple lines up to max_lines
- shrink-lines = shrink text to font_min size, and then split on to lines if need be
- lines-shrink = split on to multiple lines up to max_lines, and then shrink text if need be

*/

class BPDF {
	public static function generate($width, $height, $data, $file, $template = false) {
		return bpdf_generate_pdf($width, $height, $data, $file, $template);
	}

	public static function merge($files_in, $file_out) {
		return bpdf_merge_pdfs($files_in, $file_out);
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
	if (!defined('_UPLOAD_DIR')) {
		define('_UPLOAD_DIR', wp_upload_dir()['path']);
	}

	if (_BPDF['active'] == 'yes') {
		// do something maybe?
	}
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
add_action('add_attachment', 'bpdf_font_handler');

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

/*

{
	"page_1": {
		"g_1":{
			"asset_bottom":"17",
			"asset_left":"45",
			"asset_height":"5.4",
			"asset_graphic":"test-image.png"
		},
		"t_1":{
			"asset_bottom":"6.6",
			"asset_left":"4.1",
			"asset_width":"68",
			"asset_height":"2.8",
			"text_font":"",
			"text_align":"left",
			"font_height":"2.8",
			"max_lines":"1",
			"size_method":"shrink",
			"font_min":"2.8",
			"asset_text":"test text",
			"font_colour":"0,100,100,0"
		}
	}
}

*/

// eof