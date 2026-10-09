<?php
namespace MochiPay\Payment\Model;
class ScopedClient
{
 private $client;private $store;
 public function __construct(Client $client,$store){$this->client=$client;$this->store=$store;}
 public function createOrder(array $payload){return $this->client->createOrder($payload,$this->store);}
 public function queryOrder($id){return $this->client->queryOrder($id,$this->store);}
 public function queryRequest($id){return $this->client->queryRequest($id,$this->store);}
 public function queryReference($id){return $this->client->queryReference($id,$this->store);}
}
