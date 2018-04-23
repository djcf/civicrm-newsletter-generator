<?php
if (!defined('DRUPAL_ROOT')) {
	define('DRUPAL_ROOT', '/buildkit/build/CiviCRM');
}
if (!defined('CIVI_ROOT')) {
	define('CIVI_ROOT', DRUPAL_ROOT . '/sites/all/modules/civicrm');
}
if (!defined('API_PATH')) {
	define('API_PATH', CIVI_ROOT . '/api/api.php');
}
/* Bootstrap into the Civi environment */
include_once API_PATH;
include_once CIVI_ROOT . '/civicrm.config.php';

function ccrm_get_image($nid, $style) {
	if (!$nid) return null;

	$node = node_load($nid);
	$wrapper = entity_metadata_wrapper('node', $node);
	$image_uri = $wrapper->field_image->value();

	$derivative_uri = image_style_path($style, $image_uri);
	$success        = file_exists($derivative_uri) || image_style_create_derivative(image_style_load($style), $image_uri, $derivative_uri);
	return file_create_url($derivative_uri);
}
function ccrm_prepare_mailing($vars) {
	   //  'subject' => $_POST['lead']['subject'],
    // 'lead_title' => $_POST['lead']['title'],
    // 'lead_id' => $_POST['lead']['id'],
    // 'lead_caption' => $_POST['lead']['caption'],
    // 'articles' => $_POST['articles']

	global $user;
	$mailing_components = ccrm_get_mailing_components();
	$vars['lead_image'] = ccrm_get_image($lead_id, "picturebox");
	$vars['lead_url']   = url("node/$lead_id", array('absolute' => true));
	foreach($vars['articles'] as &$article) {
		$article['url']   = url("node/{$article['id']}", array('absolute' => true));
		$article['image'] = ccrm_get_image($article['id'], "thumbnail");
	}
	extract($vars);
	$options = array(
		'header_id' => $mailing_components['AutoNewsletter Header']['id'],
		'footer_id' => $mailing_components['AutoNewsletter Footer']['id'],
		'name'      => $mailing_name,
		'subject'   => $mailing_subject,
		'body_text' => get_body_text($vars, $mailing_components),
		'body_html' => get_body_html($vars, $mailing_components),
		'created_id'=> civicrm_api3('UFMatch', 'getvalue', array(
				'return' => "contact_id",
				'uf_id' => $user->uid,
			)),
	);
	print_r($options);
	die();
}

function ccrm_component_format($vars, $subject) {
	foreach($vars as $search=>$replace) {
		if (!is_array($search)) {
			$subject = str_replace("{" . $search . "}", $replace, $subject);
		}
	}
	return $subject;
}

function get_body_html($vars, $components) {
	$r = array();
	$r[] = ccrm_component_format($vars, $components['AutoNewsletter Lead Box']);
	foreach(array_chunk($articles, 2) as $article_pair) {
		$i = 1;
		$template_array = array();
		while(!empty($article_pair)) {
			$article = array_shift($article_pair);
			foreach($article as $article_field => $value) {
				$template_array[$article_field . "_$i"] = $value;
			}
		}
		$r[] = ccrm_component_format($template_array, $components['AutoNewsletter Article Box']);
	}
	return implode("$r", "\n\n\n<!--*******************************-->\n\n\n");
}

function get_body_text($vars, $components) {
	extract($vars);
	$r = array("COMMONSPACE DAILY NEWSLETTER");
	$r[] = "";
	$r[] = "";
	$r[] = "!($lead_image)";
	$r[] = "";
	$r[] = $lead_title;
	$r[] = str_repeat('=', strlen($lead_title));
	$r[] = $lead_caption;
	$r[] = $lead_url;
	$r[] = "";
	foreach($articles as $nid => $article) {
		$r[] = "!({$article['image']})";
		$r[] = $article['title'];
		$r[] = str_repeat('-', strlen($article['title']));
		$r[] = $article['caption'];
		$r[] = $article['url'];
		$r[] = "";
	}
	return implode($r, "\n");
}

function ccrm_get_mailing_components() {
	$r = array();
	$results = civicrm_api3('MailingComponent', 'get', array(
		'name' => array('LIKE' => "AutoNews%"),
	));
	foreach ($results['values'] as $result) {
		$r[$result['name']] = $result;
	}
	return $r;
}

include("util-text.php");