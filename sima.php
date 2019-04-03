<?php

class Sima {

  private $auth_data;
  private $settlement_id;
  private $token;
  private $jp_organizer;
  private $logger;
  public $JPPurchase=87415;

  private function error() {
    //$this->logger->write();
  }

  public function setAuthData($auth_data) {
    $this->auth_data = $auth_data;
  }

  public function setSettlementId($settlement_id) {
    $this->settlement_id = $settlement_id;
  }

  public function setLogger($logger) {
    $this->logger = $logger;
  }

  /*
   * Конструктор урла по названию сущности
   */

  private function createUrl($entity) {
    $url = 'https://www.sima-land.ru/api/v3';
    $urlLen = strlen($url);
    $entityLen = strlen($entity);
    if ($url[$urlLen - 1] != '/' && $entity[0] != '/') {
      $url .= "/";
    }
    if ($entity[$entityLen - 1] != '/') {
      $entity .= "/";
    }
    return $url . $entity;
  }


  /**
   * Функция авторизует пользователя и получает токен для выполнения методов, требующих авторизацию
   * @param string $auth_data
   * @return bool
   */
  public function auth($auth_data = '') {

    if ($this->auth_data) {
      $auth_data = $this->auth_data;
    }

    if ($auth_data) {

      $curl = curl_init();

      curl_setopt($curl, CURLOPT_URL, $this->createUrl('auth'));

      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . base64_encode($auth_data),
      ));

      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

      $response = json_decode(curl_exec($curl));

      curl_close($curl);

      $this->token = $response->jwt;

      return $this->token;

    }

    return false;

  }

  public function getDeliveryPrice($items, $settlement_id = 0) {

    $deliveryPrice = 0;

    if ($this->settlement_id) {
      $settlement_id = $this->settlement_id;
    }

    $calculate = $settlement_id && is_array($items) && !empty($items);

    if ($calculate) {

      $order_data = array(
        'settlement_id' => $settlement_id,
        'items' => $items
      );

      $curl = curl_init();

      curl_setopt($curl, CURLOPT_URL, $this->createUrl('delivery-price'));
      curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

      curl_setopt($curl, CURLOPT_POST, true);

      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($order_data));

      curl_setopt($curl, CURLOPT_TIMEOUT, 2);

      $json = curl_exec($curl);

      $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

      curl_close($curl);

      if ($httpCode == 200) {

        $json = json_decode($json);

        if (is_numeric($json->totalSum)) {

          $deliveryPrice = $json->totalSum;

        }

      } else {

        $deliveryPrice = false;

      }

    } else {

      $deliveryPrice = false;

    }

    return $deliveryPrice;

  }

  public function getDeliveryDate($item_id) {

    $deliveryDate = false;

    if ($this->token) {

      $curl = curl_init();

      curl_setopt($curl, CURLOPT_URL, $this->createUrl('item') . $item_id . '/?expand=delivery_date');

      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $this->token,
      ));

      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

      $response = json_decode(curl_exec($curl));

      preg_match('/(\d+)\s(\S+)/', $response->delivery_date, $matches);

      $day = (int) $matches[1];
      $month = $matches[2];

      $deliveryDate = $day . ' ' . $month;

      curl_close($curl);

    }

    return $deliveryDate;

  }


  /**
   * Функция проверки наличия товаров на сайте sima-land.ru
   * @param $products
   * @param string $field
   * @return array
   */
  public function checkProducts($products, $field = 'sku') {

    $availableProducts = array();
    $productsSKU = array();
    $productsSKUChunks = array();

    foreach ($products as $product) {
      # code...
      $productsSKU[$product['product_id']] = substr($product[$field], 0, 10);
    }

    $productsSKUChunks = array_chunk($productsSKU, 50);

    foreach ($productsSKUChunks as $productsSKUChunk) {
      # code...
      $curl = curl_init('https://www.sima-land.ru/api/v3/item/?sid=' . implode(',', $productsSKUChunk));
      curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

      $json = curl_exec($curl);

      curl_close($curl);

      $json = json_decode($json, true);

      foreach($json['items'] as $item){
        $availableProducts[$item['sid']] = $item['id'];
      }
    }

    return $availableProducts;

  }

  /**
   * Функция получает идентификатор активной СП дл пользователя sima-land.ru
   * @return bool
   */
  public function getJPPurchase() {

    $jp_purchase = false;

    if ($this->token) {

      $curl = curl_init();

      curl_setopt($curl, CURLOPT_URL, $this->createUrl('jp-organizer') . $this->jp_organizer . '/?expand=jp_purchase');

      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $this->token,
      ));

      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

      $response = json_decode(curl_exec($curl));

      $jp_purchase = $response->jp_purchase->id;

      curl_close($curl);

    }


