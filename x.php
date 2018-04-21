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

 //return array();
 $news_items_nids = array_keys($entities[$defaults['entity']]);
 $news_items = entity_load($defaults['entity'], $news_items_nids);
 $r = array();
 foreach($news_items as $nid => $news_item) {
   $r[$nid] = $news_item->title;
 }
 return $r;
}

function ccrm_newsletter_menu() {
$items = array();
$items['create/newsletters'] = array(
    'title' => 'Recent News: List Links',   
    'page callback' => 'drupal_get_form',
    'page arguments' => array('ccrm_newsletter_manage'),
    'access arguments' => array('access administration pages'),
);
return $items;
}

function ccrm_newsletter_manage() {
//$db_result = db_query( "select nid,title from node");
// create array and add one element called data
	$db_result = get_news();
$rows= array();
$form['#tree'] = TRUE;
$max = 60;
foreach($db_result as $nid => $title){
    if(strlen($title)>$max)
        $title = substr($title,0,$max).' ...';
    $form['slides'][$nid]['id'] = array(
        '#type' => 'hidden',      
        '#default_value' => $nid,       
    );
    // Textfield to hold content id.
    $form['slides'][$nid]['title'] = array(
        '#type' => 'item',        
        '#title' => $title
    );     
    // This field is invisible, but contains sort info (weights).
    $form['slides'][$nid]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => 10,
    );
}

$form['submit'] = array('#type' => 'submit', '#value' => t('Save changes'));
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
foreach (element_children($form['slides']) as $nid) {
    $form['slides'][$nid]['weight']['#attributes']['class'] = array('slides-order-weight');
    $rows[] = array(
        'data' => array(
            array('class' => array('slide-cross')),               
                drupal_render($form['slides'][$nid]['title']),
                drupal_render($form['slides'][$nid]['weight']),       
            ),
        'class' => array('draggable'),
    );
}

$header = array('',t('title'),t('position'));
$output = drupal_render($form['note']);
$output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'slides-order')));
$output .= drupal_render_children($form);

drupal_add_tabledrag('slides-order', 'order', 'sibling', 'slides-order-weight');

return $output;
}

function ccrm_newsletter_manage_submit($form, &$form_state) {
$slides = array(); 
foreach ($form_state['values']['slides'] as $slide) {   
    $slides[] = array(
        'id' => $slide['id'],       
        'weight' => $slide['weight'],
    );         
}  
if (!empty($slides)) {
    usort($slides, '_ccrm_newsletter_arraysort');
}  
$position = 1;
foreach($slides as $slide){
    $id = $slide['id'];
    $sql = "UPDATE recent_news SET position={$position} WHERE id = {$id}";
    db_query($sql);
    $position++;
}

drupal_set_message(t('Ordering have been saved.'));
}

// Custom array sort function by weight.
function _ccrm_newsletter_arraysort($a, $b) {
if (isset($a['weight']) && isset($b['weight'])) {
    return $a['weight'] < $b['weight'] ? -1 : 1;
}
return 0;
}