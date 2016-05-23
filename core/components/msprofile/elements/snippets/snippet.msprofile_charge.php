<?php
/** @var array $scriptProperties */
/** @var msProfile $msProfile */
$msProfile = $modx->getService('msprofile', 'msProfile', MODX_CORE_PATH . 'components/msprofile/model/msprofile/',
    $scriptProperties
);
if (!($msProfile instanceof msProfile)) {
    return '';
}
if (!$modx->user->isAuthenticated($modx->context->key)) {
    return $modx->lexicon('ms2_profile_err_auth');
}
/** @var pdoFetch $pdoFetch */
$pdoFetch = $modx->getService('pdoFetch');
$pdoFetch->setConfig($scriptProperties);

$minSum = $modx->getOption('minSum', $scriptProperties, 200);
$maxSum = $modx->getOption('maxSum', $scriptProperties, 0);
$outputSeparator = $modx->getOption('outputSeparator', $scriptProperties, "\n");
$tplOrder = $modx->getOption('tplOrder', $scriptProperties, 'tpl.msOrder.success');
$tplPayment = $modx->getOption('tplPayment', $scriptProperties, 'tpl.msProfile.charge.payment');
$tplForm = $modx->getOption('tplForm', $scriptProperties, 'tpl.msProfile.charge.form');

if (!empty($_GET['msorder'])) {
    if ($order = $modx->getObject('msOrder', $_GET['msorder'])) {
        if ((!empty($_SESSION['minishop2']['orders']) && in_array($_GET['msorder'],
                    $_SESSION['minishop2']['orders'])) || $order->get('user_id') == $modx->user->id || $modx->context->key == 'mgr'
        ) {
            return $pdoFetch->getChunk($tplOrder, array('id' => $_GET['msorder']));
        }
    }
}

$error = '';
$errors = array();
if (!empty($_POST['action']) && $_POST['action'] == 'profile_charge') {
    $response = $msProfile->createPayment($_POST);
    if (!$response['success']) {
        $error = $response['message'];
        $errors = $response['data'];
    }
}

$where = array('class:NOT LIKE' => 'CustomerAccount%', 'class:!=' => '');
if (empty($showInactive)) {
    $where['active'] = true;
}
if (!empty($payments)) {
    $payments = array_map('trim', explode(',', $payments));
    $in = $out = array();
    foreach ($payments as $payment) {
        if ($payment > 0) {
            $in[] = $payment;
        } elseif ($payment < 0) {
            $out[] = abs($payment);
        }
    }
    if (!empty($in)) {
        $where['id:IN'] = $in;
    } elseif (!empty($out)) {
        $where['id:NOT IN'] = $out;
    }
}

// Add custom parameters
foreach (array('where') as $v) {
    if (!empty($scriptProperties[$v])) {
        $tmp = is_string($scriptProperties[$v])
            ? json_decode($scriptProperties[$v], true)
            : $scriptProperties[$v];
        if (is_array($tmp)) {
            $$v = array_merge($$v, $tmp);
        }
    }
    unset($scriptProperties[$v]);
}

$options = array(
    'class' => 'msPayment',
    'where' => $where,
    'sortby' => 'rank',
    'sortdir' => 'ASC',
    'nestedChunkPrefix' => 'minishop2_',
);

// Merge all properties and run!
$pdoFetch->addTime('Query parameters are prepared.');
$pdoFetch->setConfig(array_merge($options, $scriptProperties));

$methods = $pdoFetch->getCollection('msPayment', $where, $options);
if (empty($methods)) {
    return $modx->lexicon('ms2_profile_err_payments');
}
$payments = array();
foreach ($methods as $key => $method) {
    $method['checked'] = (empty($_POST['payment']) && $key == 0) || (!empty($_POST['payment']) && $_POST['payment'] == $method['id'])
        ? 'checked'
        : '';
    $payments[] = $pdoFetch->getChunk($tplPayment, $method);
}
$payments = implode($outputSeparator, $payments);

$data = array(
    'payments' => $payments,
    'sum' => !empty($_POST['sum'])
        ? $_POST['sum']
        : $minSum,
    'min_sum' => $minSum,
    'max_sum' => $maxSum,
    'error' => $error,
);
foreach ($errors as $key => $error) {
    $data['error_' . $key] = $error;
}

return $pdoFetch->getChunk($tplForm, $data);