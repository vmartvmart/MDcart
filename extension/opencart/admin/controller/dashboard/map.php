<?php
namespace MDcart\Admin\Controller\Extension\Opencart\Dashboard;
/**
 * Class Map
 *
 * @package MDcart\Admin\Controller\Extension\Opencart\Dashboard
 */
class Map extends \MDcart\System\Engine\Controller {
	/**
	 * Maps the numeric path IDs used by the Iran jqvmap SVG (jquery.vmap.iran.js) to the
	 * matching zone_id values in the oc_zone table, since the two are named/keyed differently.
	 *
	 * @var array<int, int>
	 */
	private array $path_zone_map = [
		15 => 1557, // Kish Island -> Hormozgan
		16 => 1546, // West Azarbaijan
		17 => 1543, // Ardabil
		18 => 1545, // East Azarbaijan
		19 => 1548, // Hamadan
		20 => 1544, // Zanjan
		21 => 1547, // Kurdistan
		22 => 1563, // Mazandaran
		23 => 1541, // Qazvin
		24 => 1542, // Gilan
		25 => 1550, // Ilam
		26 => 1551, // Lorestan
		27 => 1549, // Kermanshah
		28 => 1539, // Qom
		29 => 1540, // Markazi
		30 => 3969, // Alborz
		31 => 1538, // Tehran
		32 => 1553, // Chahar Mahaal and Bakhtiari
		33 => 1555, // Bushehr
		34 => 1552, // Khuzestan
		35 => 1554, // Kohkiluyeh and Buyer Ahmad
		36 => 1560, // Yazd
		37 => 1556, // Fars
		38 => 1561, // Esfahan
		39 => 1566, // Razavi Khorasan
		40 => 1564, // Golestan
		41 => 1562, // Semnan
		42 => 1565, // North Khorasan
		43 => 1558, // Sistan and Baluchistan
		44 => 1559, // Kerman
		45 => 1557, // Hormozgan
		46 => 1567  // South Khorasan
	];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/opencart/dashboard/map');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart/dashboard/map', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart/dashboard/map.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard');

		$data['dashboard_map_width'] = $this->config->get('dashboard_map_width');

		$data['columns'] = [];

		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}

		$data['dashboard_map_status'] = $this->config->get('dashboard_map_status');
		$data['dashboard_map_sort_order'] = $this->config->get('dashboard_map_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/dashboard/map_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/opencart/dashboard/map');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart/dashboard/map')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('dashboard_map', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Dashboard
	 *
	 * @return string
	 */
	public function dashboard(): string {
		$this->load->language('extension/opencart/dashboard/map');

		$data['text_order'] = $this->language->get('text_order');
		$data['text_sale'] = $this->language->get('text_sale');
		$data['text_city'] = $this->language->get('text_city');
		$data['text_no_results'] = $this->language->get('text_no_results');

		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('extension/opencart/dashboard/map_info', $data);
	}

	/**
	 * Map
	 *
	 * @return void
	 */
	public function map(): void {
		$json = [];

		// Extension
		$this->load->model('extension/opencart/dashboard/map');

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountryByIsoCode2('IR');

		if ($country_info) {
			$results = $this->model_extension_opencart_dashboard_map->getTotalOrdersByZone($country_info['country_id']);

			$zone_path_map = array_flip($this->path_zone_map);

			foreach ($results as $result) {
				$path_id = $zone_path_map[$result['payment_zone_id']] ?? null;

				if ($path_id !== null) {
					$json[(string)$path_id] = [
						'total'   => $result['total'],
						'amount'  => $this->currency->format($result['amount'], $this->config->get('config_currency')),
						'zone_id' => $result['payment_zone_id']
					];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * City
	 *
	 * @return void
	 */
	public function city(): void {
		$json = [];

		$path_id = isset($this->request->get['path_id']) ? (int)$this->request->get['path_id'] : 0;
		$zone_id = $this->path_zone_map[$path_id] ?? 0;

		if ($zone_id) {
			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone($zone_id);

			$json['zone'] = $zone_info ? $zone_info['name'] : '';

			// Extension
			$this->load->model('extension/opencart/dashboard/map');

			$results = $this->model_extension_opencart_dashboard_map->getTotalOrdersByCity($zone_id);

			$json['cities'] = [];

			foreach ($results as $result) {
				$json['cities'][] = [
					'city'   => $result['payment_city'],
					'total'  => $result['total'],
					'amount' => $this->currency->format($result['amount'], $this->config->get('config_currency'))
				];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
