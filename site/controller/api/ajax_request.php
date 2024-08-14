<?php

/**
 * ControllerApiAjaxRequest
 */
class ControllerApiAjaxRequest extends Controller
{
	public $json = [];
	public $data = [];

	public function index()
	{
		if ($this->isAjax()) {
			$type = $this->request->get['t'] ?: '';
			switch ($type) {
				case 'slots_filter':
				case 'winning_filter':
				case 'software_filter':
					$this->json = $this->getField();
					break;
				case 'load_more':
					$this->json = $this->loadMore();
					break;
				case 'casino_filter':
					$this->json = $this->casinoFilter();
					break;
				case 'software_popup':
					$this->json = $this->casinoSoftwarePopup();
					break;
				case 'blog':
					$this->json = $this->blogAction();
					break;
				case 'welcome':
					$this->json = $this->loadBanner();
					break;
				case 'blog_like':
					$this->json = $this->blogLike();
					break;
				case 'review_add':
					$this->json = $this->reviewAdd();
					break;
				case 'like_dislike':
					$this->json = $this->addLikeDislike();
					break;
				case 'checkout':
					$this->json = $this->checkout();
					break;
				case 'login':
					$this->json = $this->login();
					break;
				case 'register':
					$this->json = $this->register();
					break;
				case 'read_activity':
					$this->json = $this->readActivity();
					break;
				default:
					$this->isAjax();
					break;
			}

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setJsonOutput($this->json);
		} else {
			return new Action('error/not_found');
		}
	}

	private function getField()
	{
		if (empty($this->request->post)) {
			$this->request->post = json_decode(file_get_contents('php://input'), true);
		}

		$field_id = $this->request->post['field_id'] ?? $this->request->get['field_id'] ?? 0;
		$field = $this->model_content_field->getFields([
			'field_id' => $field_id
		]);
		$field_data = current($field);

		if ($field_data) {
			$field_data['field_value'] = json_decode($field_data['field_value'], true);

			$data = $this->load->controller('field/' . $field_data['field_type'] . '/getData', $field_data);

			if ($this->load->view_exists('field/ajax_request/' . $field_data['field_type'] . '_' . $field_data['field_view'])) {
				$this->json['result_html'] = $this->load->view('field/ajax_request/' . $field_data['field_type'] . '_' . $field_data['field_view'], $data);
			} elseif ($this->load->view_exists('field/ajax_request/' . $field_data['field_type'])) {
				$this->json['result_html'] = $this->load->view('field/ajax_request/' . $field_data['field_type'], $data);
			}
			$this->json['show_more'] = $data['total'] > $data['limit'] * $data['page'];
			$this->json['page'] = $data['page'];
			$this->json['success'] = true;
		}

		return $this->json;
	}

	// code from content/casino_slot *start*
	private function casinoErrorToList()
	{
		if (isset($this->request->post['casino_slot_id'])) {
			$casino_slot_id = (int)$this->request->post['casino_slot_id'];
		} else {
			$casino_slot_id = false;
		}

		if (isset($this->request->post['type'])) {
			$type = $this->request->post['type'];
		} else {
			$type = false;
		}

		if ($casino_slot_id) {
			$casino_slot_info = $this->model_content_casino_slot->getCasinoSlot($casino_slot_id);
		} else {
			$casino_slot_info = [];
		}

		if ($casino_slot_info) {
			if (empty($this->session->data['error']['casino_slot'][(int)$casino_slot_id]) || $this->session->data['error']['casino_slot'][(int)$casino_slot_id] != $type) {
				$complete = $this->model_content_casino_slot->addCasinoSlotErrorToList($casino_slot_id, $this->url->link('content/casino_slot/view', 'casino_slot_id=' . $casino_slot_id), $type);

				if ($complete) {
					$this->session->data['error']['casino_slot'][(int)$casino_slot_id] = $type ? $type : true;

					$this->json['success'] = $this->language->get('text_error_added_to_errorlist');
				} else {
					$this->json['error']   = $this->language->get('error_could_not_add_error_list');
				}
			} else {
				$this->json['error']   = $this->language->get('error_this_report_just_send_yet');
			}
		} else {
			$this->json['error']   = $this->language->get('error_casino_slot_not_found');
		}
	}
	// code from content/casino_slot *end*

