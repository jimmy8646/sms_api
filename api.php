<?php

function hotkt_phone_variable_group_info()
{
    $groups['sm_send'] = array(
    'title' => t('手機簡訊驗證'),
    'description' => t('三竹簡訊手機驗證'),
  );

    return $groups;
}

function hotkt_phone_variable_info($options)
{
    $variable['switch'] = array(
  'title' => t('啟用'),
  'type' => 'boolean',
  'group' => 'sm_send',
);
    $variable['username'] = array(
    'title' => t('帳號'),
    'type' => 'string',
    'group' => 'sm_send',
  );
    $variable['password'] = array(
    'title' => t('密碼'),
    'type' => 'string',
    'group' => 'sm_send',
  );
    $variable['smexpress_url'] = array(
    'title' => t('網址'),
    'type' => 'string',
    'description' => t('請參考三竹簡訊api'),
    'group' => 'sm_send',
 );
    $variable['limt_time'] = array(
 'title' => t('限制時間'),
 'type' => 'string',
 'description' => t('請輸入整數'),
 'group' => 'sm_send',
);
    $variable['status_code'] = array(
 'title' => t('簡訊回傳碼'),
 'type' => 'text',
 'description' => t('JSON格式'),
 'group' => 'sm_send',
);

    return $variable;
}
function hotkt_phone_form_user_profile_form_alter(&$form, &$form_state, $form_id)
{
    global $user;
    $has_role = array_intersect(array('商家', '玩家遊客'), array_values($user->roles));
    if (empty($has_role) ? false : true) {
        $form['field_cellphone']['#disabled'] = true;
    }
}

function hotkt_phone_form_complete_profile_form_alter(&$form, &$form_state, $form_id)
{
    unset($form['help']);
    $limt_time = variable_get('limt_time');
    if (!isset($sec)) {
        $sec = '0';
    }
    if (!isset($lock)) {
        $lock = '0';
    }
    if (isset($_SESSION['sms_request_time'])) {
        $deadline = $_SESSION['sms_request_time'] + $limt_time;
        $sec = $deadline - time();
        if ($sec < 0) {
            $sec = '0';
            $_SESSION['lock'] = '0';
        }
    }
    $form['message'] = array(
      '#markup' => '<div class="inline-messages"></div>',
    );
    $form['count_down'] = array(
      '#markup' => '<div class="count-down element-invisible">'.$sec.'</div>',
    );
    $form['verify_message'] = array(
      '#markup' => '<div class="verify-messages">請至所填寫的手機查看驗證碼</div>',
    );
    $form['verify'] = array(
      '#type' => 'textfield',
      '#title' => t('驗證碼'),
      '#required' => true,
    );
    $form['field_cellphone']['verify_button'] = array(
      '#type' => 'button',
      '#name' => 'send_sms_button',
      '#value' => t('下一步'),
      '#ajax' => array(
        'callback' => 'ajax_send_sms_callback_function',
        'event' => 'click',
      ),
      '#limit_validation_errors' => array(
        array('field_cellphone'),
        array('field_user_name'),
      ),
      '#executes_submit_callback' => false,
    );
    $form['actions']['submit']['#ajax'] = array(
  'callback' => 'ajax_send_sms_submit',
  'event' => 'click',
);
    $form['#validate'][] = 'user_phone_verify_validate';
    if (isset($_SESSION['lock'])) {
        if ($_SESSION['lock'] == '0') {
            $form['field_cellphone']['verify_button']['#attributes']['class'][] = 'unlock';
        } elseif ($_SESSION['lock'] == '1') {
            $form['field_cellphone']['verify_button']['#attributes']['class'][] = 'lock';
        }
    }
    $form['#submit'][] = 'user_phone_verify_submit';
    $form['validate-message'] = array(
      '#markup' => '<div class="validate-message"></div>',
    );
}

function user_phone_verify_validate($form, &$form_state)
{
    global $user;
    $uid = $user->uid;
    if ($form_state['triggering_element']['#name'] == 'op') {
        $submit_verify = $form_state['values']['verify']; // 送出的驗證碼
        if ($submit_verify != $_SESSION['sms_verify_num']) {
            form_set_error($submit_verify, t('您輸入的驗證碼錯誤'));
        }
    }
}

