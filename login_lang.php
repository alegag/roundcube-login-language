<?php

/**
 * login_lang.php 
 *
 * Plugin to add a language selector in login screen
 *
 * @version 1.0
 * @author Hassansin
 * @https://github.com/hassansin
 * @example: http://kawaii.com
 */
class login_lang extends rcube_plugin
{
  public $task = 'login|logout';
  public $noajax = true;
  public $noframe = true;

  public function init()
  {
    $this->load_config();
    $this->add_hook('logout_after',array($this,'logout_set_session'));
    $this->add_hook('template_object_loginform', array($this, 'add_login_lang'));    //program/include/rcmail_output_html.php
    $this->add_hook('login_after',array($this,'change_lang'));
  }

  public function logout_set_session($arg)
  {
    $_SESSION['lang_selected'] = $_SESSION['language'];
    return $arg;
  }

  public function change_lang ($attr)
  {
    $user_lang = rcube::get_instance()->get_user_language();

    if (isset($_POST['_language']) === true) {
      $lang = rcube_utils::get_input_value('_language', rcube_utils::INPUT_POST);
    } elseif ($user_lang) {
      $lang = $user_lang;
    } else {
      $lang = rcube::get_instance()->config->get('language');
    }

    rcube::get_instance()->load_language($lang);
    $db = rcube::get_instance()->get_dbh();
    $db->query(
      "UPDATE ".$db->table_name('users').
      " SET language = ?".
      " WHERE user_id = ?",
      $lang,
      $_SESSION['user_id']
    );
    return $attr;
  }

  public function add_login_lang($arg)
  {
    $rcmail = rcube::get_instance();

    $list_lang = $rcmail->list_languages();
    asort($list_lang);

    // Figure out language from browser's default
    $browser_lang = null;
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) === true) {
      list($languages) = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
      $langs = explode(',', $languages);

      // For each language define in browser's default
      foreach ($langs as $lang) {
        // Change something like 'en-US' to 'en_US'
        $lang = str_replace('-', '_', $lang);
           
        // Check against RoundCube list of language
        foreach ($list_lang as $langid => $lang_label) {
          // Check if there is an exact match first
          if ($langid == $lang) {
            $browser_lang = $langid;
            break 2;
          }

          // If not, check for a fussy match
          list($lang_short) = explode('_', $langid);
          if ($lang_short == $lang) {
            $browser_lang = $langid;
            break 2;
          }
        }
      }
    }

    $label = $rcmail->gettext('language');
    $label = $rcmail->config->get('language_dropdown_label')? $rcmail->config->get('language_dropdown_label'):$label;

    $user_lang = rcube::get_instance()->get_user_language();
    $current = isset($_SESSION['lang_selected']) ? $_SESSION['lang_selected'] : $rcmail->config->get('language');
    $current = $current? $current : $browser_lang;
    $current = $current? $current : $rcmail->config->get('language_dropdown_selected');
    $current = $current? $current : $user_lang;
    $current = $current? $current : 'en_US';
    $select = new html_select(array('id'=>"_language",'name'=>'_language','style'=>'width:103%;padding:3px;border-radius:-1px;box-shadow: 0 0 5px 2px rgba(71, 135, 177, 0.9);')); // make same fields as larry
    $select->add(array_values($list_lang),array_keys($list_lang));

    $str  ='<tr>';
    $str .='<td class="title"><label for="_language">'.$label.'</label></td>';
    $str .='<td class="input">';
    $str .= $select->show($current);
    $str .= '</td></tr>';

    if(preg_match('/<\/tbody>/', $arg['content'])) {
      $arg['content'] = preg_replace('/<\/tbody>/', $str.'</tbody>', $arg['content']);
    } else {
      $arg['content'] = $arg['content'].$str;
    }

    // use exitings id's message and bottomline
    return $arg;
  }
}