	// code from content/casino *start*
	private function casinoFilter()
	{
		if (empty($this->request->post)) {
			$this->request->post = json_decode(file_get_contents('php://input'), true);
		}
		$casino_category_id = !empty($this->request->post['cat_id']) ? (int) $this->request->post['cat_id'] : 0;
		$casino_bonus_type_id = !empty($this->request->post['btype_id']) ? (int) $this->request->post['btype_id'] : 0;

		if ($casino_category_id) {
			$page_info = $this->model_content_casino->getCasinoCategory($casino_category_id);
		} elseif ($casino_bonus_type_id) {
			$page_info = $this->model_content_casino_bonus->getCasinoBonusType($casino_bonus_type_id);
		} else {
			$page_info = [];
		}

		if ($page_info) {
			foreach ($page_info as $key => $value) {
				if (!$value) {
					continue;
				}

				$value = is_string($value) ? json_decode($value, true) : $value;

				if (is_string($page_info[$key]) && is_array($value)) {
					$page_info[$key] = $value;
				}
			}
		}

		$exclude_casino_ids = !empty($this->request->post['exclude']) ? array_map('intval', explode(',', $this->request->post['exclude'])) : [];

		$no_software = 0;

		$request_data = array(
			'not_blacklisted'    => 1,
			'with_payments'      => 1,
			'with_softwares'     => 1,
			'with_bonus'         => 1,
			'limit'              => !empty($this->request->post['limit']) ? (int) $this->request->post['limit'] : 10,
			'start'              => !empty($this->request->post['start']) ? (int) $this->request->post['start'] : 0,
			'with_user_rating'   => !empty($this->request->post['u_rating']) ? (int) $this->request->post['u_rating'] : 0,
			'bonus_type_ids'     => $casino_bonus_type_id ? [$casino_bonus_type_id] : [],
			'category_ids'       => $casino_category_id ? [$casino_category_id] : [],
			'payment_ids'        => !empty($this->request->post['pmnt_id']) ? array((int) $this->request->post['pmnt_id']) : [],
			'exclude_casino_ids' => $exclude_casino_ids,
			'sort'               => !empty($this->request->post['sort']) ? $this->request->post['sort'] : 'rating',
			'order'              => !empty($this->request->post['order']) ? $this->request->post['order'] : 'DESC'
		);

		if ($page_info && !isset($request_data['by_country']) && !empty($page_info['mode'])) {
			if ($page_info['mode'] == 'by_country') {
				$request_data['by_country'] = true;
			} elseif ($page_info['mode'] == 'others') {
				$request_data['by_country'] = false;
			}
		}

		$this->data['c_limit'] = $request_data['limit'];
		$this->data['c_start'] = $request_data['start'];

		$this->data['casino_user_rating'] = $this->url->link('content/casino/user_rating');

		if (!empty($request_data['category_ids']) && $request_data['category_ids'][0] == -5) {
			$request_data['category_ids']   = 0;
			$request_data['bonus_type_ids'] = array(23);
		}

		if (!empty($request_data['category_ids']) && $request_data['category_ids'][0] == -2) {
			$request_data['category_ids'] = 0;
		}

		if (!empty($request_data['category_ids']) && $request_data['category_ids'][0] == -1) {
			$c_lang_id     = $this->config->get('config_language_id');
			$homepage_info = $this->config->get('config_main')[$c_lang_id];

			$request_data['category_ids'] = 0;
			$request_data['casino_ids'] = $homepage_info['toprated']['clist'];
			$request_data['sort_casino_ids'] = $homepage_info['toprated']['clist'];
		}

		if (!empty($request_data['with_user_rating'])) {
			$request_data['sort'] = 'rating';
			$request_data['order'] = 'DESC';
			$request_data['with_payments']  = 0;
			$request_data['with_softwares'] = 0;
		}

		$casino_list = $this->model_content_casino->getCasinoList($request_data);

		$total = sizeof($casino_list);

		if ($total < (int)$request_data['limit']) {
			$request_data['limit'] = (int)$request_data['limit'] - $total;

			if ($request_data['limit'] > 0) {
				$request_data['no_software'] = 1;
				$casino_list = array_merge($casino_list, $this->model_content_casino->getCasinoList($request_data));
			}
		}

		$this->data['c_style'] = '';

		if (in_array($request_data['sort'], array('free_spins', 'bonus_amount', 'bonus_percent', 'no_deposit', 'exclusive'))) {
			$this->data['c_style'] = "bonus";
		}

		$this->data['casino_list'] = array();

		if (isset($this->request->server['HTTP_CLIENT_IP'])) {
			$ip = $this->request->server['HTTP_CLIENT_IP'];
		} elseif (isset($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			$ip = $this->request->server['HTTP_X_FORWARDED_FOR'];

			if (strpos($ip, ',') !== false) {
				$parts = explode(',', $ip);

				foreach ($parts as $part) {
					if ($part) {
						$ip  = $part;
					}
				}
			}
		} elseif (isset($this->request->server['REMOTE_ADDR'])) {
			$ip = $this->request->server['REMOTE_ADDR'];
		} else {
			$ip = false;
		}

		$contry_info = [];

		if ($ip) {
			$iso_code = $this->geoip->getCountryIsoCode($ip);

			if (!empty($iso_code)) {
				$contry_info = $this->model_localisation_country->getCountryByISOCode($iso_code);
			}
		}

		if ($casino_list) {
			$this->data['casino_bonuses'] = array();

			$casino_bonuses = $this->model_content_casino_bonus->getCasinoBonusList();

			foreach ($casino_bonuses as $c_bonus_id => $casino_bonus) {
				$casino_bonus['full_bonus'] = html_entity_decode($casino_bonus['full_bonus'], ENT_QUOTES, "UTF-8");

				$this->data['casino_bonuses'][$c_bonus_id] = $casino_bonus;
			}

			$this->data['casino_payments'] = array();

			$casino_payments = $this->model_content_casino->getCasinoPayments();

			foreach ($casino_payments as $c_payment_id => $c_payment) {
				$c_payment['link']        = $c_payment['noindex'] ? $this->url->link('content/casino/payment', 'casino_payment_id=' . $c_payment['casino_payment_id']) : '#';
				$c_payment['thumb']       = $this->model_tool_image->resize((!empty($c_payment['image']) && file_exists(DIR_IMAGE . $c_payment['image']) ? $c_payment['image'] : 'no_image.png'), 40, 40, 'sh');
				$c_payment['thumb_small'] = $this->model_tool_image->resize((!empty($c_payment['image']) && file_exists(DIR_IMAGE . $c_payment['image']) ? $c_payment['image'] : 'no_image.png'), 65, 40, 'sh');

				$this->data['casino_payments'][$c_payment_id] = $c_payment;
			}

			$this->data['software_list'] = array();

			$software_list = $this->model_content_casino->getCasinoSoftwareList();

			foreach ($software_list as $c_software_id => $c_software) {
				$c_software['thumb'] = $this->model_tool_image->resize((!empty($c_software['image']) && file_exists(DIR_IMAGE . $c_software['image']) ? $c_software['image'] : 'no_image.png'), 65, 40, 'sh');

				$this->data['software_list'][$c_software_id] = $c_software;
			}

			if (!empty($casino_bonus_type_id)) {
				$casino_bonuses = $this->model_content_casino_bonus->getCasinoBonusList(['bonus_type_ids' => [$casino_bonus_type_id]]);

				$casino_bonus_ids = [];

				foreach ($casino_bonuses as $casino_bonus) {
					$casino_bonus_ids[] = $casino_bonus['casino_bonus_id'];
				}

				foreach ($casino_list as $casino_id => $casino_item) {
					foreach ($casino_item['bonus_ids'] as $i => $bonus_id) {
						if (!in_array($bonus_id, $casino_bonus_ids)) {
							unset($casino_item['bonus_ids'][$i]);
						}
					}

					if (empty($casino_item['bonus_ids'])) {
						unset($casino_list[$casino_id]);
					} else {
						$casino_list[$casino_id] = $casino_item;
					}
				}
			}

			$key = sizeof($exclude_casino_ids);

			foreach ($casino_list as $casino_item) {
				$exclude_casino_ids[] = $casino_item['casino_id'];

				$casino_item['thumb'] = $this->model_tool_image->resize((!empty($casino_item['image']) && file_exists(DIR_IMAGE . $casino_item['image']) ? $casino_item['image'] : 'no_image.png'), 160, 80, 's');

				$casino_item['user_rating_total_label'] = sprintf($this->language->get('text_users_votes'), (!empty($casino_item['user_rating_total']) ? $casino_item['user_rating_total'] : 0));

				$casino_item['bonus_amount']  = !empty($casino_item['bonus_amount']) ? $this->currency->format($casino_item['bonus_amount'], $casino_item['currency'], 1) : '';

				if (!empty($casino_item['bonus_description'])) {
					$casino_item['bonus_description'] = html_entity_decode($casino_item['bonus_description'], ENT_QUOTES, 'UTF-8');
				}

				$casino_item['full_bonus'] = html_entity_decode($casino_item['full_bonus'], ENT_QUOTES, 'UTF-8');

				$this->data['casino_list'][$key] = $casino_item;

				$key++;
			}
		}

		$tpl_style = '';

		if ($this->data['c_style'] == 'bonus') {
			$tpl_type = '_bonus';
		}

		$this->json['item_list']  = $this->load->view('content/casino_loadmore' . $tpl_type, $this->data);
		$this->json['item_count'] = count($this->data['casino_list']);
		$this->json['start_next'] = $request_data['start'] + $request_data['limit'];
		$this->json['success']    = 1;
		$this->json['not_availible'] = $no_software;

		return $this->json;
	}

	private function casinoSoftwarePopup()
	{
		$data['software_filter'] = $this->url->link('content/casino_software/filter');
		$data['software_list'] = $this->model_content_casino->getCasinoSoftwareList();
		$placeholder = $this->model_tool_image->resize('no_image.png', 45, 25, 'sh');
		foreach ($data['software_list'] as &$software_item) {
			$software_item['thumb'] = !empty($software_item['image']) && file_exists(DIR_IMAGE . $software_item['image']) ? $this->model_tool_image->resize($software_item['image'], 45, 25, 'sh') : $placeholder;
			$software_item['link']  = $software_item['casino_software_id'] ? $this->url->link('content/casino_slot/software', 'casino_software_id=' . $software_item['casino_software_id']) : '';
		}
		unset($software_item);

		$this->json['html'] = $this->load->view('content/casino_slot_software_list_popup', $data);

		return $this->json;
	}

	private function loadMore()
	{
		if (empty($this->request->post)) {
			$this->request->post = json_decode(file_get_contents('php://input'), true);
		}

		$field_id = $this->request->post['field_id'] ?? $this->request->get['field_id'] ?? 0;
		$field = $this->model_content_field->getFields([
			'field_id' => $field_id
		]);
		$field_data = current($field);

		if ($field_data) {
			$field_data['field_value'] = json_decode($field_data['field_value'], true);

			$data = $this->load->controller('field/' . $field_data['field_type'] . '/getData', $field_data);

			if ($this->load->view_exists('field/ajax_request/' . $field_data['field_type'] . '_' . $field_data['field_view'])) {
				$this->json['result_html'] = $this->load->view('field/ajax_request/' . $field_data['field_type'] . '_' . $field_data['field_view'], $data);
			} elseif ($this->load->view_exists('field/ajax_request/' . $field_data['field_type'])) {
				$this->json['result_html'] = $this->load->view('field/ajax_request/' . $field_data['field_type'], $data);
			}
			$this->json['view_show_more'] = $data['total_casinos'] > $data['start'];
			$this->json['page'] = $data['page'];
			$this->json['start'] = $data['start'];
			$this->json['success'] = true;
		}

		// $this->json = $this->load->controller('content/casino/getList');

		return $this->json;
	}

	private function casinoUserRating()
	{
		if (isset($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			$ip =  $this->request->server['HTTP_X_FORWARDED_FOR'];

			if (strpos($ip, ',') !== false) {
				$parts = explode(',', $ip);

				foreach ($parts as $part) {
					if ($part) {
						$ip  = $part;
					}
				}
			}
		} elseif (isset($this->request->server['HTTP_CLIENT_IP'])) {
			$ip = $this->request->server['HTTP_CLIENT_IP'];
		} elseif (isset($this->request->server['REMOTE_ADDR'])) {
			$ip =  $this->request->server['REMOTE_ADDR'];
		} else {
			$ip = false;
		}

		$request_data = array(
			'casino_id' => !empty($this->request->post['cid']) ? (int) $this->request->post['cid'] : 0,
			'user_ip'   => $ip,
			'rating'    => !empty($this->request->post['rating']) ? (int) $this->request->post['rating'] : 0,
		);

		if ($request_data['casino_id'] < 0) {
			$request_data['casino_id'] = 0;
		}

		if ($request_data['rating'] < 0) {
			$request_data['rating'] = 0;
		}

		if ($request_data['rating'] > 10) {
			$request_data['rating'] = 10;
		}

		if ($request_data['casino_id']) {
			$user_rating = $this->model_content_casino->addCasinoRating($request_data);
		}

		$this->json['total']   = $user_rating['total'];
		$this->json['rating']  = round($user_rating['avg_rating'], 2);
		$this->json['success'] = 1;

		return $this->json;
	}

	private function loadTerms()
	{
		$casino_id = isset($this->request->get['casino_id']) ? intval($this->request->get['casino_id']) : 0;

		if ($casino_id) {
			$casino_info = $this->model_content_casino->getCasinoList([
				'casino_ids' => [$casino_id]
			]);

			if ($casino_info) {
				$casino_info = array_shift($casino_info);

				$tc_slink = $casino_info['tc_slink_id'] ? $casino_info['tc_slink_id'] : '';
				$tc_slink = $tc_slink ? $this->url->link('common/shortlink', 'slink_id=' . $tc_slink) : '';

				$this->json['terms_text']     = html_entity_decode($casino_info['terms_text'], ENT_QUOTES, 'UTF-8');
				$this->json['adt_terms_text'] = html_entity_decode($casino_info['additional_terms_text'], ENT_QUOTES, 'UTF-8');

				if ($tc_slink && $this->json['adt_terms_text']) {
					$this->json['adt_terms_text'] .= '<a href="' . $tc_slink . '" target="_blank" rel="nofollow noopener" class="tc-link">' . $this->language->get('text_read_full_terms') . '</a>';
				}
			}
		}
		return $this->json;
	}
	// code from content/casino *end*

	// code from content/casino_software *start*
	public function casinoSoftwareFilter()
	{
		if (empty($this->request->post)) {
			$this->request->post = json_decode(file_get_contents('php://input'), true);
		}

		$field_id = $this->request->post['field_id'] ?? 0;
		$field = $this->model_content_field->getFields([
			'field_id' => $field_id
		]);
		$field_data = current($field);

		if ($field_data) {
			$field_data['field_value'] = json_decode($field_data['field_value'], true);

			$data = $this->load->controller('field/' . $field_data['field_type'] . '/getData', $field_data);

			if ($this->load->view_exists('field/ajax_request/' . $field_data['field_type'] . '_' . $field_data['field_view'])) {
				$this->json['result_html'] = $this->load->view('field/ajax_request/' . $field_data['field_type'] . '_' . $field_data['field_view'], $data);
			} elseif ($this->load->view_exists('field/ajax_request/' . $field_data['field_type'])) {
				$this->json['result_html'] = $this->load->view('field/ajax_request/' . $field_data['field_type'], $data);
			}
			$this->json['show_more'] = $data['total'] > $data['limit'] * $data['page'];
			$this->json['page'] = $data['page'];
			$this->json['success'] = true;
		}
		return $this->json;
	}
	// code from content/casino_software *end*

	// code from content/lottery *start*
	private function lotteryAddMember()
	{
		if ($this->isLogged() && isset($this->request->post['lottery_id']) && isset($this->request->post['number'])) {
			$user_info = $this->user->getUser($this->user->getId());

			if ($user_info) {
				if ($this->model_content_lottery->getLotteryNumberByUserId($this->user->getId(), $this->request->post['lottery_id'])) {
					$this->model_content_lottery->deleteLotteryNumberByUserId($this->user->getId(), $this->request->post['lottery_id']);
				}

				$lottery_user_id = $this->model_content_lottery->addLotteryNumberByUserId($this->user->getId(), $this->request->post['lottery_id'], $this->request->post['number']);

				if ($lottery_user_id) {
					$this->json['html'] = $this->load->view('content/lottery_add_number', []);
				}
			}

			if (empty($this->json['html'])) {
				http_response_code(204);
			}
		} else {
			http_response_code(403);
		}
		return $this->json;
	}
	// code from content/lottery *end*

	// code from content/review *start*
	private function reviewAdd()
	{
		if (!$this->isLogged()) {
			http_response_code(403);
			return $this->json;
		}

		$rw_types = array('main', 'news', 'casino', 'casino_slot', 'casino_software', 'news_article', 'blog_article', 'winning');
		$lang_id  = $this->session->data['language_id'];
		$type        = isset($this->request->post['type']) && in_array($this->request->post['type'], $rw_types) ? $this->request->post['type'] : 'main';
		$type_id     = isset($this->request->post['type_id']) ? (int) $this->request->post['type_id'] : 0;
		$parent_id   = isset($this->request->post['parent_id']) ? (int) $this->request->post['parent_id'] : 0;
		$message     = isset($this->request->post['message']) ? $this->request->post['message'] : '';
		$rating      = isset($this->request->post['rating']) ? (int) $this->request->post['rating'] : 0;
		$reply_level = isset($this->request->post['r_level']) ? (int) $this->request->post['r_level'] : 0;
		$winning    = isset($this->request->post['winning']) ? (float)$this->request->post['winning'] : 0;
		$multiplier = isset($this->request->post['multiplier']) ? (float)$this->request->post['multiplier'] : 0;
		$currency_id = isset($this->request->post['currency_id']) ? (int)$this->request->post['currency_id'] : 0;
		$slot_name = isset($this->request->post['slot_name']) ? $this->request->post['slot_name'] : 0;
		$casino_id     = isset($this->request->post['casino_id']) ? (int) $this->request->post['casino_id'] : 0;
		$casino_name = isset($this->request->post['casino_name']) ? $this->request->post['casino_name'] : 0;
		$advantages = isset($this->request->post['advantages']) ? $this->request->post['advantages'] : '';
		$disadvantages = isset($this->request->post['disadvantages']) ? $this->request->post['disadvantages'] : '';

		if (!empty($winning)) {
			$winning = str_replace(',', '.', $winning);
		}

		if (!empty($multiplier)) {
			$multiplier = str_replace(',', '.', $multiplier);
		}

		if ($type == 'casino_slot') {
			if (!$parent_id && ($rating > 10 || $rating <= 0)) {
				$this->json['error']['rating'] = $this->language->get('error_review_rating');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $advantages)) > 500) {
				$this->json['error']['advantages'] = $this->language->get('error_max_message_length');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $disadvantages)) > 500) {
				$this->json['error']['disadvantages'] = $this->language->get('error_max_message_length');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $message)) > 500) {
				$this->json['error']['message'] = $this->language->get('error_max_message_length');
			}

			if (!$parent_id && ($multiplier < 0 || $multiplier > 1000000  || $multiplier === 0)) {
				$this->json['error']['multiplier'] = $this->language->get('error_review_multiplier');
			}

			if (!$parent_id && ($winning < 0 || $winning > 1000000  || $winning === 0)) {
				$this->json['error']['winning'] = $this->language->get('error_review_winning');
			}

			if (empty($advantages) && empty($disadvantages) && empty($message)) {
				if (empty($parent_id)) {
					$this->json['error']['message'] = $this->language->get('error_message');
					$this->json['error']['advantages']    = $this->language->get('error_adv_or_dis');
					$this->json['error']['disadvantages'] = $this->language->get('error_adv_or_dis');
				} elseif (empty($message)) {
					$this->json['error']['message'] = $this->language->get('error_message');
				}
			}
		}

		if ($type == 'casino' || $type == 'casino_software') {
			if (!$parent_id && ($rating > 10 || $rating <= 0)) {
				$this->json['error']['rating'] = $this->language->get('error_review_rating');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $advantages)) > 500) {
				$this->json['error']['advantages'] = $this->language->get('error_max_message_length');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $disadvantages)) > 500) {
				$this->json['error']['disadvantages'] = $this->language->get('error_max_message_length');
			}

			if ($type != 'winning' && empty($advantages) && empty($disadvantages)) {
				if (empty($parent_id)) {
					$this->json['error']['advantages']    = $this->language->get('error_adv_or_dis');
					$this->json['error']['disadvantages'] = $this->language->get('error_adv_or_dis');
				} elseif (empty($message)) {
					$this->json['error']['message'] = $this->language->get('error_message');
				}
			}
		}

		if ($type == 'main' || $type == 'news_article' || $type == 'blog_article') {
			if (empty($message)) {
				$this->json['error']['message'] = $this->language->get('error_message');
			}

			if ($type == 'news_article' || $type == 'blog_article') {
				if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $message)) > 500) {
					$this->json['error']['message'] = $this->language->get('error_max_message_length');
				}
			}
		}

		if ($type == 'winning') {
			if (empty($winning)) {
				$this->json['error']['winning'] = $this->language->get('error_review_winning');
			}

			if (empty($multiplier)) {
				$this->json['error']['multiplier'] = $this->language->get('error_review_multiplier');
			}

			if (!empty($winning) && (!is_numeric($winning) || $winning < 0)) {
				$this->json['error']['winning'] = $this->language->get('error_only_numbers');
			}

			if (!empty($multiplier) && (!is_numeric($multiplier) || $multiplier < 0)) {
				$this->json['error']['multiplier'] = $this->language->get('error_only_numbers');
			}

			if (!$type_id && !$slot_name) {
				$this->json['error']['type_id'] = $this->language->get('error_type_id');
			}

			if (!$casino_id && !$casino_name) {
				$this->json['error']['casino_id'] = $this->language->get('error_casino_id');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $advantages)) > 500) {
				$this->json['error']['advantages'] = $this->language->get('error_max_message_length');
			}

			if (utf8_strlen(str_replace(['\n', '\r'], ['', ''], $disadvantages)) > 500) {
				$this->json['error']['disadvantages'] = $this->language->get('error_max_message_length');
			}
		} else {
			if (empty($type_id)) {
				$this->json['error']['type_id'] = $this->language->get('error_review_type_id');
			}
		}

		$user_id = $this->user->getId();
		$attachments = [];
		$files = [];

		if (!empty($this->request->files)) {
			$max_upload_size = $this->config->get('config_upload_max_filesize');

			foreach ($this->request->files as $key => $request_files) {
				if (isset($request_files['error']) && $request_files['error'] == 4) {
					continue;
				}
				if (is_array($request_files['name'])) {
					foreach (array_keys($request_files['name']) as $index) {
						$files[] = [
							'key'      => $key,
							'name'     => $request_files['name'][$index],
							'type'     => $request_files['type'][$index],
							'tmp_name' => $request_files['tmp_name'][$index],
							'error'    => $request_files['error'][$index],
							'size'     => $request_files['size'][$index]
						];
					}
				} else {
					$files[] = [
						'key'      => $key,
						'name'     => $request_files['name'],
						'type'     => $request_files['type'],
						'tmp_name' => $request_files['tmp_name'],
						'error'    => $request_files['error'],
						'size'     => $request_files['size']
					];
				}
			}

			foreach ($files as $file) {
				$f_index = $file['key'];

				if ($file['error'] > 0) {
					$this->json['error'][$f_index]['error_upload_8'] = $this->language->get('error_upload_8');
				} else {
					$alw_upload = true;
					$alw_types  = array('image/bmp', 'image/jpeg', 'image/png', 'image/gif');

					if (!in_array($file['type'], $alw_types)) {
						$this->json['error'][$f_index]['error_upload_8'] = $this->language->get('error_upload_8');

						$alw_upload = false;
					}

					if ($file['size'] > $max_upload_size) {
						$this->json['error'][$f_index]['error_upload_9'] = $this->language->get('error_upload_9');

						$alw_upload = false;
					}

					if ($alw_upload) {
						if (!is_dir(DIR_IMAGE . 'user_uploads/' . $user_id . '/')) {
							mkdir(DIR_IMAGE . 'user_uploads/' . $user_id, 0777, true);
						}

						$qualities = array(90, 80, 70, 60, 50, 40, 30);

						if ($file['type'] == 'image/jpeg') {
							$attachment = imagecreatefromjpeg($file['tmp_name']);
						} elseif ($file['type'] == 'image/png') {
							$attachment = imagecreatefrompng($file['tmp_name']);
						} elseif ($file['type'] == 'image/bmp') {
							$attachment = imagecreatefrombmp($file['tmp_name']);
						} elseif ($file['type'] == 'image/gif') {
							$attachment = imagecreatefromgif($file['tmp_name']);
						}

						$image_width  = imagesx($attachment);
						$image_height = imagesy($attachment);
						$image_width  = $image_width > 1200 ? 1200 : $image_width;
						$image_height = $image_height > 800 ? 800 : $image_height;

						$file_name = strtolower(time() . '-' . $f_index . '.jpg');
						$success   = false;
						$file_path = 'user_uploads/' . $user_id . '/' . $file_name;

						foreach ($qualities as $quality) {
							$attachment_bg = imagecreatetruecolor($image_width, $image_height);

							imagefill($attachment_bg, 0, 0, imagecolorallocate($attachment_bg, 255, 255, 255));
							imagealphablending($attachment_bg, TRUE);
							imagecopyresampled($attachment_bg, $attachment, 0, 0, 0, 0, $image_width, $image_height, $image_width, $image_height);
							imagejpeg($attachment_bg, DIR_IMAGE . $file_path, $quality);
							imagedestroy($attachment_bg);

							if (filesize(DIR_IMAGE . $file_path) < $max_upload_size) {
								imagedestroy($attachment);
								$success = true;
								break;
							} else {
								if (file_exists(DIR_IMAGE . $file_path)) {
									unlink(DIR_IMAGE . $file_path);
								}
							}
						}

						if ($success) {
							$attachments[] = $file_path;
						} else {
							$this->json['error'][$f_index]['error_upload_9'] = $this->language->get('error_upload_9');
						}
					}
				}
			}
		} else {
			if ($type == 'winning') {
				$this->json['error']['error'][] = $this->language->get('error_upload_4');
			}
		}

		if (empty($this->json['error'])) {
			$review_data = array(
				'user_id'   => $user_id,
				'username'  => $this->user->getUserName(),
				'parent_id' => $parent_id,
				'message'   => $message,
				'type'      => $type,
				'type_id'   => $type_id,
				'rating'    => $rating,
				'likes'     => 0,
				'dislikes'  => 0,
				'helpful'   => '',
				'status'    => 0,
				'date'      => date($this->language->get('date_format_short')),
				'meta_info' => array(),
				'isLogged'  => 1,
			);

			if ($type == 'casino_slot' || $type == 'winning') {
				$review_data['meta_info']['currency_id']   = $currency_id;
				$review_data['meta_info']['winning']       = $winning;
				$review_data['meta_info']['multiplier']    = $multiplier;
			}

			if ($type == 'casino_slot' || $type == 'casino' || $type == 'winning' || $type == 'casino_software') {
				$review_data['meta_info']['advantages']    = $advantages;
				$review_data['meta_info']['disadvantages'] = $disadvantages;
			}

			if ($type == 'winning') {
				if ($slot_name) {
					$review_data['meta_info']['slot_name']  = $slot_name;
				}

				if ($casino_id) {
					$review_data['meta_info']['casino_id']  = $casino_id;
				}

				if ($casino_name) {
					$review_data['meta_info']['casino_name']  = $casino_name;
				}
			}

			if ($attachments) {
				$review_data['meta_info']['images'] = $attachments;
			}

			unset($review_data['g-recaptcha-response']);

			$review_data['review_id'] = $this->model_content_review->addReview($review_data);

			$review_data['message']       = nl2br($review_data['message']);
			$review_data['advantages']    = nl2br($review_data['meta_info']['advantages']);
			$review_data['disadvantages'] = nl2br($review_data['meta_info']['disadvantages']);

			$data['user_info'] = $this->model_account_user->getUser($this->user->getId());

			if ($data['user_info']) {
				$user_level = $this->user->getUserLevel($data['user_info']['lvl_xp']);
			} else {
				$user_level = array();
			}

			if ($data['user_info']['image']) {
				$review_data['avatar'] = $this->model_tool_image->resize($this->user->getAvatar($data['user_info']['image'], $data['user_info']['user_id']), 64, 64, 'sh');
			} else {
				$user_levels = $this->cache->get('user_levels');

				if ($user_levels) {
					if (!empty($user_level['level']) && is_file(DIR_IMAGE . $user_levels[$user_level['level']]['image']) && file_exists(DIR_IMAGE . $user_levels[$user_level['level']]['image'])) {
						$review_data['avatar'] = $this->model_tool_image->resize($user_levels[$user_level['level']]['image'], 64, 64, 'sh');
					} else {
						$review_data['avatar'] = $this->model_tool_image->resize('no_image.png', 64, 64, 'sh');
					}
				} else {
					$review_data['avatar'] = $this->model_tool_image->resize('no_image.png', 64, 64, 'sh');
				}
			}

			$review_data['level']      = !empty($user_level['level']) ? $user_level['level'] : false;
			$review_data['level_name'] = !empty($user_level['level_name']) ? $user_level['level_name'] : false;

			if ($attachments) {
				foreach ($attachments as $image_fname) {
					if (file_exists(DIR_IMAGE . 'user_uploads/' . $review_data['user_id'] . '/' . $image_fname)) {
						$review_data['images'][] = array(
							'thumb' => $this->model_tool_image->resize('user_uploads/' . $review_data['user_id'] . '/' . $image_fname, 160, 160, 'sh'),
							'image' => 'image/user_uploads/' . $review_data['user_id'] . '/' . $image_fname,
						);
					}
				}
			}

			$this->json['status']    = 0;
			$this->json['rating']    = $rating;
			$this->json['parent_id'] = $parent_id;
			$this->json['success']   = $this->language->get('text_review_success_check');

			if (!empty($review_data['meta_info']['winning'])) {
				$review_data['winning'] = $review_data['meta_info']['winning'];
			}

			if (!empty($review_data['meta_info']['multiplier'])) {
				$review_data['multiplier'] = $review_data['meta_info']['multiplier'];
			}

			if ($type == 'winning') {
				$this->json['success'] = $this->language->get('text_review_success_check');
			} else {
				if ($parent_id) {
					$this->json['review_html'] = $this->load->view('template/review_reply_item', array('reply_item' => $review_data, 'reply_level' => $reply_level + 1, 'c_user_id' => $this->user->getId(), 'c_user_group' => $this->user->getUserGroup()));
				} else {
					if ($type == 'casino' || $type == 'casino_slot' || $type == 'casino_software') {
						$this->json['review_html'] = $this->load->view('template/review_item_adv', array('review_item' => $review_data, 'c_user_id' => $this->user->getId(), 'c_user_group' => $this->user->getUserGroup()));
					} else {
						$this->json['review_html'] = $this->load->view('template/review_item', array('review_item' => $review_data, 'c_user_id' => $this->user->getId(), 'c_user_group' => $this->user->getUserGroup()));
					}
				}
			}
		}

		return $this->json;
	}
	// code from content/review *end*







	// like blog or news *start*
	private function addLikeDislike()
	{
		if (!$this->isLogged()) {
			http_response_code(403);
			return $this->json;
		}

		$content_type = (string) ($this->request->post['content_type'] ?? '');
		$content_type_id = (int) ($this->request->post['content_type_id'] ?? 0);
		$like = (int) ($this->request->post['like'] ?? 0);
		$like = $like ? ($like > 0 ? 1 : -1) : 0;
		$this->json['post'] =  $this->request->post;
		if (!$content_type_id || !$like) {
			http_response_code(403);
			return $this->json;
		}

		switch ($content_type) {
				// case 'blog':
				// $like_info = $this->model_news_article->addBlogLike(array('article_id' => $content_type_id, 'like' => $like));
				// if (!empty($like_info)) {
				// 	$this->json['likes']    = $like_info['likes'];
				// 	$this->json['dislikes'] = $like_info['dislikes'];
				// }

				// $this->session->data['likes'][$postType][] = $content_type_id;
				// $this->json['message'] = $this->language->get('success_new_rate_was_added');
				// 	break;
				// case 'news':
				// $like_info = $this->model_news_article->addNewsLike(array('article_id' => $content_type_id, 'like' => $like));
				// if (!empty($like_info)) {
				// 	$this->json['likes']    = $like_info['likes'];
				// 	$this->json['dislikes'] = $like_info['dislikes'];
				// }

				// $this->session->data['likes'][$postType][] = $content_type_id;
				// $this->json['message'] = $this->language->get('success_new_rate_was_added');
				// 	break;
			case 'review':
				$like_info = $this->model_content_review->addReviewLike([
					'review_id' => $content_type_id,
					'like' => $like
				]);

				if (isset($like_info['total'])) {
					$this->json['likes']    = $like_info['likes'];
					$this->json['dislikes'] = $like_info['dislikes'];

					$this->json['success'] = $this->language->get('success_new_rate_was_added');
				} else {
					$this->json['error'] = $this->language->get('error_review_already');
				}

				// if ($content_type_id && $like) {
				// 	$likes = $this->model_content_review->getLikesAuthorByReviewId($content_type_id, 7);

				// 	$this->json['like_authors'] = [];

				// 	foreach($likes as $like){
				// 		if(!empty($like['user_id'])){
				// 			$this->json['like_authors'][] = $this->model_account_user->getUser($like['user_id']);
				// 		}
				// 	}

				// 	$total_authors = $this->model_content_review->getTotalLikeAuthorsByReviewId($review['review_id']);

				// 	if($total_authors > 7){
				// 		$this->json['like_authors_more'] = $total_authors - 7;
				// 	}else{
				// 		$this->json['like_authors_more'] = 0;
				// 	}
				// }
				break;

			default:
				http_response_code(403);
				break;
		}

		return $this->json;
	}
	// like blog or news *end*

	// code from content/blog *start*
	private function blogAction(): array
	{
		if (!$this->isLogged()) {
			http_response_code(403);
			return $this->json;
		}

		$blog_title    = isset($this->request->post['blog_title']) ? $this->request->post['blog_title'] : '';
		$blog_contents = isset($this->request->post['blog_contents']) ? $this->request->post['blog_contents'] : '';

		if (empty($blog_title)) {
			$this->json['error']['blog_title'] = $this->language->get('error_blog_title');
		}

		if (empty($blog_contents)) {
			$this->json['error']['blog_contents'] = $this->language->get('error_blog_contents');
		}

		$user_id = $this->user->getId();
		$attachments = [];
		$files = [];

		if (!empty($this->request->files)) {
			$max_upload_size = $this->config->get('config_upload_max_filesize');

			foreach ($this->request->files as $key => $request_files) {
				if (isset($request_files['error']) && $request_files['error'] == 4) {
					continue;
				}
				if (is_array($request_files['name'])) {
					foreach (array_keys($request_files['name']) as $index) {
						$files[] = [
							'key'      => $key,
							'name'     => $request_files['name'][$index],
							'type'     => $request_files['type'][$index],
							'tmp_name' => $request_files['tmp_name'][$index],
							'error'    => $request_files['error'][$index],
							'size'     => $request_files['size'][$index]
						];
					}
				} else {
					$files[] = [
						'key'      => $key,
						'name'     => $request_files['name'],
						'type'     => $request_files['type'],
						'tmp_name' => $request_files['tmp_name'],
						'error'    => $request_files['error'],
						'size'     => $request_files['size']
					];
				}
			}

			foreach ($files as $file) {
				$f_index = $file['key'];


				if ($file['error'] > 0) {
					$this->json['error'][$f_index]['error_upload_8'] = $this->language->get('error_upload_8');
				} else {
					$alw_upload = true;
					$alw_types  = array('image/bmp', 'image/jpeg', 'image/png', 'image/gif');

					if (!in_array($file['type'], $alw_types)) {
						$this->json['error'][$f_index]['error_upload_8'] = $this->language->get('error_upload_8');

						$alw_upload = false;
					}

					if ($file['size'] > $max_upload_size) {
						$this->json['error'][$f_index]['error_upload_9'] = $this->language->get('error_upload_9') . ' ' . $max_upload_size;

						$alw_upload = false;
					}

					if ($alw_upload) {
						if (!is_dir(DIR_IMAGE . 'user_uploads/' . $user_id . '/')) {
							mkdir(DIR_IMAGE . 'user_uploads/' . $user_id, 0777, true);
						}

						$qualities = array(90, 80, 70, 60, 50, 40, 30);

						if ($file['type'] == 'image/jpeg') {
							$attachment = imagecreatefromjpeg($file['tmp_name']);
						} elseif ($file['type'] == 'image/png') {
							$attachment = imagecreatefrompng($file['tmp_name']);
						} elseif ($file['type'] == 'image/bmp') {
							$attachment = imagecreatefrombmp($file['tmp_name']);
						} elseif ($file['type'] == 'image/gif') {
							$attachment = imagecreatefromgif($file['tmp_name']);
						}

						$image_width  = imagesx($attachment);
						$image_height = imagesy($attachment);
						$image_width  = $image_width > 1200 ? 1200 : $image_width;
						$image_height = $image_height > 800 ? 800 : $image_height;

						$file_name = strtolower(time() . '-' . $f_index . '.jpg');
						$success   = false;
						$file_path = 'user_uploads/' . $user_id . '/' . $file_name;

						foreach ($qualities as $quality) {
							$attachment_bg = imagecreatetruecolor($image_width, $image_height);

							imagefill($attachment_bg, 0, 0, imagecolorallocate($attachment_bg, 255, 255, 255));
							imagealphablending($attachment_bg, TRUE);
							imagecopyresampled($attachment_bg, $attachment, 0, 0, 0, 0, $image_width, $image_height, $image_width, $image_height);
							imagejpeg($attachment_bg, DIR_IMAGE . $file_path, $quality);
							imagedestroy($attachment_bg);

							if (filesize(DIR_IMAGE . $file_path) < $max_upload_size) {
								imagedestroy($attachment);
								$success = true;
								break;
							} else {
								if (file_exists(DIR_IMAGE . $file_path)) {
									unlink(DIR_IMAGE . $file_path);
								}
							}
						}

						if ($success) {
							$attachments[] = $file_path;
						} else {
							$this->json['error'][$f_index]['error_upload_9'] = $this->language->get('error_upload_9');
						}
					}
				}
			}
		}

		if (empty($this->json['error'])) {
			$c_lang_id = $this->session->data['language_id'];

			$blog_data = array(
				'article_description'   => array($c_lang_id => array(
					'title'            => $blog_title,
					'description'      => $blog_contents,
					'meta_title'       => $blog_title,
					'meta_h1'          => $blog_title,
					'meta_description' => '',
					'image'            => $attachments ? $attachments[0] : '',
					'image_full'       => $attachments ? $attachments[0] : ''
				)),
				'article_category'      => array(1),
				'main_blog_category_id' => 1,
				'keywords'              => array($c_lang_id => $this->url->translit($blog_title)),
			);

			$blog_id = $this->model_blog_article->addArticle($blog_data);

			if (empty($main_blog_category)) {
				$main_blog_category = $this->model_blog_category->getCategoryByRole('main');

				if ($main_blog_category) {
					foreach ($main_blog_category as $key => $value) {
						if (!$value) {
							continue;
						}

						$value = is_string($value) ? json_decode($value, true) : $value;

						if (is_string($main_blog_category[$key]) && is_array($value)) {
							$main_blog_category[$key] = $value;
						}
					}
				}
			}

			$this->json['success'] = $blog_id
				? $this->load->view('content/success', [
					'link' => !empty($main_blog_category['blog_category_id']) ? $this->url->link('content/blog/category', 'blog_category_id=' . $main_blog_category['blog_category_id']) : '',
					'message' => $this->language->get('text_blog_success_check')
				])
				: $this->language->get('text_blog_error');
		}

		return $this->json;
	}
	// code from content/blog *end*

	// code from extension/module *start*
	private function loadBanner()
	{
		$country_info = $this->load->controller('extension/module/welcome_banner/getCountryInfo');
		
		if ($country_info) {
			$modules = $this->model_extension_module->getModulesByCode('welcome_banner');
			
			foreach ($modules as $module) {
				if (
					!empty($module['status'])
					&& isset($module['type'])
					&& $module['type'] == 'popup'
					&& !empty($module['country_ids'])
					&& in_array($country_info['country_id'], $module['country_ids'])
				) {
					$this->json['html'] = $this->load->controller('extension/module/welcome_banner/getView', $module);
					break;
				}
			}
		}

		return $this->json;
	}
	// code from extension/module *end*

	// code from controller/account *start*
	private function checkout() {
		if (!$this->isLogged()) {
			$this->response->redirect($this->url->link('common/login'));
		}
		if (!$this->isLogged()) {
			http_response_code(403);
			return $this->json;
		}

		$cart     = $this->user->getUserCart();
		$user_lvl = $this->user->getUserLevel();
		$user_pts = $this->user->getUserSJPoints();

		if ($cart['total_items'] > 0) {
			$product_list = $this->model_content_shop->getProductList();

			if ($cart['total_price'] > $user_pts) {
				$this->json['error']['error'][] = $this->language->get('error_dont_have_enough_points');
			}

			foreach ($cart['items'] as $pid => $cart_item) {
				if (isset($product_list[$pid])) {
					if ($product_list[$pid]['level'] > $user_lvl) {
						$this->json['error']['checkout[' . $pid . '][price]'] = $this->language->get('error_dont_have_enough_level');
					}

					$cart['items'][$pid]['level'] = $product_list[$pid]['level'];
				} else {
					$this->json['error']['checkout[' . $pid . '][pid]'] = $this->language->get('error_item_do_not_exists');
				}
			}
		} else {
			$this->json['error']['error'][] = $this->language->get('text_cart_empty');
		}

		if (empty($this->json)) {
			$order_id = $this->model_content_shop->addOrder($cart);
			
			if($order_id){
				if(!empty($this->request->cookie['sj_cart'])){
					setcookie('sj_cart', false, time(), '/');
				}
				
				$this->json['success'] = $this->language->get('text_order_received');
			} else {
				$this->json['error']['warning'] = $this->language->get('error_warning');
			}
		}

		return $this->json;
	}
	// code from controller/account *end*
	

	// readActivity *start*
	private function readActivity() {
		if (!$this->isLogged()) {
			$this->response->redirect($this->url->link('common/login'));
		}
		if (!$this->isLogged()) {
			http_response_code(403);
			return $this->json;
		}

		$activity_ids = $this->request->post['activity_ids'] ?? [];
		$this->activity->readActivity($activity_ids);

		return $this->json;
	}
	// readActivity *end*

	private function login()
	{
		$this->json['html'] = $this->load->controller('common/login');
		return $this->json;
	}
	private function register()
	{
		$this->json['html'] = $this->load->controller('common/register');
		return $this->json;
	}

	private function isAjax(): bool
	{
		return $this->request->isAjax() ? true : http_response_code(403);
	}
	private function isLogged(): bool
	{
		return $this->user->isLogged() ? true : http_response_code(403);
	}
}