function user_phone_verify_submit(&$form, &$form_state)
{
    ctools_include('ajax');
    ctools_add_js('ajax-responder');
// Path to redirect to
$path = '/user';
    $commands[] = ctools_ajax_command_redirect($path);
// you can also use ctools_ajax_command_reload() –  xurshid29
echo ajax_render($commands);
    drupal_exit();
}
function ajax_send_sms_callback_function(&$form, &$form_state)
{
    global $user;
    $uid = $user->uid;
    $uname = $user->name; // 帳號名稱
    $limt_time = variable_get('limt_time');
    $commands = array();
    $verify_num = '';
    $digits = 4;
    for ($i = 0; $i < $digits; ++$i) {
        $verify_num .= rand(0, 9);
    }
    $_SESSION['sms_verify_num'] = $verify_num;
    $phone_number = $form_state['values']['field_cellphone']['und'][0]['value'];

    if (!isset($lock)) {  //init
      $_SESSION['lock'] = '0';
        $lock = $_SESSION['lock'];
    }
    if (!isset($sec)) {  //init
      $sec = '0';
    }
    if (isset($_SESSION['sms_request_time'])) {  //判斷秒數解鎖
      $deadline = $_SESSION['sms_request_time'] + $limt_time;
        $sec = $deadline - time();
        if ($sec > 0) {  //距離上次請求 小於90秒 上鎖
          $_SESSION['lock'] = '1';
            $lock = $_SESSION['lock'];
        } elseif ($sec < 0) {  //距離上次請求 大於90秒 秒數歸零 解鎖 更新驗證碼
          $sec = '0';  // 解鎖秒數歸零
          $_SESSION['lock'] = '0';
            $lock = $_SESSION['lock'];
            $_SESSION['sms_verify_num'] = $verify_num;
        }
    }

    if ($lock == '1') {
        $commands[] = ajax_command_replace('.count-down', '<div class="count-down element-invisible">'.$sec.'</div>');
        $commands[] = ajax_command_replace('.inline-messages', '<div class="inline-messages">'.theme('status_messages').'</div>');
        $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('ajax-unlock'));
        $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('ajax-lock'));

        return array('#type' => 'ajax', '#commands' => $commands);
    } elseif ($lock == '0') {
        if ($form_state['triggering_element']['#name'] == 'send_sms_button') {
            $commands[] = ajax_command_replace('.count-down', '<div class="count-down element-invisible">'.$sec.'</div>');
            $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('ajax-lock'));
            $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('ajax-unlock'));
            if (form_get_errors()) {
                $form_state['rebuild'] = false;
                $commands[] = ajax_command_replace('.inline-messages', '<div class="inline-messages">'.theme('status_messages').'</div>');
                $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('validate-trues'));
                $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('validate-false'));
                $commands[] = ajax_command_invoke('.form-item-verify', 'removeClass', array('validate-trues'));
                $commands[] = ajax_command_invoke('.form-item-verify', 'addClass', array('validate-false'));

                return array('#type' => 'ajax', '#commands' => $commands);
            } else {
                $form_state['rebuild'] = false;
                $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('validate-false'));
                $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('validate-trues'));
                $commands[] = ajax_command_invoke('.form-item-verify', 'removeClass', array('validate-false'));
                $commands[] = ajax_command_invoke('.form-item-verify', 'addClass', array('validate-trues'));
                $commands[] = ajax_command_replace('.inline-messages', '<div class="inline-messages">'.theme('status_messages').'</div>');
                // $commands[] = ajax_command_replace('.count-down', '<div class="count-down">'.$sec.'</div>');
                $smbody = '您的驗證號碼為 '.$verify_num.' 請在網頁上輸入進行認證';
                $send_respond = send_verify_sms($phone_number, $smbody);  // call my send sms function
                $_SESSION['sms_request_time'] = REQUEST_TIME;
                $msgid = explode('=', $send_respond['1']);  // =右方的數字
                $statuscode = explode('=', $send_respond['2']); // =右方的數字
                $json_status = variable_get('status_code');
                $status_decode = drupal_json_decode($json_status);

                $scode = isset($status_decode[$statuscode['1']]) ? $status_decode[$statuscode['1']] : $status_decode['1']; // 有對應code就吐 沒有就原值
            $entity = entity_create('eck', array('type' => 'phone_verify'));  //產生一個eck紀錄
            $entity->title = $uname;
                $entity->field_uid = array(
              'und' => array(array(
                'target_id' => $uid,
              )),
            );
                $entity->field_sms_number = array(
              'und' => array(array(
                'value' => $msgid['1'],
              )),
            );
                $entity->field_sms_status = array(
              'und' => array(array(
                'value' => $scode,
              )),
            );
                $entity->save();
                $_SESSION['lock'] = '1';
                // $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('ajax-unlock'));
                // $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('ajax-lock'));
                $commands[] = ajax_command_replace('.count-down', '<div class="count-down element-invisible">'."$limt_time".'</div>');

                return array('#type' => 'ajax', '#commands' => $commands);
            }
        }
    }
}
function ajax_send_sms_submit(&$form, &$form_state)
{
    if (form_get_errors()) {  // 如果有錯誤來自vaildate 或是還在上鎖狀態
     $form_state['rebuild'] = true;
        $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('validate-trues'));
        $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('validate-false'));
        $commands[] = ajax_command_invoke('.form-item-verify', 'removeClass', array('validate-trues'));
        $commands[] = ajax_command_invoke('.form-item-verify', 'addClass', array('validate-false'));
        $commands[] = ajax_command_replace('.inline-messages', '<div class="inline-messages">'.theme('status_messages').'</div>');

        ajax_deliver(array('#type' => 'ajax', '#commands' => $commands));
    } else {
        $form_state['rebuild'] = true;
        $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'removeClass', array('validate-false'));
        $commands[] = ajax_command_invoke('#edit-field-cellphone-verify-button', 'addClass', array('validate-trues'));
        $commands[] = ajax_command_invoke('.form-item-verify', 'removeClass', array('validate-false'));
        $commands[] = ajax_command_invoke('.form-item-verify', 'addClass', array('validate-trues'));

        ajax_deliver(array('#type' => 'ajax', '#commands' => $commands));
    }
}
function send_verify_sms($phone_number, $smbody)
{
    $switch = variable_get('switch');
    if ($switch == '1') {
        $url = variable_get('smexpress_url');
        $username = variable_get('username');
        $password = variable_get('password');
        $dstaddr = $phone_number;
        $data = array(
    'username' => $username,
    'password' => $password,
    'dstaddr' => $dstaddr,
    'encoding' => 'UTF8',
    'smbody' => $smbody,
  );
        $full_url = url($url, array('query' => $data));
        $result = drupal_http_request($full_url);
    // dpm($result);
    // 幹～送回來的東西有夠醜
    // $t = '[1]
    // msgid=0916180121
    // statuscode=1
    // AccountPoint=1468';
    $split = explode("\n", $result->data); // 用換行分開
    return $split;
    }
}
