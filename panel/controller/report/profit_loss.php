<?php
namespace MDcart\Admin\Controller\Report;
/**
 * Class ProfitLoss
 *
 * Profit/Loss report. Deliberately NOT granted to every user group by the
 * install scripts (unlike the warehouse/POS/purchase-invoice features) -
 * only whichever group(s) an administrator explicitly grants the
 * report/profit_loss permission to (System > Users > User Groups) can see
 * this menu item or open this page at all.
 *
 * Can be loaded using $this->load->controller('report/profit_loss');
 *
 * @package MDcart\Admin\Controller\Report
 */
class ProfitLoss extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('report/profit_loss');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('report/profit_loss', 'user_token=' . $this->session->data['user_token'])
		];

		$data['list'] = $this->getList();

		$this->load->model('catalog/warehouse');

		$data['warehouses'] = $this->model_catalog_warehouse->getWarehouses();

		$data['filter_date_start'] = $this->request->get['filter_date_start'] ?? date('Y-m-01');
		$data['filter_date_end'] = $this->request->get['filter_date_end'] ?? date('Y-m-d');
		$data['filter_warehouse_id'] = $this->request->get['filter_warehouse_id'] ?? '';

		$data['filter_action'] = $this->url->link('report/profit_loss', 'user_token=' . $this->session->data['user_token'], true);
		$data['list_action'] = $this->url->link('report/profit_loss.list', 'user_token=' . $this->session->data['user_token'], true);

		$data['currency_symbol'] = $this->config->get('config_currency');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('report/profit_loss', $data));
	}

	/**
	 * List (AJAX partial reload for the filter form)
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('report/profit_loss');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	private function getList(): string {
		$this->load->model('report/profit_loss');

		if (!$this->model_report_profit_loss->isInstalled()) {
			return $this->load->view('report/profit_loss_list', ['not_installed' => true, 'summary' => [], 'rows' => [], 'warehouses' => [], 'pagination' => '', 'currency_symbol' => $this->config->get('config_currency')]);
		}

		$filter_data = [
			'filter_date_start'   => $this->request->get['filter_date_start'] ?? date('Y-m-01'),
			'filter_date_end'     => $this->request->get['filter_date_end'] ?? date('Y-m-d'),
			'filter_warehouse_id' => $this->request->get['filter_warehouse_id'] ?? ''
		];

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = 20;

		$summary = $this->model_report_profit_loss->getProfitLossSummary($filter_data);
		$by_warehouse = $this->model_report_profit_loss->getProfitLossByWarehouse($filter_data);

		$filter_data['start'] = ($page - 1) * $limit;
		$filter_data['limit'] = $limit;

		$results = $this->model_report_profit_loss->getProfitLoss($filter_data);
		$total = $this->model_report_profit_loss->getTotalProfitLossDays($filter_data);

		$data['rows'] = [];

		foreach ($results as $result) {
			$data['rows'][] = [
				'date'        => date($this->language->get('date_format_short'), strtotime($result['date'])),
				'order_count' => $result['order_count'],
				'revenue'     => number_format($result['revenue'], 2),
				'cost'        => number_format($result['cost'], 2),
				'profit'      => number_format($result['profit'], 2),
				'margin'      => number_format($result['margin'], 2)
			];
		}

		$data['summary'] = [
			'order_count' => $summary['order_count'],
			'revenue'     => number_format($summary['revenue'], 2),
			'cost'        => number_format($summary['cost'], 2),
			'profit'      => number_format($summary['profit'], 2),
			'margin'      => number_format($summary['margin'], 2)
		];

		$data['by_warehouse'] = [];

		foreach ($by_warehouse as $row) {
			$data['by_warehouse'][] = [
				'warehouse_name' => $row['warehouse_name'] !== '' ? $row['warehouse_name'] : $this->language->get('text_unknown_warehouse'),
				'order_count'    => $row['order_count'],
				'revenue'        => number_format($row['revenue'], 2),
				'cost'           => number_format($row['cost'], 2),
				'profit'         => number_format($row['profit'], 2),
				'margin'         => number_format($row['margin'], 2)
			];
		}

		$url = '';

		if (!empty($filter_data['filter_date_start'])) {
			$url .= '&filter_date_start=' . $filter_data['filter_date_start'];
		}

		if (!empty($filter_data['filter_date_end'])) {
			$url .= '&filter_date_end=' . $filter_data['filter_date_end'];
		}

		if (!empty($filter_data['filter_warehouse_id'])) {
			$url .= '&filter_warehouse_id=' . $filter_data['filter_warehouse_id'];
		}

		$data['pages'] = [];

		$pages = (int)ceil($total / $limit);

		for ($i = 1; $i <= $pages; $i++) {
			$data['pages'][] = [
				'text' => (string)$i,
				'href' => $this->url->link('report/profit_loss.list', 'user_token=' . $this->session->data['user_token'] . $url . '&page=' . $i, true)
			];
		}

		$data['not_installed'] = false;
		$data['currency_symbol'] = $this->config->get('config_currency');

		return $this->load->view('report/profit_loss_list', $data);
	}
}
