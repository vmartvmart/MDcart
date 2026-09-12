<?php
namespace MDcart\Catalog\Controller\Localisation;
/**
 * Class Iran Geo
 *
 * @package MDcart\Catalog\Controller\Localisation
 */
class IranGeo extends \MDcart\System\Engine\Controller {
	/**
	 * County
	 *
	 * @return void
	 */
	public function county(): void {
		$zone_id = isset($this->request->get['zone_id']) ? (int)$this->request->get['zone_id'] : 0;

		$this->load->model('localisation/iran_geo');

		$json = ['county' => $this->model_localisation_iran_geo->getCountiesByZoneId($zone_id)];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * City
	 *
	 * @return void
	 */
	public function city(): void {
		$shahrestan_id = isset($this->request->get['shahrestan_id']) ? (int)$this->request->get['shahrestan_id'] : 0;

		$this->load->model('localisation/iran_geo');

		$json = $this->model_localisation_iran_geo->getCitiesByShahrestanId($shahrestan_id);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
