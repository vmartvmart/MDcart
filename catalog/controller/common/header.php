<?php
namespace MDcart\Catalog\Controller\Common;
/**
 * Class Header
 *
 * Can be called from $this->load->controller('common/header');
 *
 * @package MDcart\Catalog\Controller\Common
 */
class Header extends \MDcart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		// Analytics
		$data['analytics'] = [];

		if (!$this->config->get('config_cookie_id') || (isset($this->request->cookie['policy']) && $this->request->cookie['policy'])) {
			// Extension
			$this->load->model('setting/extension');

			$analytics = $this->model_setting_extension->getExtensionsByType('analytics');

			foreach ($analytics as $analytic) {
				if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
					$data['analytics'][] = $this->load->controller('extension/' . $analytic['extension'] . '/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
				}
			}
		}

		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['title'] = $this->document->getTitle();
		$data['base'] = $this->config->get('config_url');
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();

		// Hard coding css, so they can be replaced via the event's system.
		// Bootstrap ships a dedicated RTL build. Select it from the language
		// direction so switching the storefront language also switches the layout.
		$data['bootstrap'] = $data['direction'] === 'rtl'
			? 'catalog/view/stylesheet/bootstrap.rtl.min.css'
			: 'catalog/view/stylesheet/bootstrap.css';
		$data['icons'] = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
		$data['stylesheet'] = 'catalog/view/stylesheet/stylesheet.css';
		$data['rtl_stylesheet'] = $data['direction'] === 'rtl' ? 'catalog/view/stylesheet/rtl.css' : '';

		// Hard coding scripts, so they can be replaced via the event's system.
		$data['jquery'] = 'catalog/view/javascript/jquery/jquery-3.7.1.min.js';

		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');

		// Design > Colors overrides (Setting: config_color_store_*), layered
		// on top of whichever theme is active rather than tied to it — see
		// buildColorOverridesCss()'s own docblock.
		$data['color_overrides_css'] = $this->buildColorOverridesCss();

		$data['name'] = $this->config->get('config_name');

		// Fav icon
		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$data['icon'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_icon');
		} else {
			$data['icon'] = '';
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

		// Used only by the "stockland" theme's top promo strip (Design > Theme).
		$data['text_promo_strip'] = $this->language->get('text_promo_strip');

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist($this->customer->getId()));
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['home'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
		$data['wishlist'] = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['logged'] = $this->customer->isLogged();

		if (!$this->customer->isLogged()) {
			$data['register'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'));
			$data['login'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
		} else {
			$data['account'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
			$data['order'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
			$data['transaction'] = $this->url->link('account/transaction', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
			$data['download'] = $this->url->link('account/download', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
			$data['logout'] = $this->url->link('account/logout', 'language=' . $this->config->get('config_language'));
		}

		$data['shopping_cart'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
		$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
		$data['contact'] = $this->url->link('information/contact', 'language=' . $this->config->get('config_language'));
		$data['telephone'] = $this->config->get('config_telephone');

		// A "tel:" link needs just the dialable digits (plus a leading "+"
		// for an international number) - the store's configured phone
		// number is free text meant for display (dashes, spaces, parens),
		// so it can't be used as the href as-is. Used by the header's
		// phone icon to start a call directly instead of opening the
		// Contact page - see header.twig.
		$data['telephone_tel'] = preg_replace('/[^0-9+]/', '', (string)$data['telephone']);

		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		return $this->load->view('common/header', $data);
	}

	/**
	 * Build Color Overrides Css
	 *
	 * Design > Colors (design/color) lets the admin pick a handful of
	 * storefront colors — independent of, and layered on top of, whichever
	 * Design > Theme Switcher preset (or the default theme) is active — see
	 * that controller's own docblock for why it's kept separate rather than
	 * baked into a theme preset. Every knob here is optional: an unset
	 * (empty) setting is simply skipped, so a store that has never touched
	 * this page renders byte-for-byte the same as before it existed.
	 *
	 * Returns plain CSS text (not wrapped in a <style> tag — the template
	 * does that) meant to be output after every other stylesheet, so its
	 * un-prefixed selectors win the cascade on specificity ties; the
	 * !important on each declaration is a safety net against a theme
	 * stylesheet that happens to be more specific.
	 *
	 * @return string
	 */
	private function buildColorOverridesCss(): string {
		$css = '';

		$menu_bg = (string)$this->config->get('config_color_store_menu_bg');
		$menu_text = (string)$this->config->get('config_color_store_menu_text');
		$primary = (string)$this->config->get('config_color_store_primary');
		$footer_bg = (string)$this->config->get('config_color_store_footer_bg');
		$footer_text = (string)$this->config->get('config_color_store_footer_text');
		$card_bg = (string)$this->config->get('config_color_store_card_bg');

		// #menu is the main colored navigation/category bar (the site's
		// "top menu"). It ships with a gradient background image, which
		// paints over a plain background-color, so a custom color has to
		// switch the image off too or it would never actually show.
		if ($menu_bg && oc_validate_hex_color($menu_bg)) {
			$css .= '#menu { background-color: ' . $menu_bg . ' !important; background-image: none !important; }' . "\n";
		}

		if ($menu_text && oc_validate_hex_color($menu_text)) {
			$css .= '#menu .navbar-nav > li > a { color: ' . $menu_text . ' !important; }' . "\n";
		}

		// Bootstrap's own compiled CSS hardcodes each button variant's
		// colors as literal hex values on the .btn-primary rule itself
		// (a normal result of how Bootstrap is built from Sass, not a bug
		// here), so redefining the :root --bs-primary variable alone would
		// not reach button backgrounds — .btn-primary's own --bs-btn-*
		// variables need to be redeclared directly. Hover/active shades are
		// derived from the one picked color the same way a real Bootstrap
		// build would at compile time, so a single color still yields a
		// natural-looking button instead of one flat, unshaded color.
		if ($primary && oc_validate_hex_color($primary)) {
			$hover = oc_color_shade($primary, -15);
			$active = oc_color_shade($primary, -25);

			$css .= ':root { --bs-primary: ' . $primary . '; --bs-primary-rgb: ' . oc_hex_to_rgb($primary) . '; --bs-link-color: ' . $primary . '; --bs-link-color-rgb: ' . oc_hex_to_rgb($primary) . '; --bs-link-hover-color: ' . $hover . '; }' . "\n";
			$css .= '.btn-primary { --bs-btn-bg: ' . $primary . '; --bs-btn-border-color: ' . $primary . '; --bs-btn-hover-bg: ' . $hover . '; --bs-btn-hover-border-color: ' . $hover . '; --bs-btn-active-bg: ' . $active . '; --bs-btn-active-border-color: ' . $active . '; --bs-btn-disabled-bg: ' . $primary . '; --bs-btn-disabled-border-color: ' . $primary . '; }' . "\n";
		}

		if ($footer_bg && oc_validate_hex_color($footer_bg)) {
			$css .= 'footer { background-color: ' . $footer_bg . ' !important; }' . "\n";
		}

		if ($footer_text && oc_validate_hex_color($footer_text)) {
			$css .= 'footer, footer a, footer h5 { color: ' . $footer_text . ' !important; }' . "\n";
		}

		if ($card_bg && oc_validate_hex_color($card_bg)) {
			$css .= '.product-thumb { background-color: ' . $card_bg . ' !important; }' . "\n";
		}

		return $css;
	}
}
