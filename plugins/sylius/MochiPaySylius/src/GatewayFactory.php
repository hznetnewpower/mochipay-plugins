<?php
namespace MochiPay\Sylius;
class GatewayFactory extends \Payum\Core\GatewayFactory
{
 protected function populateConfig(\Payum\Core\Bridge\Spl\ArrayObject $config){$config->defaults(['payum.factory_name'=>'mochipay','payum.factory_title'=>'MochiPay']);}
}
