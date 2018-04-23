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
	//$wrapper = entity_metadata_wrapper('node', $node);
	$image_uri = $node->field_image['und'][0]['uri']; //$wrapper->field_image->value();

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

	$vars['lead_image'] = ccrm_get_image($vars['lead_id'], "picturebox");
	$vars['lead_url']   = url("node/{$vars['lead_id']}", array('absolute' => true));
	foreach($vars['articles'] as &$article) {
		$article['url']   = url("node/{$article['id']}", array('absolute' => true));
		$article['image'] = ccrm_get_image($article['id'], "thumbnail");
	}
	$header_id = civicrm_api3('MailingComponent', 'getvalue', array(
				'return' => "id",
				'name' => "AutoNewsletter Header",
			));
	$footer_id = civicrm_api3('MailingComponent', 'getvalue', array(
				'return' => "id",
				'name' => "AutoNewsletter Footer",
			));
	$created_id = civicrm_api3('UFMatch', 'getvalue', array(
				'return' => "contact_id",
				'uf_id' => $user->uid,
			));
	extract($vars);
	$options = array(
		'header_id' => $header_id,
		'footer_id' => $footer_id,
		'name'      => $mailing_name,
		'subject'   => $mailing_subject,
		'body_text' => get_body_text($vars),
		'body_html' => get_body_html($vars),
		'created_id'=> $created_id,
	);

	$results = civicrm_api3('Mailing', 'create', $options);

	return $results['id'];
}

function ccrm_component_format($vars, $template) {
	$subject = file_get_contents(__DIR__ . "/templates/AutoNewsletter $template.tpl");
	echo __DIR__ . "/templates/AutoNewsletter $template.tpl";
	foreach($vars as $search=>$replace) {
		if (!is_array($search)) {
			if(!$replace) {
				$replace = "INSERT_ARTICLE_$replace_HERE";
			}
			$subject = str_replace("{" . $search . "}", mb_convert_encoding($replace,"HTML-ENTITIES","UTF-8"), $subject);
		}
	}
	return $subject;
}

function get_body_html($vars) {
	$spacer = file_get_contents(__DIR__ . "/templates/spacer.tpl");
	$r = array($spacer);
	if ($vars['lead_id']) {
		$r[] = ccrm_component_format($vars, 'Lead Box');
		$r[] = $spacer;
	}
	foreach(array_chunk($vars['articles'], 2) as $article_pair) {
		$i = 1;
		$template_array = array();
		$size = sizeof($article_pair);
		while(!empty($article_pair)) {
			$article = array_shift($article_pair);
			foreach($article as $article_field => $value) {
				$template_array["article_{$article_field}_$i"] = $value;
			}
			$i++;
		}
		$r[] = ccrm_component_format($template_array, "Article Box-$size");
		$r[] = $spacer;
	}
	return implode($r, "\n\n\n<!--*******************************-->\n\n\n");
}

function get_body_text($vars) {
	extract($vars);
	$r = array();
//	$r[] = "";
//	$r[] = "!($lead_image)";
	$r[] = $lead_title;
	$r[] = str_repeat('=', strlen($lead_title));
	$r[] = $lead_caption;
	$r[] = $lead_url;
	$r[] = "";
	foreach($articles as $nid => $article) {
//		$r[] = "!({$article['image']})";
		$r[] = $article['title'];
		$r[] = str_repeat('-', strlen($article['title']));
		$r[] = $article['caption'];
		$r[] = $article['url'];
		$r[] = "";
	}
	return implode($r, "\n");
}

// function ccrm_get_mailing_components() {
// 	$r = array();
// 	$results = civicrm_api3('MailingComponent', 'get', array(
// 		'name' => array('LIKE' => "AutoNews%"),
// 	));
// 	foreach ($results['values'] as $result) {
// 		$r[$result['name']] = $result;
// 	}
// 	return $r;
// }

// function ccrm_refresh_components($component_names = array("Article Box", "Footer", "Header", "Lead Box")) {
// 	$mailing_components = ccrm_get_mailing_components();
// 	foreach($component_names as $name) {
// 		$component_name = "AutoNews $name";
// 		$template_contents = file_get_contents(__DIR__ . "/templates/$component_name.tpl");
// 		if(array_key_exists($component_name, $mailing_components)) {
// 			$result = civicrm_api3('MailingComponent', 'delete', array(
// 				'id' => $mailing_components[$component_name]['id'],
// 			));
// 		}

// 	}
// }