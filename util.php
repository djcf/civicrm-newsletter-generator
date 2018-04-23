<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function ccrm_newsletter_get_news($simple=true) {
   $defaults = array(
       'max' => 10,
       'pager' => null,
       'type' => 'article',
       'entity' => 'node',
       'status' => NODE_PUBLISHED,
       'order' => 'DESC',
   );

   $query = new EntityFieldQuery();
   $query =  $query->entityCondition('entity_type', $defaults['entity'])
           ->entityCondition('bundle', $defaults['type'])
           ->propertyCondition('status', $defaults['status'])
           ->fieldCondition('field_image', 'fid', 'NULL', '!=');

   $query->propertyOrderBy('created', $defaults['order']);
   $query->range(0, $defaults['max']);
   $entities = $query->execute();

   //return array();
   $news_items = node_load_multiple(array_keys($entities[$defaults['entity']]));
   // $news_items_nids = array_keys($entities[$defaults['entity']]);
   // $news_items = entity_load($defaults['entity'], $news_items_nids);
   $r = array();
   foreach($news_items as $nid => $node) {
    if ($simple) {
     $r[$nid] = $node->title;
    } else {
     $r[$nid] = array(
        "title" => $node->title,
        "teaser" => ccrm_newsletter_get_node_summary($node)
     );
    }
   }
   return $r;
}

function ccrm_newsletter_get_node_summary($node, $size=null) {
  if (isset($node->body_value)) {
    return str_replace('"', "'", strip_tags($node->body_summary ?
      $node->body_summary :
      text_summary($node->body_value, null, $size)
    ));
  }
  return str_replace('"', "'", strip_tags($node->body['und'][0]['summary'] ?
    $node->body['und'][0]['summary'] :
    text_summary($node->body['und'][0]['value'], null, $size)
  ));
}

function ccrm_newsletter_menu() {
  $items = array();
  $items['create/newsletters'] = array(
      'title' => 'Generate Newsletter',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('ccrm_newsletter_manage'),
      'access arguments' => array('access administration pages'),
  );
  $items['newsletters-autocomplete-engine'] = array(
    'page callback' => 'ccrm_newsletter_autocomplete',
    'access arguments' => array('access content'),
    'type' => MENU_CALLBACK,
  );
  $items['newsletters-fetch-data'] = array(
    'page callback' => 'ccrm_newsletter_fetch_data',
    'access arguments' => array('access content'),
    'type' => MENU_CALLBACK,
  );
  return $items;
}

function ccrm_newsletter_fetch_data($text) {
  $results = array();
 
  $query = db_select('node', 'n');
  $query->leftJoin('field_data_body', 'd', '(d.entity_id = n.nid AND d.entity_type = :node)', array(':node' => 'node'));
  $query
      ->condition('n.nid', $text, '=')
      ->fields('n', array('title', 'nid'))
      ->fields('d', array('body_value', 'body_summary'))
      ->orderBy('created', 'ASC');
  $articles = $query->execute();
  foreach ($articles as $row) {
    $results[$row->nid] = array(
      "title" => check_plain($row->title),
      "body"  => check_plain(ccrm_newsletter_get_node_summary($row))
    );
  }
 
  drupal_json_output(array_shift($results));
}

function ccrm_newsletter_autocomplete($text) {
  $results = array();
 
  $query = db_select('node', 'n');
  $query
      ->condition('n.title', '%' . db_like($text) . '%', 'LIKE')
      ->fields('n', array('title', 'nid'))
      ->range(0, 15)
      ->orderBy('created', 'ASC');
  $articles = $query->execute();
 
  foreach ($articles as $row) {
    $results[$row->nid] = check_plain($row->title);
  }
 
  drupal_json_output($results);
}

