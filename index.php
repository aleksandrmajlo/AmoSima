<?php
header('Access-Control-Allow-Origin: *');
// время выполнения скрипта
ini_set('max_execution_time', 2147483647);
ini_set('memory_limit', '-1');

require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/sima.php";
require_once __DIR__."/config.php";



$client = new Sima();
$client->auth($login . ":" . $pass);

$orders = $client->getOrders();
$results = [];
if ($orders) {
    foreach ($orders as $k => $order) {

        $time = strtotime($order->created_at);
        $phone = $order->order_delivery->phone;
        $results[$order->id] = [
            'name' => $order->contact_person,
            'phone' => $phone,
            'email' => $order->email,
            'price' => $order->total,
            'created_at' => $time
        ];
    }
}

try {
    // Создание клиента
    $amo = new \AmoCRM\Client($SUBDOMAIN, $LOGIN, $HASH);
    // информация об аккаунте
//      var_dump($amo->account->apiCurrent(true));
    /*
    * ["leads_statuses"]  -ид воронки искать
     *  ["leads"]=  поля дополнительные для сделки
    */

    // воронка Сделал заказ **************************************************
    $idAppList = FUNNEL_ORDER;

    //поля дополнительные для сделки *******************************************
//    $lihkSima = 85857;
    $lihkSima = 638565;
//    $IDSIMA = 85891;
    $IDSIMA = 638567;
//    $name = 86047;
    $name = 638569;
//    $email = 86049;
    $email = 638571;
//    $phone = 86051;
    $phone = 638573;

    //  все сделки***************************************************************************************
    $lids = $amo->lead->apiList([]);
    // тут фильтруем  уже добаленные не добавляем
    foreach ($lids as $key => $lid) {
        $custom_fields = $lid['custom_fields'];
        if (!empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                if ($field['id'] == $IDSIMA) {
                    $value = $field['values'][0] ["value"];
                    if (isset($results[$value])) {
                        unset($results[$value]);
                    }
                }
            }
        }
    }
    //отфильтрованные добавляем ******************************************************************************
    if (count($results) > 0) {
        foreach ($results as $key => $result) {

            $lead = $amo->lead;
            $lead->debug(false);
            $lead['name'] = 'Cделка c sima-land ID ' . $key;
            $lead['date_create'] = $result['date_create'];
            $lead['status_id'] = $idAppList;
            $lead['price'] = $result['price'];

            $lead->addCustomField($lihkSima, 'https://www.sima-land.ru/order/' . $key . '/');
            $lead->addCustomField($IDSIMA, $key);
            $lead->addCustomField($name, $result['name']);
            $lead->addCustomField($phone, $result['phone']);
            $lead->addCustomField($email, $result['email']);
            $id = $lead->apiAdd();
        }
        echo json_encode(['results'=>count($results)]);
    }else{
        echo json_encode(['results'=>0]);
    }

} catch (\AmoCRM\Exception $e) {
//    printf('Error (%d): %s' . PHP_EOL, $e->getCode(), $e->getMessage());
}
