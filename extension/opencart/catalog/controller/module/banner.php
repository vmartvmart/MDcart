<?php
namespace MDcart\Catalog\Controller\Extension\MDcart\Module;
/**
 * Class Banner
 *
 * @package MDcart\Catalog\Controller\Extension\MDcart\Module
 */
class Banner extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param array<string, mixed> $setting array of filters
	 *
	 * @return string
	 */
	public function index(array $setting): string {
		static $module = 0;

		// Banner
		$this->load->model('design/banner');

		// Image
		$this->load->model('tool/image');

		$data['banners'] = [];

		$results = $this->model_design_banner->getBanner($setting['banner_id']);

		foreach ($results as $result) {
			$image = html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8');

			if (!is_file(DIR_IMAGE . $image)) {
				continue;
			}

			$extension = strtolower((string)pathinfo($image, PATHINFO_EXTENSION));

			if (in_array($extension, ['mp4', 'webm', 'ogg'], true)) {
				// Videos are played at their native size via CSS, not resized like images.
				$data['banners'][] = [
					'title' => $result['title'],
					'link'  => $result['link'],
					'image' => HTTP_CATALOG . 'image/' . $image,
					'type'  => 'video'
				];
			} else {
				$data['banners'][] = [
					'title' => $result['title'],
					'link'  => $result['link'],
					'image' => $this->model_tool_image->resize($image, $setting['width'], $setting['height']),
					'type'  => 'image'
				];
			}
		}

		if ($data['banners']) {
			$data['module'] = $module++;

			$data['effect'] = $setting['effect'];
			$data['controls'] = $setting['controls'];
			$data['indicators'] = $setting['indicators'];
			$data['items'] = $setting['items'];
			$data['interval'] = $setting['interval'];
			$data['width'] = $setting['width'];
			$data['height'] = $setting['height'];
			$data['full_width'] = $setting['full_width'] ?? 0;

			return $this->load->view('extension/opencart/module/banner', $data);
		} else {
			return '';
		}
	}
}
