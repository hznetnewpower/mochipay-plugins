<?php
namespace MochiPay\Sylius\DependencyInjection;
class MochiPayExtension extends \Symfony\Component\DependencyInjection\Extension\Extension
{
 public function load(array $configs,\Symfony\Component\DependencyInjection\ContainerBuilder $container):void {
  require_once dirname(__DIR__).'/lib/bootstrap.php';
  (new \Symfony\Component\DependencyInjection\Loader\YamlFileLoader($container,new \Symfony\Component\Config\FileLocator(dirname(__DIR__).'/Resources/config')))->load('services.yaml');
 }
}