function ccrm_newsletter_manage() {
  //$db_result = db_query( "select nid,title from node");
  // create array and add one element called data
  //$db_result = ccrm_newsletter_get_news();

  # the drupal checkboxes form field definition
  $articles = ccrm_newsletter_get_news();
  $form['lead']['subject'] = array(
    '#type' => 'textfield',
    '#title' => 'Mailing Subject'
  );
  $form['lead']['name'] = array(
    '#type' => 'textfield',
    '#title' => 'Mailing Name (for staff)',
    '#default_value' => "Commonspace Daily News " . date("F jS, Y", strtotime("now"))
  );
  $form['lead']['title'] = array(
    '#type' => 'textfield',
    '#title' => 'Main Article Title'
  );
  $form['lead']['id'] = array(
    '#type' => 'hidden',
    '#value' => '',
    '#attributes' => array('id' => 'lead-id'),
  );
  $form['lead']['caption'] = array(
    '#type' => 'textarea',
    '#title' => 'Main Article Caption'
  );
  $form['aarticles'] = array(
    '#title' => t('Recent Articles'),
    '#type' => 'checkboxes',
    '#description' => t('Select the articles to include in the mailing.'),
    '#options' => ccrm_newsletter_get_news(true),
  );

  $form['extra_title'] = array(
    '#title' => t('Add Article'),
    '#type' => 'textfield',
    '#maxlength' => 60,
    '#autocomplete_path' => 'newsletters-autocomplete-engine',
  );

  $db_result = array();
  $rows= array();
  $form['#tree'] = TRUE;
  $max = 60;
  foreach($db_result as $nid => $title){
      if(strlen($title)>$max)
          $title = substr($title,0,$max).' ...';
      $form['articles'][$nid]['id'] = array(
          '#type' => 'hidden',
          '#default_value' => $nid,
      );
      // Textfield to hold content id.
      $form['articles'][$nid]['title'] = array(
          '#type'  => 'textfield',
          '#value' => $title
      );
      $form['articles'][$nid]['caption'] = array(
          '#type' => 'textarea',
          '#value' => ''
      );
      // This field is invisible, but contains sort info (weights).
      $form['articles'][$nid]['weight'] = array(
          '#type' => 'weight',
          '#title' => t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => 10,
      );
      // Operation links (to remove rows).
      $form['articles'][$nid]['op'] = array(
        '#markup' => '<a href="#" class="remove-row">' . t('x') . '</a>',
      );
  }

  $form['submit'] = array('#type' => 'submit', '#value' => t('Generate mailing'));
  return $form;
}

function ccrm_newsletter_theme($existing, $type, $theme, $path) {
	return array(
	    'ccrm_newsletter_manage' => array(
	        'render element' => 'form',
	    ),
	);
}

function theme_ccrm_newsletter_manage($variables) {
	$form = $variables['form'];

	$rows = array();
	foreach (element_children($form['articles']) as $nid) {
	    $form['articles'][$nid]['weight']['#attributes']['class'] = array('slides-order-weight');
	    $rows[] = array(
	        'data' => array(
	            array('class' => array('slide-cross')),
                  drupal_render($form['articles'][$nid]['title']),
                  drupal_render($form['articles'][$nid]['caption']),
	                drupal_render($form['articles'][$nid]['weight']),
	                drupal_render($form['articles'][$nid]['op']),
	            ),
	        'class' => array('draggable'),
	    );
	}

	$header = array(t('Articles to include in mailing'), '', '');
	$output = drupal_render($form['note']);
	$output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'slides-order')));
	$output .= drupal_render_children($form);

	drupal_add_tabledrag('slides-order', 'order', 'sibling', 'slides-order-weight');

  // jQuery to implement remove feature. Setting the text field to empty
  // and submitting the form will remove the rows.
	$js_injectable = file_get_contents(__DIR__ . "/scripts.js");
	$row_tpl = file_get_contents(__DIR__ . "/templates/ui-row.tpl");
	$row_tpl_safe = str_replace(array('\n', '\t', '\r', '  '), '', $row_tpl);
	$chkbox_tpl = file_get_contents(__DIR__ . "/templates/checkbox.tpl");
	$chkbox_tpl_safe = str_replace(array('\n', '\t', '\r', '  '), '', $chkbox_tpl);

	$interpolated_script = str_replace("{newrow}", $row_tpl_safe, $js_injectable);
	$interpolated_script = str_replace("{newchkbox}", $chkbox_tpl_safe, $interpolated_script);
  $interpolated_script = str_replace("{captiontable}", str_replace(array('\n','\t','\r'), '', json_encode(ccrm_newsletter_get_news(false))), $interpolated_script);

	drupal_add_js($interpolated_script, array('type' => 'inline'));

	return $output;
}

function ccrm_newsletter_manage_submit($form, &$form_state) {
  // echo "<pre>";
  // print_r($_POST);
  // die();
  // $vars = array(
  //   'subject' => $form['lead']['subject']['#value'],
  //   'lead_title' => $form['lead']['title']['#value'],
  //   'lead_id' => $form['lead']['id']['#value'],
  //   'lead_caption' => $form['lead']['caption']['#value']
  // );
  $vars = array(
    'mailing_subject' => $_POST['lead']['subject'],
    'mailing_name' => $_POST['lead']['name'],
    'lead_title' => $_POST['lead']['title'],
    'lead_id' => $_POST['lead']['id'],
    'lead_caption' => $_POST['lead']['caption'],
    'articles' => $_POST['articles']
  );

  $mailing_id = ccrm_prepare_mailing($vars);

  drupal_goto("/civicrm/mailing/send?mid=$mailing_id&continue=true&reset=1");
//  drupal_set_message(t('Ordering have been saved.'));
}

// Custom array sort function by weight.
function _ccrm_newsletter_arraysort($a, $b) {
  if (isset($a['weight']) && isset($b['weight'])) {
      return $a['weight'] < $b['weight'] ? -1 : 1;
  }
  return 0;
}