//    return $jp_purchase;
    return 87415;

  }

    public function getJPOrganizers() {

        $jp_organizers = array();

        if ($this->token) {

            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $this->createUrl('jp-organizer') . '?settlement_id=' . $this->settlement_id);

            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $this->token,
            ));

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = json_decode(curl_exec($curl), true);

            $jp_organizers = $response['items'];

            curl_close($curl);

        }

        return $jp_organizers;
    }

  /**
   * Функция получает список заказов из активной совместной закупки
   * @return array
   */
  public function getOrders() {

    $orders = array();

    if ($this->token) {

      //$href = 'https://www.sima-land.ru/api/v3/order/?page=1&per-page=50&sort=-created_at&jp_purchase_id=' . $this->getJPPurchase() . '&jp_order_status_id=7&is_jp_request_only=1&expand=items,order_delivery,payment_type,user_delivery_address,settlement,order_status,email,jp_organizer,delivery_date_text';
      $href = 'https://www.sima-land.ru/api/v3/order/?jp_purchase_id=' . $this->JPPurchase . '&is_jp_request_only=1&expand=items,order_delivery,payment_type,user_delivery_address,settlement,order_status,email,jp_organizer,delivery_date_text';



      do {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $href);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
          'Authorization: Bearer ' . $this->token,
        ));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($curl));

        if($response->items&&count($response->items)>0){
            foreach ($response->items as $item) {
                $orders[] = $item;
            }
        }


        curl_close($curl);

        $href = $response->_links->next->href;

      } while ($response->_links->self->href != $response->_links->last->href);

    }

    return $orders;

  }

    public function getOrder($id) {


        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.sima-land.ru/api/v3/order/'.$id.'/');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->token,
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($curl));
        curl_close($curl);
        return $response;

    }

  /**
   * Функция ставит статус заказу из СП "Отклонена организатором"
   * @param $order_id
   */
  public function cancelOrder($order_id) {

    $curl = curl_init('https://www.sima-land.ru/api/v3/jp-order/' . $order_id . '/');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $this->token,
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    $updateFieldsData = array(
      'id' => $order_id,
      'jp_order_status_id' => '2',
      'jp_cancellation_reason_id' => '11',
      'jp_cancellation_reason_text' => ''
      // значения всех атрибутов, необходимые для обновления записи
    );
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($updateFieldsData));
    $json = curl_exec($curl);

    if ($this->logger) {
      $this->logger->write($json);
    }

    curl_close($curl);

  }

  /**
   * Функция получает подробную информацию о СП по идентификатору
   * @param $jpId
   * @return mixed|null
   */
  public function getJointPurchase($jpId) {

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->createUrl('jp-purchase') . $jpId . '/');

    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $this->token,
    ));

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = json_decode(curl_exec($curl));

    $jp_purchase = $response;

    curl_close($curl);

    return $jp_purchase;

  }

  /**
   * Функция выбирает заказы в СП (добавляет к ранее выбранным)
   * @param $orders
   */
  public function chooseOrders($orders) {

    $jp_purchase = $this->getJPPurchase();
    $jp_info = $this->getJointPurchase($jp_purchase);

    $jp_orders = array();

    foreach ($jp_info->jp_purchase_selected_orders as $value) {
      array_push($jp_orders, $value->order_id);
    }

    foreach ($orders as $order) {
      array_push($jp_orders, $order);
    }

    $curl = curl_init('https://www.sima-land.ru/api/v3/jp-purchase/' . $jp_purchase . '/');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $this->token,
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    $updateFieldsData = array(
      'jp_purchase_selected_order_ids' => implode(',', $jp_orders)
    );
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($updateFieldsData));
    $json = curl_exec($curl);

    if ($this->logger) {
      $this->logger->write($json);
    }

    curl_close($curl);

  }

  /**
   * Функция подтверждает заказ в СП
   * @param $orderId
   */
  public function acceptOrder($orderId) {

    $curl = curl_init('https://www.sima-land.ru/api/v3/jp-order/' . $orderId . '/');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $this->token,
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    $updateFieldsData = array(
      'jp_order_status_id' => 8
    );
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($updateFieldsData));
    $json = curl_exec($curl);

    if ($this->logger) {
      $this->logger->write($json);
    }

    curl_close($curl);

  }

}