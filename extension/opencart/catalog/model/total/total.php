<?php
namespace MDcart\Catalog\Model\Extension\MDcart\Total;
/**
 * Class Total
 *
 * Can be called from $this->load->model('extension/opencart/total/total');
 *
 * @package MDcart\Catalog\Model\Extension\MDcart\Total
 */
class Total extends \MDcart\System\Engine\Model {
	/**
	 * Get Total
	 *
	 * @param array<int, array<string, mixed>> $totals
	 * @param array<int, float>                $taxes
	 * @param float                            $total
	 *
	 * @return void
	 */
	public function getTotal(array &$totals, array &$taxes, float &$total): void {
		$this->load->language('extension/opencart/total/total');

		$totals[] = [
			'extension'  => 'opencart',
			'code'       => 'total',
			'title'      => $this->language->get('text_total'),
			'value'      => $total,
			'sort_order' => (int)$this->config->get('total_total_sort_order')
		];
	}
}
