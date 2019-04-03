<?php
/*
 * Отправка смс с воронки "Сделал заказ",
 * + перекинуть все заявки в "Выслан счет на оплату"
 */
header('Access-Control-Allow-Origin: *');
// время выполнения скрипта
ini_set('max_execution_time', 2147483647);
ini_set('memory_limit', '-1');

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/sms.class.php";
require_once __DIR__ . "/config.php";

try {
    $amo = new \AmoCRM\Client($SUBDOMAIN, $LOGIN, $HASH);
    // собираем сделки с воронки "Сделал заказ"
    $lids = $amo->lead->apiList(['status' => FUNNEL_ORDER]);
    $results = [];
    if ($lids) {
        foreach ($lids as $key => $lid) {
            $custom_fields = $lid["custom_fields"];
            if ($custom_fields && count($custom_fields) > 0) {
                $results[$lid['id']] = ["price" => $lid["price"], "STATUS" => false];
                foreach ($custom_fields as $field) {
                    if ($field["id"] == "638567") {
                        $results[$lid['id']]["IDSIMA"] = $field["values"][0]["value"];
                    }
                    if ("638573" == $field["id"]) {
                        $results[$lid['id']]["phone"] = $field["values"][0]["value"];
                    }
                }
            }

        }
    }
    // *******************  смс ************************************************
    if (count($results) > 0) {
        // следующий четверг
        $date = new DateTime();
        $date->modify('next thursday');
        $day=$date->format('d');
        $month=$date->format('m');
        $MonthNames=array("Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря");
        $dateThis=$day." ".$MonthNames[intval($month)-1];
//        $templateSms = '#price# р. - №#order# Заказ для оплаты с sima-land.ru';
        $templateSms = 'Счет "Сима-ленд" \n СУММА К ОПЛАТЕ: #price# руб.\n Способы оплаты \n
                        1. Перевод на карту Сбербанка 5469 5600 1679 2893, привязана к номеру 89063039990 Мария Николаевна Ф. В КОММЕНТАРИИ К ОПЛАТЕ НИЧЕГО НЕ ПИШИТЕ. \n
                        Пожалуйста, оплатите заказ до #date# до 14:00 и пришлите чек в сообщения группы vk.com/simafm или в ответном сообщении. \n
                        Ваш заказ находится на точке выдачи: Комарова 128 (вход в магазин Форум, на двери надпись "Сима-ленд здесь"). \n
                        График работы: Пн, чт, пт с 9:00 до 18:00 (обед с 13:00 до 14:00), вт, ср с 9:00 до 20:00, сб с 9:00 до 14:00.';
        $search = [
            '#price#',
            '#order#',
            '#date#'
        ];
        foreach ($results as $key => $v) {
            $phone = preg_replace("/[^0-9]{1,4}/", '', $v['phone']);
            if (!empty($phone)) {
                $messages = new \Sms\Xml\Messages(Targetsms_Login, Targetsms_Pas);
                $messages->setUrl('https://sms.targetsms.ru');

                $replace = [
                    $v['price'],
                    $key,
                    $dateThis
                ];
                $textSms = str_replace($search, $replace, $templateSms);
//                echo '<pre style="border:5px solid red;">';
//                var_dump($textSms);
//                echo '</pre>';
                ///* раскоментировать
                $mes = $messages->createNewMessage(Targetsms_Name, $textSms, 'sms');
                $abonent = $mes->createAbonent($phone);
                $abonent->setClientIdSms($key);
                $mes->addAbonent($abonent);
                $messages->addMessage($mes);
                if (!$messages->send()) {
                    echo $messages->getError();
                } else {
                    $results[$key]["STATUS"] = true;
                }
                //раскоментировать */

            }

        }
    }
    // *********** изменение статуса воронки
    $reultsSend=0;
    if (count($results) > 0) {
        foreach ($results as $key => $result) {
            // если статус положительный-то есть смс отправлено
            // перекидуем  в воронку "Выслан счет на оплату"
            ///* раскоментировать
            if ($result["STATUS"]) {
                foreach ($lids as $k => $lid) {
                    if ($lid['id'] == $key) {
                        // Обновление сделок
                        $lead = $amo->lead;
                        //$lead->debug(true);
                        $lead["status_id"] =FUNNEL_PLAYMENT ;
                        $lead->apiUpdate((int)$lid['id'], 'now');
                        $reultsSend++;
                    }
                }
            }
           //раскоментировать */
        }

    }
    echo json_encode(['results'=>$reultsSend]);
} catch (\AmoCRM\Exception $e) {
    printf('Error (%d): %s' . PHP_EOL, $e->getCode(), $e->getMessage());
}
