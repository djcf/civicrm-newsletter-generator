<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function get_news() {
  $defaults = array(
      'max' => 30,
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

  return $entities['node'];
  //return array();
  $news_items_nids = array_keys($entities[$defaults['entity']]);
  $news_items = entity_load($defaults['entity'], $news_items_nids);
  $r = array();
  foreach($news_items as $nid => $news_item) {
    $r[$nid] = $news_item->title;
  }
  print_r($r);
  return $r;
}

function newsletter_manage() {
  $articles = get_news();
  
  // Empty row for new newsletter input.
  $articles[] = array('nid' => 0, 'caption' => '', 'weight' => 0);
  
  $form['#tree'] = TRUE;
  foreach ($articles as $id => $article) {
    if (!empty($article->nid)) {
      $node = node_load($article->nid);
    }
    else {
      $node = (object)array('title' => '');
    }
    
    // Textfield to hold content id.
    $form['articles'][$article->nid]['node'] = array(
      '#type' => 'textfield',
      '#autocomplete_path' => 'ui-newsletter/autocomplete',
      '#default_value' => check_plain($node->title) . (!empty($node->nid) ? " [$node->nid]" : ''),
    );
    // Caption for the newsletter.
    $form['articles'][$article->nid]['caption'] = array(
      '#type' => 'textfield',
      '#default_value' => 'test',
    );
    // This field is invisible, but contains sort info (weights).
    $form['articles'][$article->nid]['weight'] = array(
      '#type' => 'weight',
      '#title' => t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => $article->weight,
    );
    // Operation links (to remove rows).
    $form['articles'][$article->nid]['op'] = array(
      '#markup' => '<a href="#" class="remove-row">' . t('Remove') . '</a>',
    );
  }
  
  // jQuery to implement remove feature. Setting the text field to empty
  // and submitting the form will remove the rows.
  $js = <<<EOD
(function ($) {
  $(function() {
    $('a.remove-row').click(function() {
      $(this).closest('tr').fadeOut(function() {
        $(this).find('input.form-autocomplete').val('')
          .closest('form').submit();
      });
    });;
  });
})(jQuery);
EOD;
  
  drupal_add_js($js, array('type' => 'inline'));
  
  $form['submit'] = array('#type' => 'submit', '#value' => t('Save changes'));
  return $form;
}

// This looks for the node id in the submitted value, "Test title string [123]"
function newsletter_manage_submit($form, &$form_state) {
  $articles = array();
  foreach ($form_state['values']['articles'] as $article) {
    preg_match('/\[(\d+)\]$/', $article['node'], $matches);
    if ($nid = !empty($matches[1]) ? (int)$matches[1] : 0) {
      $articles[] = array(
        'nid' => $nid,
        'caption' => "",
        'weight' => $article['weight'],
      );
    }
  }
  
  if (!empty($articles)) {
    usort($articles, '_newsletter_arraysort');
  }
  
  variable_set('newsletters', $articles);
  drupal_set_message(t('newsletters have been saved.'));
}
// Custom array sort function by weight.
function _newsletter_arraysort($a, $b) {
  if (isset($a['weight']) && isset($b['weight'])) {
    return $a['weight'] < $b['weight'] ? -1 : 1;
  }
  return 0;
}

// Search titles of article and page contents.
function newsletter_autocomplete($string) {
  $query = db_select('node', 'n');
  $query->fields('n', array('nid', 'title'));
  
  $types = array('article', 'page'); // Add additional content types as you like.
  if (!empty($types)) {
    $db_or = db_or();
    foreach ($types as $type) {
      if (!empty($type)) {
        $db_or->condition('n.type', $type, '=');
      }
    }
    $query->condition($db_or);
  }
  
  $result = $query
    ->condition('n.title', '%' . db_like($string) . '%', 'LIKE')
    ->range(0, 10)
    ->execute();
  
  $matches = array();
  foreach ($result as $row) {
    $matches[$row->title . " [$row->nid]"] = check_plain($row->title) . " [$row->nid]";
  }
  
  drupal_json_output($matches);
}

// Theme function for newsletter_manage().
function theme_newsletter_manage($variables) {
  $form = $variables['form'];
  
  $rows = array();
  foreach (element_children($form['newsletters']) as $nid) {
    $form['newsletters'][$nid]['weight']['#attributes']['class'] = array('newsletters-weight');
    $rows[] = array(
      'data' => array(
        array('class' => array('newsletter-cross')),
        drupal_render($form['newsletters'][$nid]['node']),
        drupal_render($form['newsletters'][$nid]['caption']),
        drupal_render($form['newsletters'][$nid]['weight']),
        drupal_render($form['newsletters'][$nid]['op']),
      ),
      'class' => array('draggable', 'newsletters-weight'),
    );
  }
  
  $header = array('', t('Content'), t('Caption (If empty, title is used)'), t('Weight'), t('Operations'));
  $output = drupal_render($form['note']);
  $output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'newsletters-table')));
  $output .= drupal_render_children($form);
  drupal_add_tabledrag('newsletters-table', 'order', 'sibling', 'newsletters-weight');
  
  return $output;
}